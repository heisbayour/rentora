<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Staff\InviteStaff;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** Staff accept an invitation by setting a password through the signed link. */
class StaffInvitationController extends Controller
{
    public function show(Request $request, User $user): View
    {
        $this->guard($request, $user);

        return view('auth.accept-invitation', ['user' => $user, 'action' => $request->fullUrl()]);
    }

    public function store(AcceptInvitationRequest $request, User $user): RedirectResponse
    {
        $this->guard($request, $user);

        $user->forceFill([
            'password' => Hash::make((string) $request->validated('password')),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('app.dashboard')->with('toast', __('staff.welcome', ['name' => $user->name]));
    }

    private function guard(Request $request, User $user): void
    {
        abort_unless($user->isStaff() && $user->is_active, 404);
        abort_unless(hash_equals(InviteStaff::fingerprint($user), (string) $request->query('h')), 403, __('staff.invite_used'));
    }
}
