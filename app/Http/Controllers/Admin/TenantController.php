<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Tenants\DeleteTenant;
use App\Actions\Tenants\InviteTenant;
use App\Actions\Tenants\SaveTenant;
use App\Enums\DocumentVisibility;
use App\Enums\TenantStatus;
use App\Http\Controllers\Concerns\SortsLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantRequest;
use App\Models\ActivityLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TenantController extends Controller
{
    use SortsLists;

    /** Tabs available on the profile. Invoices, payments, maintenance and messages join in later phases. */
    private const TABS = ['overview', 'leases', 'invoices', 'payments', 'documents', 'activity'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Tenant::class);

        $search = trim((string) $request->query('q', ''));
        $status = TenantStatus::tryFrom((string) $request->query('status'));

        $query = Tenant::query()->with('activeLease.unit.property')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status->value));
        [$query, $sort, $direction] = $this->applySort($query, $request, ['name' => 'full_name', 'status' => 'status', 'created' => 'created_at'], 'name');

        return view('admin.tenants.index', [
            'tenants' => $query->paginate(config('rentora.per_page'))->withQueryString(),
            'search' => $search,
            'status' => $status,
            'statuses' => TenantStatus::cases(),
            'sort' => $sort,
            'direction' => $direction,
            'canManage' => $request->user()->can('create', Tenant::class),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Tenant::class);

        return view('admin.tenants.form', ['tenant' => new Tenant(['notification_prefs' => Tenant::DEFAULT_PREFS])]);
    }

    public function store(TenantRequest $request, SaveTenant $save): RedirectResponse
    {
        $tenant = $save->handle(null, $request->tenantData());

        return redirect()->route('app.tenants.show', $tenant)->with('toast', __('tenants.created', ['name' => $tenant->full_name]));
    }

    public function show(Request $request, Tenant $tenant): View
    {
        $this->authorize('view', $tenant);

        $canManage = $request->user()->can('viewDetails', $tenant);
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'overview';
        if (! $canManage) {
            $tab = 'overview';
        }
        $tenant->load(['activeLease.unit.property', 'user']);

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'tab' => $tab,
            'tabs' => $canManage ? self::TABS : ['overview'],
            'canManage' => $canManage,
            'leases' => $tab === 'leases' ? $tenant->leases()->with('unit.property')->latest('start_date')->get() : collect(),
            'documents' => $tab === 'documents' ? $tenant->documents()->get() : collect(),
            'invoices' => $tab === 'invoices' ? $tenant->invoices()->with(['tenant', 'unit'])->latest('due_date')->get() : collect(),
            'outstanding' => $canManage ? $tenant->outstanding() : null,
            'payments' => $tab === 'payments' ? $tenant->payments()->with('tenant')->latest('id')->get() : collect(),
            'activity' => $tab === 'activity' ? $this->activity($tenant) : collect(),
            'visibilities' => DocumentVisibility::options(),
            'whatsappPhone' => self::internationalPhone($tenant->phone),
        ]);
    }

    public function edit(Tenant $tenant): View
    {
        $this->authorize('update', $tenant);

        return view('admin.tenants.form', ['tenant' => $tenant]);
    }

    public function update(TenantRequest $request, Tenant $tenant, SaveTenant $save): RedirectResponse
    {
        $save->handle($tenant, $request->tenantData());

        return redirect()->route('app.tenants.show', $tenant)->with('toast', __('tenants.updated', ['name' => $tenant->full_name]));
    }

    public function destroy(Tenant $tenant, DeleteTenant $delete): RedirectResponse
    {
        $this->authorize('delete', $tenant);

        $delete->handle($tenant);

        return redirect()->route('app.tenants.index')->with('toast', __('tenants.deleted', ['name' => $tenant->full_name]));
    }

    public function invite(Tenant $tenant, InviteTenant $invite): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $invite->handle($tenant);

        return back()->with('toast', __('tenants.invite_sent', ['name' => $tenant->full_name]));
    }

    /** @return Collection<int, ActivityLog> */
    private function activity(Tenant $tenant)
    {
        $leaseIds = $tenant->leases()->pluck('id');

        return ActivityLog::query()->with('user')
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $w) => $w->where('subject_type', 'tenant')->where('subject_id', $tenant->id))
                ->orWhere(fn (Builder $w) => $w->where('subject_type', 'lease')->whereIn('subject_id', $leaseIds)))
            ->latest('id')->limit(100)->get();
    }

    /** Digits only, Nigerian local numbers converted to 234..., for wa.me links. Phase 8 adds the full normalizer. */
    private static function internationalPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($digits, '0') ? config('rentora.defaults.phone_country_code').substr($digits, 1) : $digits;
    }
}
