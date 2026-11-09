<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Payment;
use App\Support\AmountInWords;
use App\Support\Pdf;
use App\Support\QrCode;
use Symfony\Component\HttpFoundation\Response;

/** Receipt PDF (spec 8.6): branding, amount in figures and words, allocations, verification QR. */
class ReceiptRenderer
{
    public function __construct(
        private readonly Pdf $pdf,
        private readonly QrCode $qr,
    ) {}

    public function download(Payment $payment): Response
    {
        abort_if($payment->receipt_number === null, 404);
        $payment->loadMissing(['tenant', 'lease.unit.property', 'allocations.invoice']);
        $verifyUrl = route('receipts.verify', $payment->verification_code);

        return $this->pdf->download('pdf.receipt', [
            'payment' => $payment,
            'amountInWords' => AmountInWords::money($payment->money()),
            'qr' => $this->qr->dataUri($verifyUrl),
            'verifyUrl' => $verifyUrl,
        ], $payment->receipt_number.'.pdf');
    }
}
