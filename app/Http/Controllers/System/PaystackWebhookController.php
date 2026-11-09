<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentVerifier;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paystack webhook (spec 8.6 step 6). Off unless RENTORA_PAYSTACK_WEBHOOKS=true
 * (InfinityFree blocks non-browser requests). The x-paystack-signature HMAC
 * SHA512 of the raw body must match, then the usual PaymentVerifier confirms
 * the transaction with the API.
 */
class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentVerifier $verifier, CurrentOrganization $current): JsonResponse
    {
        abort_unless((bool) config('rentora.paystack.webhooks'), 404);

        $reference = (string) $request->input('data.reference', '');
        $payment = $reference !== '' ? Payment::query()->withoutGlobalScopes()->with('organization')->where('reference', $reference)->first() : null;

        // The secret key is per organization, so the payment is looked up before the signature is checked.
        if ($payment !== null) {
            $current->set($payment->organization);
        }
        if (! app(PaymentGateway::class)->validWebhookSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if ($payment !== null && $request->input('event') === 'charge.success') {
            $verifier->verify($payment);
        }

        return response()->json(['received' => true]);
    }
}
