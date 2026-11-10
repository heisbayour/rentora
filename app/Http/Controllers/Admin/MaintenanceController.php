<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MaintenanceAssignRequest;
use App\Http\Requests\Admin\MaintenanceStatusRequest;
use App\Http\Requests\MaintenanceCommentRequest;
use App\Http\Requests\MaintenanceSubmissionRequest;
use App\Models\MaintenanceRequest;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Maintenance\MaintenanceWorkflow;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Staff side of repairs (spec 8.7): table and kanban views, thread, status, assignment. */
class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MaintenanceRequest::class);

        $view = $request->query('view') === 'kanban' ? 'kanban' : 'table';
        $search = trim((string) $request->query('q', ''));
        $status = MaintenanceStatus::tryFrom((string) $request->query('status'));
        $priority = MaintenancePriority::tryFrom((string) $request->query('priority'));
        $mine = $request->query('mine') === '1';

        $query = MaintenanceRequest::query()->with(['unit.property', 'tenant', 'vendor', 'assignee'])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status->value))
            ->when($priority !== null, fn (Builder $q) => $q->where('priority', $priority->value))
            ->when($mine, fn (Builder $q) => $q->where('assigned_user_id', $request->user()->id))
            // Emergencies first, then newest.
            ->orderByRaw("CASE WHEN priority = 'emergency' THEN 0 ELSE 1 END")
            ->latest('id');

        return view('admin.maintenance.index', [
            'view' => $view,
            'requests' => $view === 'table' ? $query->paginate(config('rentora.per_page'))->withQueryString() : null,
            'columns' => $view === 'kanban' ? $query->whereIn('status', MaintenanceStatus::openValues())->get()->groupBy(fn ($r) => $r->status->value) : collect(),
            'search' => $search,
            'status' => $status,
            'priority' => $priority,
            'mine' => $mine,
            'statuses' => MaintenanceStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', MaintenanceRequest::class);

        return view('admin.maintenance.create', [
            'units' => Unit::query()->with('property')->orderBy('property_id')->orderBy('label')->get()
                ->mapWithKeys(fn (Unit $u) => [$u->id => $u->property->name.' · '.$u->label])->all(),
            'tenants' => Tenant::query()->orderBy('full_name')->pluck('full_name', 'id')->all(),
            'unitId' => $request->integer('unit') ?: null,
            'categories' => MaintenanceCategory::options(),
            'priorities' => MaintenancePriority::options(),
        ]);
    }

    public function store(MaintenanceSubmissionRequest $request): RedirectResponse
    {
        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        $tenant = $request->filled('tenant_id')
            ? Tenant::query()->findOrFail($request->integer('tenant_id'))
            : $unit->activeLease?->tenant;

        $maintenance = $this->workflow->create($unit, $tenant, $request->user(), $request->safe()->only([
            'category', 'title', 'description', 'priority', 'preferred_time', 'access_instructions',
        ]), array_values((array) $request->file('photos', [])));

        return redirect()->route('app.maintenance.show', $maintenance)->with('toast', __('maintenance.created', ['number' => $maintenance->number]));
    }

    public function show(MaintenanceRequest $maintenance): View
    {
        $this->authorize('view', $maintenance);

        $maintenance->load(['unit.property', 'tenant', 'vendor', 'assignee', 'reporter', 'attachments', 'updates.user', 'updates.vendor', 'expense']);

        return view('admin.maintenance.show', [
            'request' => $maintenance,
            'nextStatuses' => $maintenance->status->transitions(),
            'staff' => User::query()->staff()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get()
                ->mapWithKeys(fn (Vendor $v) => [$v->id => $v->name.($v->trade ? ' ('.$v->trade.')' : '')])->all(),
            'vendorLink' => $maintenance->vendor_id ? MaintenanceWorkflow::vendorLink($maintenance) : null,
            'currencySymbol' => Money::forOrganization(0)->symbol(),
        ]);
    }

    public function status(MaintenanceStatusRequest $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        $this->workflow->changeStatus($maintenance, MaintenanceStatus::from((string) $request->validated('status')), $request->user(), $request->validated('note'), [
            'scheduled_for' => $request->filled('scheduled_for') ? Carbon::parse((string) $request->validated('scheduled_for')) : null,
            'cost_amount' => $request->filled('cost_amount') ? (int) $request->validated('cost_amount') : null,
            'record_expense' => $request->boolean('record_expense'),
        ]);

        return redirect()->route('app.maintenance.show', $maintenance)->with('toast', __('maintenance.status_updated'));
    }

    public function assign(MaintenanceAssignRequest $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        $this->workflow->assign($maintenance, $request->user(),
            $request->filled('assigned_user_id') ? $request->integer('assigned_user_id') : null,
            $request->filled('vendor_id') ? $request->integer('vendor_id') : null);

        return redirect()->route('app.maintenance.show', $maintenance)->with('toast', __('maintenance.assignment_saved'));
    }

    public function comment(MaintenanceCommentRequest $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        $this->workflow->comment($maintenance, $request->user(), (string) $request->validated('body'), $request->boolean('is_internal'));
        $this->workflow->addPhotos($maintenance, array_values((array) $request->file('photos', [])), $request->user());

        return redirect()->route('app.maintenance.show', $maintenance)->with('toast', __('maintenance.comment_added'));
    }
}
