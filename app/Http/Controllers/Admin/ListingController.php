<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Which vacant or reserved units appear on the public website (spec 8.11). */
class ListingController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('create', Unit::class);

        $listedOnly = $request->query('show') === 'listed';
        $units = Unit::query()->with(['property', 'photos'])
            ->whereIn('status', [UnitStatus::Vacant->value, UnitStatus::Reserved->value])
            ->when($listedOnly, fn ($q) => $q->where('is_listed', true))
            ->orderByDesc('is_listed')->orderBy('label')
            ->paginate(config('rentora.per_page'))->withQueryString();

        return view('admin.listings.index', ['units' => $units, 'listedOnly' => $listedOnly]);
    }

    public function toggle(Unit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);

        if (! $unit->is_listed && ! $unit->status->isListable()) {
            throw ValidationException::withMessages(['unit' => __('listings.not_listable')]);
        }
        $unit->forceFill(['is_listed' => ! $unit->is_listed])->save();

        return back()->with('toast', __($unit->is_listed ? 'listings.listed' : 'listings.unlisted', ['label' => $unit->label]));
    }
}
