<?php

declare(strict_types=1);

use App\Actions\Payments\CompletePayment;
use App\Actions\Payments\TransferProofs;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\PaymentReconciler;
use App\Services\Payments\PaymentVerifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** A tenant with a portal login and open invoices of the given amounts (oldest first). */
function tenantWithInvoices(array $amounts, ?User $staff = null): array
{
    $organization = $staff?->organization ?? organization();
    $user = userWithRole(Role::Tenant, $organization);
    $tenant = Tenant::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'email' => $user->email]);
    $invoices = [];
    foreach (array_values($amounts) as $i => $amount) {
        $invoices[] = Invoice::factory()->create([
            'tenant_id' => $tenant->id, 'amount' => $amount, 'due_date' => today()->subMonths(count($amounts) - $i),
            'number' => 'INV-T-'.$tenant->id.'-'.$i,
        ]);
    }

    return [$tenant, $user, $invoices];
}

function fakePaystackVerify(int $amount, string $status = 'success', string $currency = 'NGN'): void
{
    config(['rentora.paystack.public_key' => 'pk_test_public', 'rentora.paystack.secret_key' => 'sk_test_secret']);
    Http::fake([
        '*/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'status' => $status, 'amount' => $amount, 'currency' => $currency, 'id' => 987654, 'gateway_response' => 'Approved',
        ]]),
        '*/transaction/initialize' => Http::response(['status' => true, 'data' => [
            'authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'x',
        ]]),
    ]);
}

function pendingPaystackPayment(Tenant $tenant, int $amount, array $invoiceIds = []): Payment
{
    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id, 'amount' => $amount, 'method' => PaymentMethod::Paystack, 'status' => PaymentStatus::Pending, 'paid_at' => null,
    ]);
    $payment->forceFill(['gateway_payload' => ['requested_invoice_ids' => $invoiceIds]])->save();

    return $payment;
}

it('starts an online payment and redirects to Paystack checkout', function () {
    fakePaystackVerify(0);
    [$tenant, $user] = tenantWithInvoices([5_000_000]);

    $this->actingAs($user)->post(route('portal.pay.start'), ['amount' => '5000000'])
        ->assertRedirect('https://checkout.paystack.com/abc123');

    $payment = Payment::query()->sole();
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->reference)->toStartWith('RNT-');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transaction/initialize')
        && $request['amount'] === 5_000_000 && $request['reference'] === $payment->reference
        && $request['callback_url'] === route('pay.callback'));
});

it('enforces the ₦100 minimum and the outstanding maximum', function () {
    fakePaystackVerify(0);
    [$tenant, $user] = tenantWithInvoices([5_000_000]);

    $this->actingAs($user)->post(route('portal.pay.start'), ['amount' => '9999'])->assertSessionHasErrors('amount');
    $this->actingAs($user)->post(route('portal.pay.start'), ['amount' => '5000001'])->assertSessionHasErrors('amount');
    expect(Payment::query()->count())->toBe(0);
});

it('confirms a successful payment on the callback and allocates it', function () {
    [$tenant, $user, [$invoice]] = tenantWithInvoices([5_000_000]);
    $payment = pendingPaystackPayment($tenant, 5_000_000);
    fakePaystackVerify(5_000_000);

    $this->actingAs($user)->get(route('pay.callback', ['reference' => $payment->reference]))->assertRedirect(route('portal.home'));

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Successful)
        ->and($payment->receipt_number)->toStartWith('RCT-')
        ->and($payment->gateway_reference)->toBe('987654')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('marks the payment failed when the amount does not match', function () {
    [$tenant, $user, [$invoice]] = tenantWithInvoices([5_000_000]);
    $payment = pendingPaystackPayment($tenant, 5_000_000);
    fakePaystackVerify(4_000_000);

    $this->actingAs($user)->get(route('pay.callback', ['reference' => $payment->reference]));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->rejection_reason)->toBe(__('payments.errors.amount_mismatch'))
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('marks the payment failed when the currency does not match', function () {
    [$tenant] = tenantWithInvoices([5_000_000]);
    $payment = pendingPaystackPayment($tenant, 5_000_000);
    fakePaystackVerify(5_000_000, currency: 'USD');

    app(PaymentVerifier::class)->verify($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

it('processes a duplicate callback only once', function () {
    [$tenant, $user, [$invoice]] = tenantWithInvoices([5_000_000]);
    $payment = pendingPaystackPayment($tenant, 5_000_000);
    fakePaystackVerify(5_000_000);

    $this->actingAs($user)->get(route('pay.callback', ['reference' => $payment->reference]));
    $receipt = $payment->fresh()->receipt_number;
    $this->actingAs($user)->get(route('pay.callback', ['reference' => $payment->reference]));
    app(CompletePayment::class)->handle($payment);

    expect($payment->fresh()->receipt_number)->toBe($receipt)
        ->and($payment->allocations()->count())->toBe(1)
        ->and((int) $payment->allocations()->sum('amount'))->toBe(5_000_000)
        ->and(ActivityLog::query()->where('action', 'payment.received')->count())->toBe(1);
});

it('keeps another tenant away from a callback reference', function () {
    [$tenant] = tenantWithInvoices([5_000_000]);
    $payment = pendingPaystackPayment($tenant, 5_000_000);
    [, $otherUser] = tenantWithInvoices([100], userWithRole(Role::Manager, $tenant->organization));

    $this->actingAs($otherUser)->get(route('pay.callback', ['reference' => $payment->reference]))->assertNotFound();
});

it('rejects a webhook with an invalid signature with 401', function () {
    config(['rentora.paystack.webhooks' => true, 'rentora.paystack.secret_key' => 'sk_test_secret']);
    [$tenant] = tenantWithInvoices([5_000_000]);
    $payment = pendingPaystackPayment($tenant, 5_000_000);
    $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $payment->reference]]);

    $this->call('POST', route('webhooks.paystack'), [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => 'bad', 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertStatus(401);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('accepts a signed webhook and verifies the payment', function () {
    config(['rentora.paystack.webhooks' => true, 'rentora.paystack.secret_key' => 'sk_test_secret']);
    [$tenant] = tenantWithInvoices([5_000_000]);
    $payment = pendingPaystackPayment($tenant, 5_000_000);
    fakePaystackVerify(5_000_000);
    $body = (string) json_encode(['event' => 'charge.success', 'data' => ['reference' => $payment->reference]]);

    $this->call('POST', route('webhooks.paystack'), [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_secret'), 'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Successful);
});

it('keeps the webhook switched off unless enabled', function () {
    $this->postJson(route('webhooks.paystack'), [])->assertNotFound();
});

it('allocates oldest first, part pays the next and keeps the rest as credit', function () {
    $manager = userWithRole(Role::Manager);
    [$tenant, , [$old, $mid, $new]] = tenantWithInvoices([3_000_000, 3_000_000, 3_000_000], $manager);

    $this->actingAs($manager)->post(route('app.payments.store'), [
        'tenant_id' => $tenant->id, 'method' => 'cash', 'amount' => '5000000', 'paid_at' => today()->toDateString(),
    ])->assertRedirect();
    expect($old->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($mid->fresh()->status)->toBe(InvoiceStatus::Partial)
        ->and($mid->fresh()->amount_paid)->toBe(2_000_000)
        ->and($new->fresh()->status)->toBe(InvoiceStatus::Unpaid);

    $this->actingAs($manager)->post(route('app.payments.store'), [
        'tenant_id' => $tenant->id, 'method' => 'pos', 'amount' => '6000000', 'paid_at' => today()->toDateString(),
    ]);
    expect($new->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($tenant->fresh()->credit_balance)->toBe(2_000_000);
});

it('pays chosen invoices first', function () {
    $manager = userWithRole(Role::Manager);
    [$tenant, , [$old, $new]] = tenantWithInvoices([3_000_000, 3_000_000], $manager);

    $this->actingAs($manager)->post(route('app.payments.store'), [
        'tenant_id' => $tenant->id, 'method' => 'cash', 'amount' => '3000000', 'paid_at' => today()->toDateString(), 'invoice_ids' => [$new->id],
    ]);

    expect($new->fresh()->status)->toBe(InvoiceStatus::Paid)->and($old->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('approves a transfer proof with a receipt, or rejects it with a reason', function () {
    Storage::fake('local');
    $manager = userWithRole(Role::Manager);
    [$tenant, $user, [$invoice]] = tenantWithInvoices([2_000_000], $manager);

    $this->actingAs($user)->post(route('portal.pay.proof'), [
        'amount' => '2000000', 'paid_on' => today()->toDateString(), 'sender_name' => 'Ada Okafor',
        'proof' => UploadedFile::fake()->image('slip.jpg'),
    ])->assertRedirect(route('portal.home'));
    $proof = Payment::query()->sole();
    expect($proof->status)->toBe(PaymentStatus::Pending)->and($proof->method)->toBe(PaymentMethod::BankTransfer);
    Storage::disk('local')->assertExists($proof->proof_path);

    $this->actingAs($manager)->get(route('app.payments.proof', $proof))->assertOk();
    $this->actingAs($manager)->post(route('app.payments.approve', $proof))->assertRedirect();
    expect($proof->fresh()->status)->toBe(PaymentStatus::Successful)
        ->and($proof->fresh()->receipt_number)->not->toBeNull()
        ->and($proof->fresh()->approved_by)->toBe($manager->id)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    $second = app(TransferProofs::class)->submit($tenant, 1_000_000, today(), 'Ada', UploadedFile::fake()->image('slip2.jpg'));
    $this->actingAs($manager)->post(route('app.payments.reject', $second), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($manager)->post(route('app.payments.reject', $second), ['reason' => 'Not on our statement'])->assertRedirect();
    expect($second->fresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($second->fresh()->rejection_reason)->toBe('Not on our statement')
        ->and($second->fresh()->receipt_number)->toBeNull();
});

it('lets only the owner reverse a manual payment, restoring balances', function () {
    $owner = userWithRole(Role::Owner);
    $manager = userWithRole(Role::Manager, $owner->organization);
    [$tenant, , [$invoice]] = tenantWithInvoices([3_000_000], $owner);
    $this->actingAs($manager)->post(route('app.payments.store'), [
        'tenant_id' => $tenant->id, 'method' => 'cash', 'amount' => '4000000', 'paid_at' => today()->toDateString(),
    ]);
    $payment = Payment::query()->sole();
    expect($tenant->fresh()->credit_balance)->toBe(1_000_000);

    $this->actingAs($manager)->post(route('app.payments.reverse', $payment), ['reason' => 'Bounced'])->assertForbidden();
    $this->actingAs($owner)->post(route('app.payments.reverse', $payment), ['reason' => 'Cheque bounced'])->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($payment->allocations()->count())->toBe(0)
        ->and($invoice->fresh()->amount_paid)->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and($tenant->fresh()->credit_balance)->toBe(0)
        ->and(ActivityLog::query()->where('action', 'payment.reversed')->exists())->toBeTrue();
});

it('refuses to reverse a Paystack payment', function () {
    $owner = userWithRole(Role::Owner);
    [$tenant] = tenantWithInvoices([5_000_000], $owner);
    $payment = pendingPaystackPayment($tenant, 5_000_000);
    app(CompletePayment::class)->handle($payment);

    $this->actingAs($owner)->post(route('app.payments.reverse', $payment), ['reason' => 'Refund'])->assertSessionHasErrors('payment');
});

it('issues a receipt PDF and a public verification page with masked data', function () {
    $manager = userWithRole(Role::Manager);
    [$tenant] = tenantWithInvoices([1_000_000], $manager);
    $tenant->update(['full_name' => 'Adaeze Okafor']);
    $this->actingAs($manager)->post(route('app.payments.store'), [
        'tenant_id' => $tenant->id, 'method' => 'cash', 'amount' => '1000000', 'paid_at' => today()->toDateString(),
    ]);
    $payment = Payment::query()->sole();

    $this->actingAs($manager)->get(route('app.payments.receipt', $payment))->assertOk()->assertHeader('content-type', 'application/pdf');
    auth()->logout();

    $this->get(route('receipts.verify', $payment->verification_code))
        ->assertOk()->assertSee(__('payments.verify.valid'))->assertSee($payment->receipt_number)
        ->assertSee('Ad*** Ok***')->assertDontSee('Adaeze Okafor')->assertSee('₦10,000.00');
    $this->get(route('receipts.verify', 'NOTAREALCD'))->assertNotFound()->assertSee(__('payments.verify.not_found'));
});

it('stops a tenant downloading another tenant\'s receipt', function () {
    $manager = userWithRole(Role::Manager);
    [$tenant, $user] = tenantWithInvoices([1_000_000], $manager);
    [$other, $otherUser] = tenantWithInvoices([1_000_000], $manager);
    $payment = Payment::factory()->create(['tenant_id' => $tenant->id, 'receipt_number' => 'RCT-2026-000099']);

    $this->actingAs($user)->get(route('portal.payments.receipt', $payment))->assertOk();
    $this->actingAs($otherUser)->get(route('portal.payments.receipt', $payment))->assertForbidden();
});

it('reconciles pending Paystack payments and expires old ones', function () {
    [$tenant] = tenantWithInvoices([5_000_000]);
    $recent = pendingPaystackPayment($tenant, 5_000_000);
    $recent->forceFill(['created_at' => now()->subMinutes(30)])->save();
    $tooNew = pendingPaystackPayment($tenant, 100);
    $stale = pendingPaystackPayment($tenant, 200);
    $stale->forceFill(['created_at' => now()->subHours(49)])->save();
    fakePaystackVerify(5_000_000);

    expect(app(PaymentReconciler::class)->run())->toBe(['verified' => 1, 'expired' => 1])
        ->and($recent->fresh()->status)->toBe(PaymentStatus::Successful)
        ->and($tooNew->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($stale->fresh()->status)->toBe(PaymentStatus::Failed);
});

it('builds a statement with opening, running and closing balances', function () {
    $manager = userWithRole(Role::Manager);
    [$tenant, $user] = tenantWithInvoices([], $manager);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'amount' => 1_000_000, 'issued_at' => '2026-01-05', 'number' => 'INV-A']);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'amount' => 2_000_000, 'issued_at' => '2026-02-05', 'number' => 'INV-B']);
    Payment::factory()->create(['tenant_id' => $tenant->id, 'amount' => 1_500_000, 'paid_at' => '2026-02-10']);

    $response = $this->actingAs($manager)->get(route('app.tenants.statement', [$tenant, 'from' => '2026-02-01', 'to' => '2026-02-28']));
    $response->assertOk();
    $statement = $response->viewData('statement');
    expect($statement['opening'])->toBe(1_000_000)
        ->and($statement['closing'])->toBe(1_500_000)
        ->and(array_column($statement['lines'], 'balance'))->toBe([3_000_000, 1_500_000]);

    $this->actingAs($user)->get(route('portal.statement', ['from' => '2026-01-01', 'to' => '2026-12-31']))->assertOk()->assertSee('INV-B');
    $this->actingAs($user)->get(route('portal.statement.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('keeps caretakers out of payments', function () {
    $caretaker = userWithRole(Role::Caretaker);

    $this->actingAs($caretaker)->get(route('app.payments.index'))->assertForbidden();
    $this->actingAs($caretaker)->get(route('app.payments.create'))->assertForbidden();
});
