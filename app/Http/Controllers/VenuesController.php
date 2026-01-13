<?php

namespace App\Http\Controllers;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Enums\ContractType;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Services\ContractManager;
use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\UseCases\SubmitModerationRequest;
use App\Domain\Permissions\Enums\PermissionScope;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Permissions\Registry\PermissionRegistry;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Services\VenueCatalogService;
use App\Domain\Venues\UseCases\CreateVenue;
use App\Domain\Venues\UseCases\UpdateVenue;
use App\Domain\Users\Enums\UserStatus;
use App\Http\Requests\Venues\StoreVenueRequest;
use App\Presentation\Navigation\VenueNavigationPresenter;
use App\Presentation\Venues\MetroOptionsPresenter;
use App\Presentation\Venues\VenueShowPresenter;
use App\Presentation\Venues\VenueSidebarPresenter;
use App\Presentation\Venues\VenueTypeOptionsPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VenuesController extends Controller
{
    public function index()
    {
        $user = request()->user();

        $navigation = app(VenueNavigationPresenter::class)->present([
            'title' => 'Площадки',
        ]);

        $types = app(VenueTypeOptionsPresenter::class)->present()['data'];
        $catalog = app(VenueCatalogService::class);
        $catalogData = $catalog->getHallsList(null, $user);

        return Inertia::render('Venues', [
            'appName' => config('app.name'),
            'venues' => $catalogData['venues'],
            'activeType' => $catalogData['activeType'],
            'activeTypeSlug' => $catalogData['activeTypeSlug'],
            'navigation' => $navigation,
            'types' => $types,
            'metros' => app(MetroOptionsPresenter::class)->present()['data'],
        ]);
    }

    public function type(string $type)
    {
        $user = request()->user();

        $navigation = app(VenueNavigationPresenter::class)->present([
            'title' => 'Площадки',
        ]);
        $catalog = app(VenueCatalogService::class);
        $types = app(VenueTypeOptionsPresenter::class)->present()['data'];
        $catalogData = $catalog->getHallsList($type, $user);

        if (!$catalogData) {
            abort(404);
        }

        return Inertia::render('Venues', [
            'appName' => config('app.name'),
            'venues' => $catalogData['venues'],
            'activeType' => $catalogData['activeType'],
            'activeTypeSlug' => $catalogData['activeTypeSlug'],
            'navigation' => $navigation,
            'types' => $types,
            'metros' => app(MetroOptionsPresenter::class)->present()['data'],
        ]);
    }

    public function show(string $type, Venue $venue)
    {
        $user = request()->user();
        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();
        $venue->load(['venueType:id,name,alias', 'creator:id,login', 'latestAddress.metro:id,name,line_name,line_color,city']);

        $data = app(VenueShowPresenter::class)->present([
            'user' => $user,
            'venue' => $venue,
            'typeSlug' => $type,
        ])['data'];

        return Inertia::render('VenueShow', array_merge(
            ['appName' => config('app.name')],
            $data
        ));
    }

    public function contracts(Request $request, string $type, Venue $venue)
    {
        $user = $request->user();
        $manager = app(ContractManager::class);
        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        if (!$this->canViewContracts($user, $venue)) {
            abort(403);
        }

        $checker = app(PermissionChecker::class);
        $canAssignContracts = $manager->canAssign($user, $venue);
        $assignableTypes = $manager->getAssignableTypes($user, $venue);
        $assignableTypeValues = array_map(static fn (ContractType $contractType) => $contractType->value, $assignableTypes);

        $navigation = app(VenueSidebarPresenter::class)->present([
            'title' => 'Площадки',
            'typeSlug' => $type,
            'venue' => $venue,
            'user' => $user,
        ]);

        $this->ensureContractPermissions();

        $availablePermissions = Permission::query()
            ->where('scope', PermissionScope::Resource)
            ->where('target_model', Venue::class)
            ->orderBy('label')
            ->get(['code', 'label'])
            ->map(function (Permission $permission) use ($checker, $user, $venue, $manager, $assignableTypes, $assignableTypeValues) {
                $code = $permission->code;

                if (!$checker->can($user, $code, $venue)) {
                    return null;
                }

                $allowedTypes = $assignableTypes;

                if ($code === PermissionCode::ContractAssign->value) {
                    $allowedTypes = array_filter(
                        $assignableTypes,
                        fn (ContractType $contractType) => $manager->canGrantContractAssign($user, $venue, $contractType)
                    );
                }

                if ($code === PermissionCode::ContractRevoke->value) {
                    $allowedTypes = array_filter(
                        $assignableTypes,
                        fn (ContractType $contractType) => $manager->canGrantContractRevoke($user, $venue, $contractType)
                    );
                }

                if ($allowedTypes === []) {
                    return null;
                }

                return [
                    'code' => $code,
                    'label' => $permission->label ?: $permission->code,
                    'allowed_types' => $allowedTypes === $assignableTypes
                        ? $assignableTypeValues
                        : array_map(static fn (ContractType $contractType) => $contractType->value, $allowedTypes),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $contracts = Contract::query()
            ->where('entity_type', $venue->getMorphClass())
            ->where('entity_id', $venue->getKey())
            ->with(['user:id,login', 'permissions:id,code,label'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Contract $contract) use ($manager, $user, $venue): array {
                return [
                    'id' => $contract->id,
                    'name' => $contract->name,
                    'contract_type' => $contract->contract_type?->value,
                    'status' => $contract->status?->value,
                    'starts_at' => $contract->starts_at?->toDateTimeString(),
                    'ends_at' => $contract->ends_at?->toDateTimeString(),
                    'comment' => $contract->comment,
                    'created_by' => $contract->created_by,
                    'can_revoke' => $manager->canRevoke($user, $contract, $venue),
                    'user' => $contract->user
                        ? [
                            'id' => $contract->user->id,
                            'login' => $contract->user->login,
                        ]
                        : null,
                    'permissions' => $contract->permissions
                        ->map(static fn ($permission) => [
                            'code' => $permission->code,
                            'label' => $permission->label ?: $permission->code,
                        ])
                        ->all(),
                ];
            })
            ->all();

        return Inertia::render('VenueContracts', [
            'appName' => config('app.name'),
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'alias' => $venue->alias,
            ],
            'contracts' => $contracts,
            'availablePermissions' => $availablePermissions,
            'contractTypes' => collect($assignableTypes)
                ->map(fn (ContractType $contractType) => [
                    'value' => $contractType->value,
                    'label' => $contractType->label(),
                ])
                ->all(),
            'canAssignContracts' => $canAssignContracts,
            'navigation' => $navigation,
            'activeHref' => "/venues/{$type}/{$venue->alias}/contracts",
            'activeTypeSlug' => $type,
        ]);
    }

    public function assignContract(Request $request, string $type, Venue $venue, ContractManager $manager)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        if (!$manager->canAssign($user, $venue)) {
            abort(403);
        }

        $this->ensureContractPermissions();

        $assignableTypes = $manager->getAssignableTypes($user, $venue);
        $assignableTypeValues = array_map(static fn (ContractType $contractType) => $contractType->value, $assignableTypes);
        $assignableTypeRule = $assignableTypeValues === [] ? 'in:' : 'in:' . implode(',', $assignableTypeValues);

        $data = $request->validate([
            'login' => ['nullable', 'string'],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('status', UserStatus::Confirmed->value),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'contract_type' => ['required', 'string', $assignableTypeRule],
            'starts_at' => ['required', 'date', 'after_or_equal:today'],
            'ends_at' => ['nullable', 'date'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,code'],
        ]);

        $target = \App\Models\User::query()
            ->whereKey($data['user_id'])
            ->where('status', UserStatus::Confirmed->value)
            ->first();

        if (!$target) {
            return back()->withErrors(['contract' => 'Пользователь не найден.']);
        }

        $startsAt = !empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : null;
        $endsAt = !empty($data['ends_at']) ? Carbon::parse($data['ends_at']) : null;
        $contractType = ContractType::from($data['contract_type']);

        $result = $manager->assign(
            $user,
            $target,
            $venue,
            $contractType,
            $data['permissions'] ?? [],
            $data['name'] ?? null,
            $startsAt,
            $endsAt,
            $data['comment'] ?? null
        );

        if (!$result->success) {
            return back()->withErrors(['contract' => 'Пользователь не найден.']);
        }

        return back()->with('notice', 'Контракт назначен.');
    }

    public function revokeContract(Request $request, string $type, Venue $venue, Contract $contract, ContractManager $manager)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        if (
            $contract->entity_type !== $venue->getMorphClass()
            || $contract->entity_id !== $venue->getKey()
        ) {
            abort(404);
        }

        $result = $manager->revoke($user, $contract, $venue);

        if (!$result->success) {
            return back()->withErrors(['contract' => 'Пользователь не найден.']);
        }

        return back()->with('notice', 'Контракт аннулирован.');
    }

    private function canViewContracts(?\App\Models\User $user, Venue $venue): bool
    {
        if (!$user) {
            return false;
        }

        $isAdmin = $user->roles()
            ->where('alias', 'admin')
            ->exists();

        if ($isAdmin) {
            return true;
        }

        $now = now();

        return Contract::query()
            ->where('user_id', $user->id)
            ->where('entity_type', $venue->getMorphClass())
            ->where('entity_id', $venue->getKey())
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


    private function ensureContractPermissions(): void
    {
        $definitions = array_filter(
            PermissionRegistry::all(),
            static fn (array $definition) => in_array(
                $definition['code'],
                [PermissionCode::ContractAssign, PermissionCode::ContractRevoke],
                true
            )
        );

        foreach ($definitions as $definition) {
            $code = $definition['code'] instanceof \BackedEnum
                ? $definition['code']->value
                : $definition['code'];

            $scope = $definition['scope'] instanceof \BackedEnum
                ? $definition['scope']->value
                : $definition['scope'];

            Permission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'label' => $definition['label'],
                    'scope' => $scope,
                    'target_model' => $definition['target_model'],
                ]
            );
        }
    }

    public function store(StoreVenueRequest $request, CreateVenue $useCase)
    {
        $this->authorize('create', Venue::class);

        $user = $request->user();
        $data = $request->validated();
        $venue = $useCase->execute($user, $data);

        $typeSlug = $venue->venueType?->alias ? Str::plural($venue->venueType->alias) : '';

        return redirect()->route('venues.show', [
            'type' => $typeSlug,
            'venue' => $venue,
        ]);
    }

    public function update(Request $request, string $type, Venue $venue, UpdateVenue $useCase)
    {
        $this->authorize('update', $venue);

        $fields = $useCase->getEditableFields($venue);
        if ($fields === []) {
            return back()->withErrors(['venue' => 'Редактирование недоступно для этой площадки.']);
        }

        $rules = Arr::only($useCase->getValidationRules($venue), $fields);
        $data = validator($request->only($fields), $rules)->validate();
        $useCase->execute($request->user(), $venue, $data);

        return back();
    }

    public function submitModerationRequest(Request $request, string $type, Venue $venue, SubmitModerationRequest $useCase)
    {
        $user = $request->user();
        $checker = app(PermissionChecker::class);
        $canSubmitModeration = $user
            && $user->status?->value === UserStatus::Confirmed->value
            && $checker->can($user, PermissionCode::VenueSubmitForModeration, $venue);

        if (!$canSubmitModeration) {
            return back()->withErrors([
                'moderation' => 'Недостаточно прав для отправки на модерацию.',
            ]);
        }

        $result = $useCase->execute($user, ModerationEntityType::Venue, $venue);

        if (!$result->success) {
            return back()->withErrors([
                'moderation' => implode("\n", $result->missingRequirements ?? []),
            ]);
        }

        $venue->update([
            'status' => VenueStatus::Moderation,
        ]);

        return back();
    }

    // Методы генерации alias перенесены в доменный use case.
}
