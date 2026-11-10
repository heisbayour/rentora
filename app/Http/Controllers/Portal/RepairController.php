<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceCommentRequest;
use App\Http\Requests\MaintenanceSubmissionRequest;
use App\Http\Requests\Portal\RateRepairRequest;
use App\Models\MaintenanceRequest;
use App\Services\Maintenance\MaintenanceWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Tenant repairs (spec 8.7 and 8.13): only their own requests, never internal notes. */
class RepairController extends Controller
{
    public function __construct(private readonly MaintenanceWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $tenant = $request->user()->tenant ?? abort(404);

        return view('portal.repairs.index', [
            'requests' => MaintenanceRequest::query()->with('unit')->where('tenant_id', $tenant->id)->latest('id')->paginate(config('rentora.per_page')),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', MaintenanceRequest::class);

        return view('portal.repairs.create', [
            'categories' => MaintenanceCategory::cases(),
            'priorities' => MaintenancePriority::options(),
        ]);
    }

    public function store(MaintenanceSubmissionRequest $request): RedirectResponse
    {
        $tenant = $request->user()->tenant ?? abort(404);
        $unit = $tenant->activeLease?->unit;
        if ($unit === null) {
            throw ValidationException::withMessages(['category' => __('maintenance.errors.no_active_lease')]);
        }

        $maintenance = $this->workflow->create($unit, $tenant, $request->user(), $request->safe()->only([
            'category', 'title', 'description', 'priority', 'preferred_time', 'access_instructions',
        ]), array_values((array) $request->file('photos', [])));

        return redirect()->route('portal.repairs.show', $maintenance)->with('toast', __('maintenance.portal.submitted', ['number' => $maintenance->number]));
    }

    public function show(MaintenanceRequest $maintenance): View
    {
        $this->authorize('view', $maintenance);

        $maintenance->load(['unit.property', 'attachments']);

        return view('portal.repairs.show', [
            'request' => $maintenance,
            'updates' => $maintenance->updates()->visibleToTenant()->with(['user', 'vendor'])->get(),
        ]);
    }

    public function cancel(Request $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        $this->authorize('cancel', $maintenance);

        $this->workflow->changeStatus($maintenance, MaintenanceStatus::Cancelled, $request->user());

        return redirect()->route('portal.repairs.show', $maintenance)->with('toast', __('maintenance.portal.cancelled'));
    }

    public function rate(RateRepairRequest $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        $this->workflow->confirmAndRate($maintenance, $request->user(), (int) $request->validated('rating'), $request->validated('feedback'));

        return redirect()->route('portal.repairs.show', $maintenance)->with('toast', __('maintenance.portal.rated'));
    }

    public function comment(MaintenanceCommentRequest $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        $this->workflow->comment($maintenance, $request->user(), (string) $request->validated('body'));
        $this->workflow->addPhotos($maintenance, array_values((array) $request->file('photos', [])), $request->user());

        return redirect()->route('portal.repairs.show', $maintenance)->with('toast', __('maintenance.comment_added'));
    }
}
