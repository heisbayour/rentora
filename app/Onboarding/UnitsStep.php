<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Actions\Units\QuickAddUnits;
use App\Enums\RentFrequency;
use App\Enums\UnitType;
use App\Http\Requests\Admin\UnitRequest;
use App\Models\Property;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Wizard step 3: quick-add the first property's units. */
class UnitsStep implements OnboardingStep
{
    public function __construct(
        private readonly QuickAddUnits $quickAdd,
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'units';
    }

    public function title(): string
    {
        return __('onboarding.steps.units');
    }

    public function skippable(): bool
    {
        return false;
    }

    public function view(): string
    {
        return 'admin.onboarding.units';
    }

    public function data(): array
    {
        return [
            'property' => $this->property(),
            'types' => UnitType::options(),
            'frequencies' => RentFrequency::options(),
            'rows' => 5,
            'max' => QuickAddUnits::MAX_ROWS,
            'currencySymbol' => Money::forOrganization(0)->symbol(),
        ];
    }

    public function prepare(array $input): array
    {
        $rows = $input['units'] ?? [];
        $input['units'] = QuickAddUnits::filledRows(is_array($rows) ? $rows : []);

        return $input;
    }

    public function rules(): array
    {
        return [
            'units' => ['required', 'array', 'min:1', 'max:'.QuickAddUnits::MAX_ROWS],
            'units.*.label' => ['required', 'string', 'max:60', 'distinct:ignore_case',
                Rule::unique('units', 'label')->where('property_id', $this->property()->getKey())],
            'units.*.type' => ['required', Rule::enum(UnitType::class)],
            'units.*.rent_amount' => ['required', 'integer', 'min:0', 'max:'.UnitRequest::MAX_MINOR],
            'units.*.rent_frequency' => ['required', Rule::enum(RentFrequency::class)],
        ];
    }

    public function handle(Request $request): void
    {
        /** @var list<array{label: string, type: string, rent_amount: int|string, rent_frequency: string}> $rows */
        $rows = $request->input('units');
        $this->quickAdd->handle($this->property(), $rows);
    }

    private function property(): Property
    {
        return Property::query()->find($this->settings->int(PropertyStep::PROPERTY_KEY))
            ?? Property::query()->oldest('id')->firstOrFail();
    }
}
