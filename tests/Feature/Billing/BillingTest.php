<?php

declare(strict_types=1);

use App\Actions\Billing\IssueInitialInvoices;
use App\Actions\Leases\ActivateLease;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\LateFeeType;
use App\Enums\RentFrequency;
use App\Enums\Role;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\AllocationService;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\LateFeeService;
use Illuminate\Support\Carbon;

function activeLease(User $staff, array $attributes = []): Lease
{
    $unit = Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $staff->organization_id])->id]);
    $lease = Lease::factory()->create(['unit_id' => $unit->id, ...$attributes]);
    app(ActivateLease::class)->handle($lease);

    return $lease->fresh(['tenant']);
}

afterEach(fn () => Carbon::setTestNow());

it('issues rent once the lead time is reached, numbered in sequence', function () {
    $lease = activeLease(userWithRole(Role::Manager), [
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'rent_frequency' => RentFrequency::Monthly,
        'rent_amount' => 10_000_000, 'invoice_lead_days' => 7, 'billing_anchor_day' => 1,
    ]);
    $generator = app(InvoiceGenerator::class);

    Carbon::setTestNow('2026-01-20');
    expect($generator->run())->toBe(1);

    Carbon::setTestNow('2026-01-25');
    expect($generator->run())->toBe(1);

    $invoices = Invoice::query()->orderBy('id')->get();
    expect($invoices->pluck('period_start')->map->toDateString()->all())->toBe(['2026-01-01', '2026-02-01'])
        ->and($invoices->first()->type)->toBe(InvoiceType::Rent)
        ->and($invoices->first()->due_date->toDateString())->toBe('2026-01-01')
        ->and($invoices->first()->number)->toBe('INV-2026-000001')
        ->and($invoices->last()->number)->toBe('INV-2026-000002');
});

it('creates no duplicates when generation runs twice', function () {
    activeLease(userWithRole(Role::Manager), [
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'rent_frequency' => RentFrequency::Quarterly,
        'service_charge_amount' => 2_000_000, 'invoice_lead_days' => 14,
    ]);

    Carbon::setTestNow('2026-03-20');
    $first = app(InvoiceGenerator::class)->run();
    $second = app(InvoiceGenerator::class)->run();

    expect($first)->toBe(4)
        ->and($second)->toBe(0)
        ->and(Invoice::query()->where('type', 'rent')->count())->toBe(2)
        ->and(Invoice::query()->where('type', 'service_charge')->count())->toBe(2);
});

it('generates the correct annual periods', function () {
    activeLease(userWithRole(Role::Manager), [
        'start_date' => '2027-03-01', 'end_date' => '2029-02-28', 'rent_frequency' => RentFrequency::Annual, 'invoice_lead_days' => 30,
    ]);

    Carbon::setTestNow('2028-02-10');
    app(InvoiceGenerator::class)->run();

    expect(Invoice::query()->orderBy('id')->get()->map(fn ($i) => $i->period_start->toDateString().'/'.$i->period_end->toDateString())->all())
        ->toBe(['2027-03-01/2028-02-29', '2028-03-01/2029-02-28']);
});

it('skips draft and ended leases', function () {
    $manager = userWithRole(Role::Manager);
    Lease::factory()->create(['unit_id' => Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id])->id]);

    expect(app(InvoiceGenerator::class)->run())->toBe(0);
});

it('issues the first rent and an unpaid deposit at activation', function () {
    $lease = activeLease(userWithRole(Role::Manager), [
        'start_date' => today()->addMonths(2)->toDateString(), 'end_date' => today()->addMonths(14)->toDateString(),
        'deposit_amount' => 5_000_000, 'invoice_lead_days' => 7,
    ]);

    expect(app(IssueInitialInvoices::class)->handle($lease))->toBe(2)
        ->and(app(IssueInitialInvoices::class)->handle($lease))->toBe(0)
        ->and(Invoice::query()->pluck('type')->map->value->sort()->values()->all())->toBe(['deposit', 'rent']);
});

it('applies a late fee once, after the grace days', function () {
    $lease = activeLease(userWithRole(Role::Manager), [
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'rent_frequency' => RentFrequency::Monthly,
        'rent_amount' => 10_000_000, 'grace_days' => 5, 'late_fee_type' => LateFeeType::Percent, 'late_fee_value' => 1000,
    ]);
    Carbon::setTestNow('2026-01-01');
    app(InvoiceGenerator::class)->run();
    $rent = Invoice::query()->firstOrFail();

    Carbon::setTestNow('2026-01-06');
    expect(app(LateFeeService::class)->run())->toBe(0);

    Carbon::setTestNow('2026-01-07');
    expect(app(LateFeeService::class)->run())->toBe(1);
    Carbon::setTestNow('2026-01-20');
    expect(app(LateFeeService::class)->run())->toBe(0);

    $fee = Invoice::query()->where('type', 'late_fee')->sole();
    expect($fee->parent_invoice_id)->toBe($rent->id)
        ->and($fee->amount)->toBe(1_000_000);
});

it('charges no late fee on paid invoices or leases without one', function () {
    $lease = activeLease(userWithRole(Role::Manager), [
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'rent_frequency' => RentFrequency::Monthly,
        'late_fee_type' => LateFeeType::Fixed, 'late_fee_value' => 500_000,
    ]);
    Carbon::setTestNow('2026-01-01');
    app(InvoiceGenerator::class)->run();
    Invoice::query()->update(['amount_paid' => DB::raw('amount'), 'status' => 'paid']);

    Carbon::setTestNow('2026-02-01');
    expect(app(LateFeeService::class)->run())->toBe(0);
});

it('applies the tenant credit balance to a new invoice', function () {
    $lease = activeLease(userWithRole(Role::Manager), [
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'rent_frequency' => RentFrequency::Monthly, 'rent_amount' => 10_000_000,
    ]);
    $payment = Payment::factory()->create(['tenant_id' => $lease->tenant_id, 'amount' => 15_000_000]);
    app(AllocationService::class)->recalculateCredit($lease->tenant_id);
    expect($lease->tenant->fresh()->credit_balance)->toBe(15_000_000);

    Carbon::setTestNow('2026-01-01');
    app(InvoiceGenerator::class)->forLease($lease, ignoreLeadTime: true, limit: 2);

    $invoices = Invoice::query()->orderBy('id')->get();
    expect($invoices[0]->status)->toBe(InvoiceStatus::Paid)
        ->and($invoices[1]->status)->toBe(InvoiceStatus::Partial)
        ->and($invoices[1]->amount_paid)->toBe(5_000_000)
        ->and($lease->tenant->fresh()->credit_balance)->toBe(0)
        ->and(PaymentAllocation::query()->where('payment_id', $payment->id)->sum('amount'))->toEqual(15_000_000);
});

it('lets staff raise and void a manual invoice', function () {
    $manager = userWithRole(Role::Manager);
    $lease = activeLease($manager);

    $this->actingAs($manager)->post(route('app.invoices.store'), [
        'tenant_id' => $lease->tenant_id, 'lease_id' => $lease->id, 'type' => 'utility',
        'description' => 'Water, October', 'amount' => '1250000', 'due_date' => today()->toDateString(),
    ])->assertRedirect();
    $invoice = Invoice::query()->sole();
    expect($invoice->unit_id)->toBe($lease->unit_id);

    $this->actingAs($manager)->get(route('app.invoices.show', $invoice))->assertOk()->assertSee('Water, October');
    $this->actingAs($manager)->get(route('app.invoices.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->actingAs($manager)->post(route('app.invoices.void', $invoice), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($manager)->post(route('app.invoices.void', $invoice), ['reason' => 'Billed in error'])->assertRedirect();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
});

it('refuses to void an invoice with payments applied', function () {
    $manager = userWithRole(Role::Manager);
    $lease = activeLease($manager);
    $invoice = Invoice::factory()->create(['tenant_id' => $lease->tenant_id, 'amount' => 1_000_000]);
    $payment = Payment::factory()->create(['tenant_id' => $lease->tenant_id, 'amount' => 500_000]);
    app(AllocationService::class)->allocatePayment($payment);

    $this->actingAs($manager)->post(route('app.invoices.void', $invoice), ['reason' => 'Mistake'])->assertSessionHasErrors('invoice');
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Partial);
});

it('refuses manual rent invoices and foreign tenants', function () {
    $manager = userWithRole(Role::Manager);
    $lease = activeLease($manager);
    $foreignLease = activeLease(userWithRole(Role::Manager));

    $this->actingAs($manager)->post(route('app.invoices.store'), [
        'tenant_id' => $lease->tenant_id, 'type' => 'rent', 'description' => 'x', 'amount' => '100', 'due_date' => today()->toDateString(),
    ])->assertSessionHasErrors('type');
    $this->actingAs($manager)->post(route('app.invoices.store'), [
        'tenant_id' => $foreignLease->tenant_id, 'type' => 'other', 'description' => 'x', 'amount' => '100', 'due_date' => today()->toDateString(),
    ])->assertSessionHasErrors('tenant_id');
});

it('keeps caretakers out of money routes', function () {
    $caretaker = userWithRole(Role::Caretaker);
    $invoice = Invoice::factory()->create(['tenant_id' => Tenant::factory()->create(['organization_id' => $caretaker->organization_id])->id]);

    foreach ([route('app.invoices.index'), route('app.invoices.create'), route('app.invoices.show', $invoice), route('app.invoices.pdf', $invoice)] as $url) {
        $this->actingAs($caretaker)->get($url)->assertForbidden();
    }
});

it('returns 404 for another organization\'s invoice', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs(userWithRole(Role::Manager))->get(route('app.invoices.show', $invoice))->assertNotFound();
});

it('lists invoices with overdue filter and shows them on the tenant profile', function () {
    $manager = userWithRole(Role::Manager);
    $lease = activeLease($manager);
    $late = Invoice::factory()->create(['tenant_id' => $lease->tenant_id, 'due_date' => today()->subDays(10), 'number' => 'INV-LATE-1']);
    Invoice::factory()->create(['tenant_id' => $lease->tenant_id, 'due_date' => today()->addDays(10), 'number' => 'INV-SOON-1']);

    $this->actingAs($manager)->get(route('app.invoices.index', ['overdue' => '1']))->assertOk()->assertSee('INV-LATE-1')->assertDontSee('INV-SOON-1');
    $this->actingAs($manager)->get(route('app.tenants.show', [$lease->tenant, 'tab' => 'invoices']))->assertOk()->assertSee($late->number);
});
