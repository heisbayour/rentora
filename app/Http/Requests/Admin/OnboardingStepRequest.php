<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Onboarding\OnboardingStep;
use App\Services\Onboarding;
use Illuminate\Foundation\Http\FormRequest;

/** Validates whichever first-run wizard step is being submitted, using that step's rules. */
class OnboardingStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->boolean('skip') && ($step = $this->step()) !== null) {
            $this->replace($step->prepare($this->input()));
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->boolean('skip')) {
            return [];
        }

        return $this->step()?->rules() ?? [];
    }

    private function step(): ?OnboardingStep
    {
        return app(Onboarding::class)->step((string) $this->route('step'));
    }
}
