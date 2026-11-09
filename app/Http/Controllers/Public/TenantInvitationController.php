<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Tenants\AcceptTenantInvitation;
use App\Actions\Tenants\InviteTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptTenantInvitationRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** /invite/{tenant}: the tenant sets a password and gets portal access (spec 4.3 and 8.4). */
class TenantInvitationController extends Controller
{
    public function show(Request $request, Tenant $tenant): View
    {
        $this->guard($request, $tenant);

        return view('auth.accept-tenant-invitation', ['tenant' => $tenant, 'action' => $request->fullUrl()]);
    }

    public function store(AcceptTenantInvitationRequest $request, Tenant $tenant, AcceptTenantInvitation $accept): RedirectResponse
    {
        $this->guard($request, $tenant);

        $user = $accept->handle($tenant, $request->loginEmail(), (string) $request->validated('password'));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.home')->with('toast', __('tenants.welcome', ['name' => $tenant->full_name]));
    }

    private function guard(Request $request, Tenant $tenant): void
    {
        abort_if($tenant->hasPortalAccount(), 403, __('tenants.errors.invite_used'));
        abort_unless(hash_equals(InviteTenant::fingerprint($tenant), (string) $request->query('h')), 403, __('tenants.errors.invite_used'));
    }
}
