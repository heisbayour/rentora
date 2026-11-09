<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Actions\Properties\SaveProperty;
use App\Enums\PropertyType;
use App\Models\Property;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Wizard step 2: the first property. Remembered so the units step can add to it. */
class PropertyStep implements OnboardingStep
{
    public const PROPERTY_KEY = 'onboarding.property_id';

    public function __construct(
        private readonly SaveProperty $save,
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'property';
    }

    public function title(): string
    {
        return __('onboarding.steps.property');
    }

    public function skippable(): bool
    {
        return false;
    }

    public function view(): string
    {
        return 'admin.onboarding.property';
    }

    public function data(): array
    {
        $existing = Property::query()->find($this->settings->int(self::PROPERTY_KEY));

        return [
            'property' => $existing ?? new Property(['country' => config('rentora.defaults.country'), 'type' => PropertyType::Residential]),
            'types' => PropertyType::options(),
        ];
    }

    public function prepare(array $input): array
    {
        $input['code'] = Str::upper(trim((string) ($input['code'] ?? '')));

        return $input;
    }

    public function rules(): array
    {
        $existing = $this->settings->int(self::PROPERTY_KEY) ?: null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('properties', 'code')->where('organization_id', request()->user()?->organization_id)->ignore($existing)],
            'type' => ['required', Rule::enum(PropertyType::class)],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
        ];
    }

    public function handle(Request $request): void
    {
        $existing = Property::query()->find($this->settings->int(self::PROPERTY_KEY));
        $property = $this->save->handle($existing, $request->only(array_keys($this->rules())) + ['is_listed' => false]);
        $this->settings->set(self::PROPERTY_KEY, (string) $property->getKey());
    }
}
