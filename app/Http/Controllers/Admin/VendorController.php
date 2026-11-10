<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VendorRequest;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Vendor::class);

        $search = trim((string) $request->query('q', ''));
        $vendors = Vendor::query()->withCount(['requests as open_jobs_count' => fn (Builder $q) => $q->whereIn('status', MaintenanceStatus::openValues())])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$search}%")->orWhere('trade', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(config('rentora.per_page'))->withQueryString();

        return view('admin.vendors.index', [
            'vendors' => $vendors,
            'search' => $search,
            'canManage' => $request->user()->can('create', Vendor::class),
        ]);
    }

    public function store(VendorRequest $request): RedirectResponse
    {
        $vendor = Vendor::query()->create($request->validated());

        return redirect()->route('app.vendors.index')->with('toast', __('vendors.created', ['name' => $vendor->name]));
    }

    public function update(VendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update($request->validated());

        return redirect()->route('app.vendors.index')->with('toast', __('vendors.updated', ['name' => $vendor->name]));
    }
}
