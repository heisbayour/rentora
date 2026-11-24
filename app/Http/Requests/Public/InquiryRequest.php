<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class InquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:191'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:2000'],
            // Honeypot, hidden from people.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }
}
