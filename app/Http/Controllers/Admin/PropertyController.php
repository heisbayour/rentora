<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Properties\DeleteProperty;
use App\Actions\Properties\SaveProperty;
use App\Enums\DocumentVisibility;
use App\Enums\PropertyType;
use App\Http\Controllers\Concerns\SortsLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PropertyRequest;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyController extends Controller
{
    use SortsLists;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Property::class);

        $search = trim((string) $request->query('q', ''));
        $type = PropertyType::tryFrom((string) $request->query('type'));

        $query = Property::query()->withOccupancy()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")))
            ->when($type !== null, fn ($q) => $q->where('type', $type->value));
        [$query, $sort, $direction] = $this->applySort($query, $request, ['name' => 'name', 'code' => 'code', 'city' => 'city', 'created' => 'created_at'], 'name');

        return view('admin.properties.index', [
            'properties' => $query->paginate(config('rentora.per_page'))->withQueryString(),
            'search' => $search,
            'type' => $type,
            'types' => PropertyType::cases(),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Property::class);

        return view('admin.properties.form', ['property' => new Property(['country' => config('rentora.defaults.country'), 'type' => PropertyType::Residential])]);
    }

    public function store(PropertyRequest $request, SaveProperty $save): RedirectResponse
    {
        $property = $save->handle(null, $request->propertyData(), $request->file('cover_image'));

        return redirect()->route('app.properties.show', $property)->with('toast', __('properties.created', ['name' => $property->name]));
    }

    public function show(Request $request, Property $property): View
    {
        $this->authorize('view', $property);

        $property->loadCount([
            'units',
            'units as occupied_units_count' => fn ($q) => $q->where('status', 'occupied'),
        ]);
        $tab = in_array($request->query('tab'), ['units', 'documents'], true) ? (string) $request->query('tab') : 'units';

        return view('admin.properties.show', [
            'property' => $property,
            'tab' => $tab,
            'units' => $property->units()->orderBy('label')->get(),
            'documents' => $property->documents()->get(),
            'visibilities' => DocumentVisibility::options(),
        ]);
    }

    public function edit(Property $property): View
    {
        $this->authorize('update', $property);

        return view('admin.properties.form', ['property' => $property]);
    }

    public function update(PropertyRequest $request, Property $property, SaveProperty $save): RedirectResponse
    {
        $save->handle($property, $request->propertyData(), $request->file('cover_image'), $request->boolean('remove_cover'));

        return redirect()->route('app.properties.show', $property)->with('toast', __('properties.updated', ['name' => $property->name]));
    }

    public function destroy(Property $property, DeleteProperty $delete): RedirectResponse
    {
        $this->authorize('delete', $property);

        $delete->handle($property);

        return redirect()->route('app.properties.index')->with('toast', __('properties.deleted', ['name' => $property->name]));
    }
}
