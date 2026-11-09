<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

/** A required reason, for rejecting a transfer proof or reversing a payment. */
class PaymentReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('payment');
        $ability = $this->routeIs('app.payments.reverse') ? 'reverse' : 'approve';

        return $payment instanceof Payment && (bool) $this->user()?->can($ability, $payment);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:1000']];
    }
}
