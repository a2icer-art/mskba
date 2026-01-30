<?php

namespace App\Http\Controllers;

use App\Domain\Participants\Enums\ParticipantRoleAssignmentStatus;
use App\Domain\Participants\Models\ParticipantRoleAssignment;
use App\Domain\Users\Services\AccountPageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderAccount($request, 'user');
    }

    public function profile(Request $request)
    {
        return $this->renderAccount($request, 'profile');
    }

    public function contacts(Request $request)
    {
        return $this->renderAccount($request, 'contacts');
    }

    public function access(Request $request)
    {
        return $this->renderAccount($request, 'access');
    }

    public function balance(Request $request)
    {
        return $this->renderAccount($request, 'balance');
    }

    public function role(Request $request, string $alias)
    {
        $user = $request->user();

        $assignment = ParticipantRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', ParticipantRoleAssignmentStatus::Confirmed)
            ->whereHas('role', fn ($q) => $q->where('alias', $alias))
            ->whereNull('context_type')
            ->whereNull('context_id')
            ->first();

        if (!$assignment) {
            abort(404);
        }

        return $this->renderAccount($request, 'role-' . $alias);
    }

    private function renderAccount(Request $request, string $activeTab)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }
        $service = app(AccountPageService::class);

        return Inertia::render('Account', array_merge(
            ['appName' => config('app.name')],
            $service->getProps($user, $activeTab)
        ));
    }
}
