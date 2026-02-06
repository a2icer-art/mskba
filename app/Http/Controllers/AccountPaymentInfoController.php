<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Models\PaymentMethod;
use App\Domain\Payments\Services\PaymentMethodService;
use App\Presentation\Breadcrumbs\AccountBreadcrumbsPresenter;
use App\Presentation\Navigation\AccountNavigationPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountPaymentInfoController extends Controller
{
    public function index(Request $request, PaymentMethodService $paymentMethodService)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $paymentMethods = $user->paymentMethods()
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PaymentMethod $method) => $paymentMethodService->format($method))
            ->values()
            ->all();

        $participantRoles = app(\App\Domain\Users\Services\AccountPageService::class)->getParticipantRoles($user);
        $navigation = app(AccountNavigationPresenter::class)->present([
            'participantRoles' => $participantRoles,
            'messageCounters' => [
                'unread_messages' => app(\App\Domain\Messages\Services\MessageCountersService::class)->getUnreadMessages($user),
            ],
        ]);
        $breadcrumbs = app(AccountBreadcrumbsPresenter::class)->present([
            'activeTab' => 'payment-info',
            'participantRoles' => $participantRoles,
        ])['data'];

        return Inertia::render('AccountPaymentInfo', [
            'appName' => config('app.name'),
            'user' => [
                'id' => $user->id,
                'login' => $user->login,
            ],
            'paymentMethods' => $paymentMethods,
            'navigation' => $navigation,
            'activeHref' => '/account/settings/payment-info',
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function store(Request $request, PaymentMethodService $paymentMethodService)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $data = $paymentMethodService->validate($request);
        $data['owner_type'] = $user->getMorphClass();
        $data['owner_id'] = $user->id;
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;

        PaymentMethod::query()->create($data);

        return back()->with('notice', 'Метод оплаты добавлен.');
    }

    public function update(Request $request, PaymentMethod $paymentMethod, PaymentMethodService $paymentMethodService)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $paymentMethodService->ensureOwner($paymentMethod, $user->getMorphClass(), $user->id);

        $data = $paymentMethodService->validate($request);
        $data['updated_by'] = $user->id;

        $paymentMethod->update($data);

        return back()->with('notice', 'Метод оплаты обновлён.');
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod, PaymentMethodService $paymentMethodService)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $paymentMethodService->ensureOwner($paymentMethod, $user->getMorphClass(), $user->id);

        $paymentMethod->delete();

        return back()->with('notice', 'Метод оплаты удалён.');
    }
}
