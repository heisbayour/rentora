<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\VendorJobUpdateRequest;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceRequest;
use App\Models\Vendor;
use App\Services\Maintenance\MaintenanceWorkflow;
use App\Services\SettingsService;
use App\Support\CurrentOrganization;
use App\Support\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * /vendor/job/{request}: the vendor's view of one job through a signed link
 * (spec 8.7). Valid only while the link's vendor is still assigned.
 */
class VendorJobController extends Controller
{
    public function __construct(
        private readonly MaintenanceWorkflow $workflow,
        private readonly CurrentOrganization $current,
        private readonly SettingsService $settings,
    ) {}

    public function show(Request $request, int $job): View
    {
        [$job, $vendor] = $this->resolve($request, $job);
        $job->load(['unit.property', 'tenant', 'attachments']);

        return view('public.vendor-job', [
            'job' => $job,
            'vendor' => $vendor,
            'updates' => $job->updates()->visibleToTenant()->with(['user', 'vendor'])->get(),
            'showTenantContact' => $this->settings->bool('maintenance.vendor_sees_tenant_contact'),
            'action' => $request->fullUrl(),
            'canUpdate' => in_array($job->status, [MaintenanceStatus::Acknowledged, MaintenanceStatus::Scheduled, MaintenanceStatus::InProgress, MaintenanceStatus::OnHold], true),
        ]);
    }

    public function update(VendorJobUpdateRequest $request, int $job): RedirectResponse
    {
        [$job, $vendor] = $this->resolve($request, $job);

        if ($request->filled('body')) {
            $this->workflow->comment($job, $vendor, (string) $request->validated('body'));
        }
        $this->workflow->addPhotos($job, array_values((array) $request->file('photos', [])), $vendor);
        if ($request->filled('status') && $job->status->value !== $request->validated('status')) {
            $this->workflow->changeStatus($job, MaintenanceStatus::from((string) $request->validated('status')), $vendor);
        }

        return redirect()->to($request->fullUrl())->with('toast', __('maintenance.vendor.saved'));
    }

    public function photo(Request $request, int $job, MaintenanceAttachment $attachment): Response
    {
        [$job] = $this->resolve($request, $job);
        abort_unless($attachment->maintenance_request_id === $job->id, 404);

        return Storage::disk(PrivateFiles::DISK)->response($attachment->path, null, ['Cache-Control' => 'private, no-store']);
    }

    /** @return array{0: MaintenanceRequest, 1: Vendor} */
    private function resolve(Request $request, int $id): array
    {
        $job = MaintenanceRequest::query()->withoutGlobalScopes()->with('organization')->findOrFail($id);
        abort_unless($job->vendor_id !== null && (string) $job->vendor_id === (string) $request->query('vendor'), 403, __('maintenance.vendor.link_invalid'));
        $this->current->set($job->organization);

        return [$job, Vendor::query()->findOrFail($job->vendor_id)];
    }
}
