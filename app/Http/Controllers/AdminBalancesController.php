<?php

namespace App\Http\Controllers;

use App\Domain\Balances\Services\BalanceService;
use App\Models\User;
use App\Presentation\Breadcrumbs\AdminBreadcrumbsPresenter;
use App\Presentation\Navigation\AdminNavigationPresenter;
use App\Support\DateFormatter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminBalancesController extends Controller
{
    public function index(Request $request)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $query = User::query()
            ->with('balance')
            ->orderByDesc('id');

        $search = $request->string('q')->toString();
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('login', 'like', '%' . $search . '%');
                if (is_numeric($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $status = $request->string('status')->toString();
        if ($status === 'blocked') {
            $query->whereHas('balance', fn ($builder) => $builder->where('status', 'blocked'));
        } elseif ($status === 'active') {
            $query->where(function ($builder): void {
                $builder->whereDoesntHave('balance')
                    ->orWhereHas('balance', fn ($sub) => $sub->where('status', 'active'));
            });
        } else {
            $status = '';
        }

        $users = $query
            ->paginate(10)
            ->withQueryString()
            ->through(function (User $user): array {
                $balance = $user->balance;
                return [
                    'id' => $user->id,
                    'login' => $user->login,
                    'balance' => [
                        'available_amount' => $balance?->available_amount ?? 0,
                        'held_amount' => $balance?->held_amount ?? 0,
                        'currency' => $balance?->currency?->value ?? 'RUB',
                        'status' => $balance?->status?->value ?? 'active',
                        'block_reason' => $balance?->block_reason,
                        'blocked_at' => DateFormatter::dateTime($balance?->blocked_at),
                    ],
                ];
            });

        $navigation = app(AdminNavigationPresenter::class)->present([
            'user' => $request->user(),
        ]);
        $breadcrumbs = app(AdminBreadcrumbsPresenter::class)->present([
            'user' => $request->user(),
            'currentHref' => '/admin/balances',
        ])['data'];

        return Inertia::render('Admin/Balances', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => '/admin/balances',
            'breadcrumbs' => $breadcrumbs,
            'users' => $users,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function topUp(Request $request, User $user, BalanceService $service)
    {
        $this->ensureAccess($this->getRoleLevel($request), 20);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ], [
            'amount.required' => 'Укажите сумму пополнения.',
            'amount.integer' => 'Сумма должна быть числом.',
            'amount.min' => 'Сумма должна быть больше 0.',
        ]);

        $service->topUp($user, (int) $data['amount'], [
            'reason' => 'admin_topup',
        ]);

        return back()->with('notice', 'Баланс пополнен.');
    }

    public function debit(Request $request, User $user, BalanceService $service)
    {
        $this->ensureAccess($this->getRoleLevel($request), 20);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ], [
            'amount.required' => 'Укажите сумму списания.',
            'amount.integer' => 'Сумма должна быть числом.',
            'amount.min' => 'Сумма должна быть больше 0.',
        ]);

        $service->debit($user, (int) $data['amount'], [
            'reason' => 'admin_debit',
        ]);

        return back()->with('notice', 'Списание выполнено.');
    }

    public function block(Request $request, User $user, BalanceService $service)
    {
        $this->ensureAccess($this->getRoleLevel($request), 20);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Укажите причину блокировки.',
        ]);

        $service->block($user, $data['reason'], [
            'reason' => $data['reason'],
        ]);

        return back()->with('notice', 'Баланс заблокирован.');
    }

    public function unblock(Request $request, User $user, BalanceService $service)
    {
        $this->ensureAccess($this->getRoleLevel($request), 20);

        $service->unblock($user, [
            'reason' => 'admin_unblock',
        ]);

        return back()->with('notice', 'Баланс разблокирован.');
    }

    private function getRoleLevel(Request $request): int
    {
        $authUser = $request->user();

        if (!$authUser) {
            abort(403);
        }

        return (int) $authUser->roles()->max('level');
    }

    private function ensureAccess(int $roleLevel, int $minLevel): void
    {
        if ($roleLevel <= $minLevel) {
            abort(403);
        }
    }
}
