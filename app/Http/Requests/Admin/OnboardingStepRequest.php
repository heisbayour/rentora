<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\Onboarding;
use Illuminate\Foundation\Http\FormRequest;

/** Validates whichever first-run wizard step is being submitted, using that step's rules. */
class OnboardingStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->boolean('skip')) {
            return [];
        }
        $step = app(Onboarding::class)->step((string) $this->route('step'));

        return $step?->rules() ?? [];
    }
}
