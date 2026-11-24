<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\AccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tenant account (spec 8.13): profile and password. Contact details on the tenant record follow the login. */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('portal.account', ['user' => $request->user(), 'tenant' => $request->user()->tenant]);
    }

    public function update(AccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill(['name' => $request->validated('name'), 'phone' => $request->validated('phone')])->save();
        $user->tenant?->forceFill(['phone' => (string) $request->validated('phone')])->save();

        return redirect()->route('portal.account')->with('toast', __('profile.saved'));
    }
}
