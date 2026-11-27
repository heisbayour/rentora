<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\SettingsService;
use App\Support\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** A tenant with a portal login and one open invoice. */
function portalTenant(?Organization $organization = null): array
{
    $organization ??= organization();
    $user = userWithRole(Role::Tenant, $organization);
    $tenant = Tenant::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'email' => $user->email]);
    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'amount' => 5_000_000, 'due_date' => today()->subDays(3)]);

    return [$user, $tenant, $invoice];
}

it('renders every portal screen for a tenant', function () {
    [$user, , $invoice] = portalTenant();

    foreach (['portal.home', 'portal.pay', 'portal.payments.index', 'portal.invoices.index', 'portal.documents', 'portal.account', 'portal.inbox', 'portal.notices', 'portal.statement'] as $route) {
        $this->actingAs($user)->get(route($route))->assertOk();
    }
    $this->actingAs($user)->get(route('portal.invoices.show', $invoice))->assertOk()->assertSee($invoice->number);
    $this->actingAs($user)->get(route('portal.invoices.pdf', $invoice))->assertOk();
});

it('shows the balance and the bank transfer path when no gateway is configured', function () {
    [$user, $tenant] = portalTenant();
    app(CurrentOrganization::class)->runAs($tenant->organization, fn () => app(SettingsService::class)
        ->setMany(['billing.bank_name' => 'Zenith Bank', 'billing.bank_account_number' => '1234567890', 'billing.bank_account_name' => 'Demo Properties']));

    $this->actingAs($user)->get(route('portal.home'))->assertSee('50,000.00');
    $this->actingAs($user)->get(route('portal.pay'))
        ->assertSee('1234567890')
        ->assertDontSee(__('portal.pay.pay_online'));
});

it('keeps another tenant\'s invoice, receipt and statement out of reach', function () {
    $organization = organization();
    [$userA] = portalTenant($organization);
    [, $tenantB, $invoiceB] = portalTenant($organization);
    $paymentB = Payment::factory()->create(['tenant_id' => $tenantB->id, 'status' => PaymentStatus::Successful, 'receipt_number' => 'RCT-B-1']);

    $this->actingAs($userA)->get(route('portal.invoices.show', $invoiceB))->assertForbidden();
    $this->actingAs($userA)->get(route('portal.invoices.pdf', $invoiceB))->assertForbidden();
    $this->actingAs($userA)->get(route('portal.payments.receipt', $paymentB))->assertForbidden();
    $this->actingAs($userA)->get(route('app.tenants.statement', $tenantB))->assertForbidden();
    $this->actingAs($userA)->get(route('portal.statement'))->assertOk()->assertDontSee($invoiceB->number);
    $this->actingAs($userA)->get(route('portal.invoices.index'))->assertDontSee($invoiceB->number);
    $this->actingAs($userA)->get(route('portal.payments.index'))->assertDontSee('RCT-B-1');
});

it('hides invoices of another organization behind a 404', function () {
    [$userA] = portalTenant();
    [, , $foreignInvoice] = portalTenant();

    $this->actingAs($userA)->get(route('portal.invoices.show', $foreignInvoice))->assertNotFound();
});

it('creates a pending transfer payment for the signed-in tenant only', function () {
    Storage::fake('local');
    $organization = organization();
    [$user, $tenant, $invoice] = portalTenant($organization);
    [, $other, $otherInvoice] = portalTenant($organization);

    $this->actingAs($user)->post(route('portal.pay.proof'), [
        'amount' => 5_000_000,
        'paid_on' => today()->toDateString(),
        'sender_name' => 'Ada Obi',
        'proof' => UploadedFile::fake()->image('receipt.jpg'),
        'invoice_ids' => [$invoice->id],
    ])->assertRedirect(route('portal.pay'));

    $payment = Payment::query()->sole();
    expect($payment->tenant_id)->toBe($tenant->id)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->method)->toBe(PaymentMethod::BankTransfer);

    // Another tenant's invoice cannot be attached to the payment.
    $this->actingAs($user)->post(route('portal.pay.proof'), [
        'amount' => 5_000_000, 'paid_on' => today()->toDateString(), 'sender_name' => 'Ada Obi',
        'proof' => UploadedFile::fake()->image('receipt.jpg'), 'invoice_ids' => [$otherInvoice->id],
    ])->assertSessionHasErrors('invoice_ids.0');
    expect(Payment::query()->where('tenant_id', $other->id)->exists())->toBeFalse();
});

it('starts a Paystack payment for the signed-in tenant', function () {
    [$user, $tenant, $invoice] = portalTenant();
    fakePaystackVerify(5_000_000);

    $this->actingAs($user)->post(route('portal.pay.start'), ['amount' => 5_000_000, 'invoice_ids' => [$invoice->id]])
        ->assertRedirect('https://checkout.paystack.com/abc123');

    $payment = Payment::query()->sole();
    expect($payment->tenant_id)->toBe($tenant->id)->and($payment->status)->toBe(PaymentStatus::Pending);
});

it('serves the PWA manifest and offline assets', function () {
    organization(['name' => 'Demo Properties Ltd']);

    $this->get('/manifest.webmanifest')->assertOk()
        ->assertJsonPath('start_url', '/portal')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('version', config('rentora.version'))
        ->assertJsonCount(3, 'icons');

    expect(is_file(public_path('sw.js')))->toBeTrue()
        ->and(is_file(public_path('offline.html')))->toBeTrue()
        ->and(is_file(public_path('icons/icon-maskable-512.png')))->toBeTrue();
});

it('lets a tenant update their name and phone', function () {
    [$user, $tenant] = portalTenant();

    $this->actingAs($user)->put(route('portal.account.update'), ['name' => 'New Name', 'phone' => '08035550000'])->assertRedirect(route('portal.account'));

    expect($user->fresh()->name)->toBe('New Name')->and($tenant->fresh()->phone)->toBe('08035550000');
});

it('serves Digital Asset Links for the Android app', function () {
    $this->get('/.well-known/assetlinks.json')->assertOk()->assertExactJson([]);

    config(['rentora.android.package' => 'com.heisbayour.rentora', 'rentora.android.fingerprints' => ['AA:BB:CC']]);

    $this->get('/.well-known/assetlinks.json')->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('0.target.package_name', 'com.heisbayour.rentora')
        ->assertJsonPath('0.target.sha256_cert_fingerprints.0', 'AA:BB:CC')
        ->assertJsonPath('0.relation.0', 'delegate_permission/common.handle_all_urls');
});

it('marks only the current section as active in the portal navigation', function () {
    [$user] = portalTenant();

    foreach (['portal.home', 'portal.pay', 'portal.repairs.index', 'portal.inbox', 'portal.account'] as $route) {
        $html = $this->actingAs($user)->get(route($route))->assertOk()->getContent();
        // Once in the desktop bar, once in the mobile bottom bar.
        expect(substr_count($html, 'aria-current="page"'))->toBe(2, $route);
    }
});

it('hides the install app card inside the Android app', function () {
    [$user] = portalTenant();

    $this->actingAs($user)->get(route('portal.home'))->assertOk()->assertSee(__('portal.install.title'));
    $this->actingAs($user)->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14; wv) Chrome/131.0 Mobile RentoraApp')
        ->get(route('portal.home'))->assertOk()->assertDontSee(__('portal.install.title'));
});
