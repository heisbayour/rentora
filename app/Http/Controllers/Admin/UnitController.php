<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Units\DeleteUnit;
use App\Actions\Units\SaveUnit;
use App\Enums\RentFrequency;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Http\Controllers\Concerns\SortsLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UnitRequest;
use App\Models\Property;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{
    use SortsLists;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Unit::class);

        $search = trim((string) $request->query('q', ''));
        $status = UnitStatus::tryFrom((string) $request->query('status'));
        $propertyId = $request->integer('property') ?: null;

        $query = Unit::query()->with('property')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('label', 'like', "%{$search}%")
                ->orWhereHas('property', fn ($p) => $p->where('name', 'like', "%{$search}%"))))
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            ->when($propertyId !== null, fn ($q) => $q->where('property_id', $propertyId));
        [$query, $sort, $direction] = $this->applySort($query, $request, ['label' => 'label', 'rent' => 'rent_amount', 'status' => 'status', 'created' => 'created_at'], 'label');

        return view('admin.units.index', [
            'units' => $query->paginate(config('rentora.per_page'))->withQueryString(),
            'search' => $search,
            'status' => $status,
            'statuses' => UnitStatus::cases(),
            'propertyId' => $propertyId,
            'properties' => Property::query()->orderBy('name')->pluck('name', 'id')->all(),
            'sort' => $sort,
            'direction' => $direction,
            'canManage' => $request->user()->can('create', Unit::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Unit::class);

        $unit = new Unit([
            'property_id' => $request->integer('property') ?: null,
            'type' => UnitType::Flat,
            'rent_frequency' => RentFrequency::Annual,
            'status' => UnitStatus::Vacant,
            'bedrooms' => 1,
            'bathrooms' => 1,
        ]);

        return view('admin.units.form', $this->formData($unit));
    }

    public function store(UnitRequest $request, SaveUnit $save): RedirectResponse
    {
        $unit = $save->handle(null, $request->validated());

        return redirect()->route('app.units.show', $unit)->with('toast', __('units.created', ['label' => $unit->label]));
    }

    public function show(Request $request, Unit $unit): View
    {
        $this->authorize('view', $unit);

        $unit->load(['property', 'photos']);

        return view('admin.units.show', [
            'unit' => $unit,
            'canManage' => $request->user()->can('update', $unit),
        ]);
    }

    public function edit(Unit $unit): View
    {
        $this->authorize('update', $unit);

        return view('admin.units.form', $this->formData($unit));
    }

    public function update(UnitRequest $request, Unit $unit, SaveUnit $save): RedirectResponse
    {
        $save->handle($unit, $request->validated());

        return redirect()->route('app.units.show', $unit)->with('toast', __('units.updated', ['label' => $unit->label]));
    }

    public function destroy(Unit $unit, DeleteUnit $delete): RedirectResponse
    {
        $this->authorize('delete', $unit);

        $delete->handle($unit);

        return redirect()->route('app.properties.show', $unit->property_id)->with('toast', __('units.deleted', ['label' => $unit->label]));
    }

    /** @return array<string, mixed> */
    private function formData(Unit $unit): array
    {
        return [
            'unit' => $unit,
            'properties' => Property::query()->orderBy('name')->pluck('name', 'id')->all(),
            'types' => UnitType::options(),
            'frequencies' => RentFrequency::options(),
            'statuses' => UnitStatus::options(),
            'currencySymbol' => Money::forOrganization(0)->symbol(),
        ];
    }
}
