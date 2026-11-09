<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Payments\PaymentCodes;
use App\Services\Payments\PaymentGateway;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Tenant pays online (spec 8.6 steps 1 to 3): a pending payment with reference
 * RNT-{ulid}, then a redirect to Paystack checkout.
 */
class StartOnlinePayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentCodes $codes,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  list<int>  $invoiceIds
     * @return array{payment: Payment, url: string}
     */
    public function handle(Tenant $tenant, int $amount, array $invoiceIds = []): array
    {
        $minimum = (int) config('rentora.paystack.min_amount');
        if ($amount < $minimum) {
            throw ValidationException::withMessages(['amount' => __('payments.errors.below_minimum', ['amount' => Money::forOrganization($minimum)->format()])]);
        }
        $outstanding = (int) $tenant->invoices()->open()->get()->sum(fn (Invoice $i) => $i->outstandingMinor());
        if ($amount > $outstanding && ! $this->settings->bool('billing.allow_overpayment')) {
            throw ValidationException::withMessages(['amount' => __('payments.errors.above_outstanding', ['amount' => Money::forOrganization($outstanding)->format()])]);
        }

        $payment = new Payment([
            'tenant_id' => $tenant->getKey(),
            'lease_id' => $tenant->activeLease?->getKey(),
            'reference' => $this->codes->reference(),
            'verification_code' => $this->codes->verificationCode(),
            'amount' => $amount,
            'method' => PaymentMethod::Paystack,
            'gateway' => 'paystack',
        ]);
        $payment->organization_id = $tenant->organization_id;
        $payment->gateway_payload = ['requested_invoice_ids' => $invoiceIds];
        $payment->save();

        try {
            $url = $this->gateway->initialize(
                $payment->reference,
                $amount,
                $tenant->organization()->value('currency') ?? (string) config('rentora.defaults.currency'),
                $this->email($tenant),
                route('pay.callback'),
                ['payment_id' => $payment->getKey(), 'tenant_id' => $tenant->getKey(), 'invoice_ids' => $invoiceIds],
            );
        } catch (Throwable $e) {
            $payment->forceFill(['status' => PaymentStatus::Failed, 'rejection_reason' => $e->getMessage()])->save();
            throw ValidationException::withMessages(['amount' => __('payments.errors.gateway_unavailable')]);
        }

        return ['payment' => $payment, 'url' => $url];
    }

    /** Paystack needs an email; tenants without one get a placeholder on the app's domain (spec 8.6). */
    private function email(Tenant $tenant): string
    {
        if (($tenant->email ?? '') !== '') {
            return $tenant->email;
        }
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com';

        return 'tenant'.$tenant->getKey().'@'.$host;
    }
}
