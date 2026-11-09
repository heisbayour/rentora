<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Lease;
use App\Services\LeaseWizard;
use Illuminate\Foundation\Http\FormRequest;

/** Validates the submitted step of the lease wizard with that step's rules. */
class LeaseWizardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Lease::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return app(LeaseWizard::class)->rules((string) $this->route('step'), $this->user()?->organization_id);
    }
}
