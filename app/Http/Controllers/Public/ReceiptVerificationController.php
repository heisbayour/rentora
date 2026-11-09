<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\NameMask;
use Illuminate\Http\Response;

/**
 * /r/{code}: anyone holding a receipt can check it is genuine (spec 8.6).
 * Shows only the masked tenant name; unknown codes get a plain "not found".
 */
class ReceiptVerificationController extends Controller
{
    public function __invoke(string $code): Response
    {
        $payment = Payment::query()->withoutGlobalScopes()->with(['tenant', 'organization'])
            ->where('verification_code', strtoupper($code))
            ->whereNotNull('receipt_number')
            ->first();

        if ($payment === null) {
            return response()->view('public.receipt-verify', ['payment' => null], 404);
        }

        return response()->view('public.receipt-verify', [
            'payment' => $payment,
            'valid' => $payment->status === PaymentStatus::Successful,
            'maskedName' => NameMask::mask($payment->tenant->full_name),
        ]);
    }
}
