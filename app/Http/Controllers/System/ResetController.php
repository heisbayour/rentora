<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Actions\System\ResetOrganizationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\ResetRequest;
use App\Models\Organization;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Clean-slate reset for handover (/system/reset). Developer only: a signed-in
 * owner plus ?key=RENTORA_INSTALL_KEY, exactly like /system/upgrade, and not
 * linked from the console.
 */
class ResetController extends Controller
{
    public function show(Request $request, ResetOrganizationData $reset): View
    {
        $summary = $request->session()->get('reset.summary');

        return view('system.reset', [
            'organization' => $this->organization($request),
            'counts' => $reset->counts($this->organization($request)),
            'key' => (string) $request->query('key'),
            'summary' => is_array($summary) ? $summary : null,
        ]);
    }

    public function run(ResetRequest $request, ResetOrganizationData $reset, ActivityLogger $logger): RedirectResponse
    {
        $organization = $this->organization($request);
        $newOwner = $request->filled('new_owner_email')
            ? ['name' => (string) $request->validated('new_owner_name'), 'email' => (string) $request->validated('new_owner_email')]
            : null;

        $result = $reset->handle($organization, $newOwner);

        $logger->log('system.reset', $organization, [
            'deleted' => $result['counts'],
            'new_owner' => $result['new_owner']['email'] ?? null,
        ]);

        // Shown once: the temporary password is never stored in plain text.
        return redirect()->route('system.reset', ['key' => $request->input('key')])
            ->with('reset.summary', $result)
            ->with('toast', __('reset.done'));
    }

    private function organization(Request $request): Organization
    {
        return Organization::query()->findOrFail($request->user()?->organization_id);
    }
}
