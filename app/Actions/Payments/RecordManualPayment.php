<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\PaymentCodes;
use App\Support\PrivateFiles;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;

/** Cash, POS, cheque or transfer recorded by staff: successful at once (spec 8.6). */
class RecordManualPayment
{
    public function __construct(
        private readonly PaymentCodes $codes,
        private readonly PrivateFiles $files,
        private readonly CompletePayment $complete,
    ) {}

    /**
     * @param  list<int>  $invoiceIds
     */
    public function handle(Tenant $tenant, User $staff, PaymentMethod $method, int $amount, CarbonInterface $paidAt, ?string $note, ?UploadedFile $proof, array $invoiceIds = []): Payment
    {
        $payment = new Payment([
            'tenant_id' => $tenant->getKey(),
            'lease_id' => $tenant->activeLease?->getKey(),
            'reference' => $this->codes->reference(),
            'verification_code' => $this->codes->verificationCode(),
            'amount' => $amount,
            'method' => $method,
            'paid_at' => $paidAt,
            'recorded_by' => $staff->getKey(),
            'note' => $note,
            'proof_path' => $proof !== null ? $this->files->store($proof, 'proofs') : null,
        ]);
        $payment->organization_id = $tenant->organization_id;
        $payment->save();

        return $this->complete->handle($payment, $invoiceIds, $staff, 'payment.recorded');
    }
}
