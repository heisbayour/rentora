<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Leases\ActivateLease;
use App\Actions\Leases\EndLease;
use App\Actions\Leases\RenewLease;
use App\Actions\Leases\TerminateLease;
use App\Enums\DocumentVisibility;
use App\Enums\LeaseStatus;
use App\Http\Controllers\Concerns\SortsLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RenewLeaseRequest;
use App\Http\Requests\Admin\TerminateLeaseRequest;
use App\Models\Lease;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaseController extends Controller
{
    use SortsLists;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lease::class);

        $search = trim((string) $request->query('q', ''));
        $status = LeaseStatus::tryFrom((string) $request->query('status'));
        $expiring = $request->query('expiring') === '60';

        $query = Lease::query()->with(['tenant', 'unit.property'])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereHas('tenant', fn (Builder $t) => $t->where('full_name', 'like', "%{$search}%"))
                ->orWhereHas('unit', fn (Builder $u) => $u->where('label', 'like', "%{$search}%"))))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status->value))
            ->when($expiring, fn (Builder $q) => $q->active()->whereBetween('end_date', [today(), today()->addDays(60)]));
        [$query, $sort, $direction] = $this->applySort($query, $request, ['start' => 'start_date', 'end' => 'end_date', 'rent' => 'rent_amount'], 'end');

        return view('admin.leases.index', [
            'leases' => $query->paginate(config('rentora.per_page'))->withQueryString(),
            'search' => $search,
            'status' => $status,
            'statuses' => LeaseStatus::cases(),
            'expiring' => $expiring,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function show(Lease $lease): View
    {
        $this->authorize('view', $lease);

        $lease->load(['tenant', 'unit.property', 'renewal', 'renewalOf']);

        return view('admin.leases.show', [
            'lease' => $lease,
            'documents' => $lease->documents()->get(),
            'visibilities' => DocumentVisibility::options(),
            'renewalEnd' => $lease->isActive() ? RenewLease::defaultEndDate($lease, $lease->end_date->copy()->addDay()) : null,
            'currencySymbol' => Money::forOrganization(0)->symbol(),
        ]);
    }

    public function activate(Lease $lease, ActivateLease $activate): RedirectResponse
    {
        $this->authorize('update', $lease);

        $activate->handle($lease);

        return redirect()->route('app.leases.show', $lease)->with('toast', __('leases.activated'));
    }

    public function renew(RenewLeaseRequest $request, Lease $lease, RenewLease $renew): RedirectResponse
    {
        $renewal = $renew->handle($lease, [
            'end_date' => (string) $request->validated('end_date'),
            'rent_amount' => (int) $request->validated('rent_amount'),
            'service_charge_amount' => (int) $request->validated('service_charge_amount'),
        ]);

        return redirect()->route('app.leases.show', $renewal)->with('toast', __('leases.renewed', ['date' => $renewal->start_date->format('j M Y')]));
    }

    public function end(Lease $lease, EndLease $end): RedirectResponse
    {
        $this->authorize('update', $lease);

        $renewal = $end->handle($lease);

        return redirect()->route('app.leases.show', $renewal ?? $lease)
            ->with('toast', $renewal !== null ? __('leases.ended_and_renewed') : __('leases.ended'));
    }

    public function terminate(TerminateLeaseRequest $request, Lease $lease, TerminateLease $terminate): RedirectResponse
    {
        $terminate->handle($lease, (string) $request->validated('reason'));
        // The optional final invoice ($request->boolean('final_invoice')) is issued by billing from Phase 5.

        return redirect()->route('app.leases.show', $lease)->with('toast', __('leases.terminated'));
    }
}
