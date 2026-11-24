<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\Role;
use App\Enums\UnitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\InquiryRequest;
use App\Models\Inquiry;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Notifications\InquiryReceived;
use App\Services\Messaging\Notifier;
use App\Services\SettingsService;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public website (spec 8.11): home, listings with filters, unit page and
 * inquiry form. When the owner turns the site off, "/" goes to the login page
 * and the listing pages return 404.
 */
class SiteController extends Controller
{
    public const ENABLED_KEY = 'website.enabled';

    public function __construct(
        private readonly CurrentOrganization $current,
        private readonly SettingsService $settings,
    ) {}

    public function home(): View|RedirectResponse
    {
        if (! $this->enabled()) {
            return redirect()->route('login');
        }

        return view('public.home', $this->layout() + [
            'featured' => $this->listed()->latest('updated_at')->limit(6)->get(),
        ]);
    }

    public function listings(Request $request): View
    {
        abort_unless($this->enabled(), 404);

        $filters = $request->validate([
            'property_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_rent' => ['nullable', 'numeric', 'min:0'],
        ]);

        $units = $this->listed()
            ->when($filters['property_id'] ?? null, fn (Builder $q, $id) => $q->where('property_id', (int) $id))
            ->when(in_array($filters['type'] ?? '', UnitType::values(), true), fn (Builder $q) => $q->where('type', $filters['type']))
            ->when(isset($filters['bedrooms']), fn (Builder $q) => $q->where('bedrooms', '>=', (int) $filters['bedrooms']))
            ->when(isset($filters['max_rent']), fn (Builder $q) => $q->where('rent_amount', '<=', (int) round((float) $filters['max_rent'] * 100)))
            ->orderBy('rent_amount')
            ->paginate(12)
            ->withQueryString();

        return view('public.listings', $this->layout() + [
            'units' => $units,
            'filters' => $filters,
            'properties' => Property::query()->whereHas('units', fn (Builder $q) => $q->publiclyListed())->orderBy('name')->pluck('name', 'id'),
            'types' => UnitType::options(),
        ]);
    }

    public function unit(Unit $unit): View
    {
        abort_unless($this->enabled() && $this->listed()->whereKey($unit->getKey())->exists(), 404);
        $unit->load(['property', 'photos']);

        return view('public.unit', $this->layout() + ['unit' => $unit]);
    }

    public function inquire(InquiryRequest $request, Unit $unit, Notifier $notifier): RedirectResponse
    {
        abort_unless($this->enabled() && $this->listed()->whereKey($unit->getKey())->exists(), 404);

        // Honeypot: bots fill the hidden field; pretend it worked and store nothing.
        if ($request->filled('website')) {
            return back()->with('toast', __('inquiries.sent'));
        }

        $inquiry = new Inquiry([...$request->safe()->only(['name', 'email', 'phone', 'message']), 'unit_id' => $unit->getKey()]);
        $inquiry->organization_id = $unit->organization_id;
        $inquiry->ip = $request->ip();
        $inquiry->save();

        $notifier->toStaff($unit->organization_id, new InquiryReceived($inquiry, $unit->displayName()), [Role::Owner, Role::Manager]);

        return redirect()->route('public.unit', $unit)->with('toast', __('inquiries.sent'));
    }

    private function enabled(): bool
    {
        return $this->current->get() !== null && $this->settings->bool(self::ENABLED_KEY, true);
    }

    /** @return Builder<Unit> */
    private function listed(): Builder
    {
        return Unit::query()->publiclyListed()->whereHas('property')->with(['property', 'photos']);
    }

    /** @return array<string, mixed> */
    private function layout(): array
    {
        /** @var Organization $organization */
        $organization = $this->current->get();

        return [
            'organization' => $organization,
            'contact' => array_filter([$organization->address, $organization->phone, $organization->email]),
        ];
    }
}
