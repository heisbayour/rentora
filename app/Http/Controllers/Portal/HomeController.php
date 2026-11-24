<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tenant portal home (spec 8.13): balance, next due date, lease, open repairs, latest notices. */
class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tenant = $request->user()->tenant;
        if ($tenant === null) {
            // A tenant login not linked to a tenant record yet.
            return view('portal.unlinked');
        }
        $tenant->load('activeLease.unit.property');

        return view('portal.home', [
            'tenant' => $tenant,
            'lease' => $tenant->activeLease,
            'outstanding' => $tenant->outstanding(),
            'credit' => $tenant->creditBalance(),
            'nextInvoice' => $tenant->invoices()->open()->orderBy('due_date')->first(),
            'repairs' => MaintenanceRequest::query()->where('tenant_id', $tenant->id)
                ->whereIn('status', MaintenanceStatus::openValues())->latest('id')->limit(3)->get(),
            'notices' => $tenant->announcements()->latest('announcements.id')->limit(3)->get(),
        ]);
    }
}
