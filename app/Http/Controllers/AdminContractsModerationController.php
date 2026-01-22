<?php

namespace App\Http\Controllers;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Enums\ContractType;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Services\ContractNotificationService;
use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Permissions\Enums\PermissionScope;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Venues\Models\Venue;
use App\Presentation\Breadcrumbs\AdminBreadcrumbsPresenter;
use App\Presentation\Navigation\AdminNavigationPresenter;
use App\Support\DateFormatter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminContractsModerationController extends Controller
{
    public function index(Request $request)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        if ($roleLevel <= 20) {
            abort(403);
        }

        $navigation = app(AdminNavigationPresenter::class)->present([
            'user' => $request->user(),
        ]);
        $breadcrumbs = app(AdminBreadcrumbsPresenter::class)->present([
            'user' => $request->user(),
            'currentHref' => '/admin/contracts-moderation',
        ])['data'];

        $status = $request->string('status')->toString();
        $sort = $request->string('sort', 'submitted_at_desc')->toString();
        $allowedStatuses = [
            ModerationStatus::Pending->value,
            ModerationStatus::Approved->value,
            ModerationStatus::Clarification->value,
            ModerationStatus::Rejected->value,
        ];
        $allowedSorts = ['submitted_at_desc', 'submitted_at_asc'];

        $query = ModerationRequest::query()
            ->where('entity_type', ModerationEntityType::VenueContract->value)
            ->with([
                'entityVenue.venueType:id,name,alias',
                'entityVenue.latestAddress',
                'submitter:id,login',
                'reviewer:id,login',
            ]);

        if (in_array($status, $allowedStatuses, true)) {
            $query->where('status', $status);
        }

        if ($sort === 'submitted_at_asc') {
            $query->orderBy('submitted_at');
        } else {
            $query->orderByDesc('submitted_at');
        }

        $requests = $query
            ->paginate(10)
            ->withQueryString()
            ->through(function (ModerationRequest $request) {
                $venue = $request->entityVenue;
                $reviewer = $request->reviewer;
                $submitter = $request->submitter;
                $meta = $request->meta ?? [];

                return [
                    'id' => $request->id,
                    'status' => $request->status?->value,
                    'submitted_at' => DateFormatter::dateTime($request->submitted_at),
                    'reviewed_at' => DateFormatter::dateTime($request->reviewed_at),
                    'reject_reason' => $request->reject_reason,
                    'contract_type' => $meta['contract_type'] ?? null,
                    'comment' => $meta['comment'] ?? null,
                    'review_comment' => $meta['review_comment'] ?? null,
                    'reviewer' => $reviewer
                        ? [
                            'id' => $reviewer->id,
                            'login' => $reviewer->login,
                        ]
                        : null,
                    'submitter' => $submitter
                        ? [
                            'id' => $submitter->id,
                            'login' => $submitter->login,
                        ]
                        : null,
                    'venue' => $venue
                        ? [
                            'id' => $venue->id,
                            'name' => $venue->name,
                            'address' => $venue->latestAddress?->display_address,
                        ]
                        : null,
                    'type' => $venue?->venueType
                        ? [
                            'id' => $venue->venueType->id,
                            'name' => $venue->venueType->name,
                        ]
                        : null,
                ];
            });

        $permissions = Permission::query()
            ->where('scope', PermissionScope::Resource)
            ->where('target_model', Venue::class)
            ->orderBy('label')
            ->get(['code', 'label'])
            ->map(fn (Permission $permission) => [
                'code' => $permission->code,
                'label' => $permission->label ?: $permission->code,
            ])
            ->all();

        return Inertia::render('Admin/ContractsModeration', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => '/admin/contracts-moderation',
            'breadcrumbs' => $breadcrumbs,
            'filters' => [
                'status' => $status,
                'sort' => $sort,
            ],
            'statusOptions' => [
                ['value' => '', 'label' => 'Все'],
                ['value' => ModerationStatus::Pending->value, 'label' => 'На модерации'],
                ['value' => ModerationStatus::Approved->value, 'label' => 'Подтверждено'],
                ['value' => ModerationStatus::Clarification->value, 'label' => 'Требуются уточнения'],
                ['value' => ModerationStatus::Rejected->value, 'label' => 'Отклонено'],
            ],
            'sortOptions' => [
                ['value' => 'submitted_at_desc', 'label' => 'Сначала новые'],
                ['value' => 'submitted_at_asc', 'label' => 'Сначала старые'],
            ],
            'requests' => $requests,
            'permissions' => $permissions,
            'supervisorPermissionCodes' => $this->getSupervisorPermissionCodes(),
        ]);
    }

    public function approve(Request $request, ModerationRequest $moderationRequest)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        if ($roleLevel <= 20) {
            abort(403);
        }

        if ($moderationRequest->entity_type !== ModerationEntityType::VenueContract) {
            abort(404);
        }

        if (!in_array($moderationRequest->status, [ModerationStatus::Pending, ModerationStatus::Clarification], true)) {
            return back()->withErrors(['moderation' => 'Заявка уже обработана.']);
        }

        $venue = $moderationRequest->entityVenue;
        if (!$venue) {
            return back()->withErrors(['moderation' => 'Площадка не найдена.']);
        }

        $meta = $moderationRequest->meta ?? [];
        $contractTypeValue = $meta['contract_type'] ?? null;
        if (!$contractTypeValue) {
            return back()->withErrors(['moderation' => 'Тип контракта не указан.']);
        }

        $contractType = ContractType::tryFrom($contractTypeValue);
        if (!$contractType || !in_array($contractType, [ContractType::Owner, ContractType::Supervisor], true)) {
            return back()->withErrors(['moderation' => 'Недоступный тип контракта.']);
        }

        $submitter = $moderationRequest->submitter;
        if (!$submitter) {
            return back()->withErrors(['moderation' => 'Пользователь не найден.']);
        }

        if ($this->hasActiveContractType($venue, $contractType)) {
            return back()->withErrors(['moderation' => 'Для площадки уже есть активный контракт этого типа.']);
        }

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,code'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $permissionCodes = $this->filterApprovalPermissionCodes(
            $contractType,
            $data['permissions'] ?? []
        );
        $permissionIds = [];
        if ($permissionCodes !== []) {
            $permissionIds = Permission::query()
                ->whereIn('code', $permissionCodes)
                ->where('scope', PermissionScope::Resource)
                ->where('target_model', Venue::class)
                ->pluck('id')
                ->all();
        }

        $contract = Contract::query()->create([
            'user_id' => $submitter->id,
            'created_by' => $request->user()?->id,
            'name' => $contractType->label(),
            'contract_type' => $contractType,
            'entity_type' => $venue->getMorphClass(),
            'entity_id' => $venue->getKey(),
            'starts_at' => now(),
            'ends_at' => null,
            'status' => ContractStatus::Active,
            'comment' => $data['comment'] ?? null,
        ]);

        if ($permissionIds !== []) {
            $syncData = [];
            foreach ($permissionIds as $permissionId) {
                $syncData[$permissionId] = ['is_active' => true];
            }
            $contract->permissions()->sync($syncData);
        }

        $moderationRequest->update([
            'status' => ModerationStatus::Approved,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'reject_reason' => null,
            'meta' => array_merge($meta, [
                'review_comment' => $data['comment'] ?? null,
                'approved_permissions' => $permissionCodes,
            ]),
        ]);

        app(ContractNotificationService::class)->notifyContractAssigned($contract, $request->user());
        app(ContractNotificationService::class)->notifyModerationStatus(
            $moderationRequest,
            ModerationStatus::Approved,
            $request->user()
        );

        return back();
    }

    public function reject(Request $request, ModerationRequest $moderationRequest)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        if ($roleLevel <= 20) {
            abort(403);
        }

        if ($moderationRequest->entity_type !== ModerationEntityType::VenueContract) {
            abort(404);
        }

        if (!in_array($moderationRequest->status, [ModerationStatus::Pending, ModerationStatus::Clarification], true)) {
            return back()->withErrors(['moderation' => 'Заявка уже обработана.']);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $moderationRequest->update([
            'status' => ModerationStatus::Rejected,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'reject_reason' => $data['reason'] ?? null,
        ]);

        app(ContractNotificationService::class)->notifyModerationStatus(
            $moderationRequest,
            ModerationStatus::Rejected,
            $request->user()
        );

        return back();
    }

    public function clarify(Request $request, ModerationRequest $moderationRequest)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        if ($roleLevel <= 20) {
            abort(403);
        }

        if ($moderationRequest->entity_type !== ModerationEntityType::VenueContract) {
            abort(404);
        }

        if (!in_array($moderationRequest->status, [ModerationStatus::Pending, ModerationStatus::Clarification], true)) {
            return back()->withErrors(['moderation' => 'Заявка уже обработана.']);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $moderationRequest->update([
            'status' => ModerationStatus::Clarification,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'reject_reason' => $data['reason'] ?? null,
        ]);

        app(ContractNotificationService::class)->notifyModerationStatus(
            $moderationRequest,
            ModerationStatus::Clarification,
            $request->user()
        );

        return back();
    }

    private function getRoleLevel(Request $request): int
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        return (int) $user->roles()->max('level');
    }

    private function ensureAccess(int $roleLevel, int $minLevel): void
    {
        if ($roleLevel <= $minLevel) {
            abort(403);
        }
    }

    private function getSupervisorPermissionCodes(): array
    {
        return [
            PermissionCode::VenueBookingConfirm->value,
            PermissionCode::VenueBookingCancel->value,
            PermissionCode::VenueScheduleManage->value,
            PermissionCode::VenueSupervisorView->value,
            PermissionCode::VenueSupervisorManage->value,
        ];
    }

    private function filterApprovalPermissionCodes(ContractType $type, array $permissionCodes): array
    {
        if ($permissionCodes === []) {
            return [];
        }

        if ($type === ContractType::Supervisor) {
            $allowed = $this->getSupervisorPermissionCodes();

            return array_values(array_filter(
                $permissionCodes,
                static fn (string $code) => in_array($code, $allowed, true)
            ));
        }

        return $permissionCodes;
    }

    private function hasActiveContractType(Venue $venue, ContractType $type): bool
    {
        $now = now();

        return Contract::query()
            ->where('entity_type', $venue->getMorphClass())
            ->where('entity_id', $venue->getKey())
            ->where('contract_type', $type->value)
            ->where('status', ContractStatus::Active->value)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->exists();
    }
}
