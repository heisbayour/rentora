<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Models\MaintenanceRequest;
use Illuminate\Foundation\Http\FormRequest;

class RateRepairRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('maintenance');

        return $request instanceof MaintenanceRequest && (bool) $this->user()?->can('rate', $request);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
