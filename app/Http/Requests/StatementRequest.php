<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/** Date range for a tenant statement; defaults to this year so far. */
class StatementRequest extends FormRequest
{
    /** Access is checked by the controller against the tenant. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    public function from(): Carbon
    {
        return $this->filled('from') ? Carbon::parse((string) $this->input('from')) : today()->startOfYear();
    }

    public function to(): Carbon
    {
        return $this->filled('to') ? Carbon::parse((string) $this->input('to')) : today();
    }
}
