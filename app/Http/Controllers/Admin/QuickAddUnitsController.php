<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Units\QuickAddUnits;
use App\Enums\RentFrequency;
use App\Enums\UnitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuickAddUnitsRequest;
use App\Models\Property;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Table form to add up to 50 units at once (spec 8.3). */
class QuickAddUnitsController extends Controller
{
    public function create(Property $property): View
    {
        $this->authorize('create', Unit::class);
        $this->authorize('update', $property);

        return view('admin.units.quick-add', [
            'property' => $property,
            'types' => UnitType::options(),
            'frequencies' => RentFrequency::options(),
            'rows' => 10,
            'max' => QuickAddUnits::MAX_ROWS,
            'currencySymbol' => Money::forOrganization(0)->symbol(),
        ]);
    }

    public function store(QuickAddUnitsRequest $request, Property $property, QuickAddUnits $quickAdd): RedirectResponse
    {
        $this->authorize('update', $property);

        /** @var list<array{label: string, type: string, rent_amount: int|string, rent_frequency: string}> $rows */
        $rows = $request->validated('units');
        $count = $quickAdd->handle($property, $rows);

        return redirect()->route('app.properties.show', $property)->with('toast', trans_choice('units.quick_add.created', $count, ['count' => $count]));
    }
}
