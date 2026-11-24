<?php

declare(strict_types=1);

use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UnitStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\Reports\ArrearsAging;
use App\Services\Reports\DashboardMetrics;
use App\Support\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('puts arrears into the right aging buckets', function () {
    $this->travelTo('2026-11-24 12:00:00');
    $organization = organization();
    $tenantA = Tenant::factory()->create(['organization_id' => $organization->id, 'full_name' => 'Ada']);
    $tenantB = Tenant::factory()->create(['organization_id' => $organization->id, 'full_name' => 'Bola']);

    $invoice = fn (Tenant $tenant, int $daysOverdue, int $amount, int $paid = 0, string $status = 'unpaid') => Invoice::factory()->create([
        'tenant_id' => $tenant->id, 'due_date' => today()->subDays($daysOverdue), 'amount' => $amount, 'amount_paid' => $paid, 'status' => $status,
    ]);
    $invoice($tenantA, 1, 100_00);            // 0-30
    $invoice($tenantA, 30, 200_00);           // 0-30
    $invoice($tenantA, 31, 300_00, 100_00, 'partial'); // 31-60, 200 owed
    $invoice($tenantB, 60, 400_00);           // 31-60
    $invoice($tenantB, 61, 500_00);           // 61-90
    $invoice($tenantB, 90, 600_00);           // 61-90
    $invoice($tenantB, 91, 700_00);           // 90+
    $invoice($tenantB, 0, 999_00);            // due today: not in arrears
    $invoice($tenantB, -5, 999_00);           // not yet due
    $invoice($tenantB, 200, 999_00, 999_00, 'paid');
    $invoice($tenantB, 200, 999_00, 0, 'void');

    $result = app(CurrentOrganization::class)->runAs($organization, fn () => app(ArrearsAging::class)->build());

    expect($result['totals'])->toBe(['0_30' => 300_00, '31_60' => 600_00, '61_90' => 1100_00, '90_plus' => 700_00])
        ->and($result['total'])->toBe(2700_00)
        ->and($result['rows'][0]['tenant'])->toBe('Bola')
        ->and($result['rows'][0]['total'])->toBe(2200_00)
        ->and($result['rows'][1]['buckets'])->toBe(['0_30' => 300_00, '31_60' => 200_00, '61_90' => 0, '90_plus' => 0]);
});

it('calculates dashboard KPIs from fixture data', function () {
    $this->travelTo('2026-11-24 12:00:00');
    $organization = organization();
    $property = Property::factory()->create(['organization_id' => $organization->id]);
    $occupied = Unit::factory()->count(3)->create(['property_id' => $property->id, 'status' => UnitStatus::Occupied]);
    Unit::factory()->create(['property_id' => $property->id, 'status' => UnitStatus::Vacant]);
    $lease = Lease::factory()->create(['unit_id' => $occupied[0]->id, 'status' => LeaseStatus::Active, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    Lease::factory()->create(['unit_id' => $occupied[1]->id, 'status' => LeaseStatus::Active, 'start_date' => '2026-01-01', 'end_date' => '2027-12-31']);

    Invoice::factory()->create(['tenant_id' => $lease->tenant_id, 'due_date' => '2026-11-01', 'amount' => 1000_00, 'amount_paid' => 400_00, 'status' => 'partial']);
    Invoice::factory()->create(['tenant_id' => $lease->tenant_id, 'due_date' => '2026-11-30', 'amount' => 500_00]);
    Invoice::factory()->create(['tenant_id' => $lease->tenant_id, 'due_date' => '2026-11-10', 'amount' => 700_00, 'status' => 'void']);
    Payment::factory()->create(['tenant_id' => $lease->tenant_id, 'amount' => 400_00, 'status' => PaymentStatus::Successful, 'paid_at' => '2026-11-05 10:00:00']);
    Payment::factory()->create(['tenant_id' => $lease->tenant_id, 'amount' => 900_00, 'status' => PaymentStatus::Successful, 'paid_at' => '2026-10-20 10:00:00']);
    Payment::factory()->create(['tenant_id' => $lease->tenant_id, 'amount' => 300_00, 'status' => PaymentStatus::Pending, 'paid_at' => '2026-11-06 10:00:00']);

    $kpis = app(CurrentOrganization::class)->runAs($organization, fn () => app(DashboardMetrics::class)->kpis());

    expect($kpis['expected'])->toBe(1500_00)
        ->and($kpis['collected'])->toBe(400_00)
        ->and($kpis['collection_rate'])->toBe(27)
        ->and($kpis['arrears'])->toBe(600_00)
        ->and($kpis['units'])->toBe(4)
        ->and($kpis['occupied'])->toBe(3)
        ->and($kpis['vacant'])->toBe(1)
        ->and($kpis['occupancy'])->toBe(75)
        ->and($kpis['expiring'])->toBe(1);
});

it('renders the dashboard for owners and the maintenance view for caretakers', function () {
    $owner = userWithRole(Role::Owner);
    $caretaker = userWithRole(Role::Caretaker, $owner->organization);

    $this->actingAs($owner)->get(route('app.dashboard'))->assertOk()->assertSee(__('dashboard.kpi.collected'));
    $this->actingAs($caretaker)->get(route('app.dashboard'))->assertOk()
        ->assertSee(__('dashboard.kpi.open_repairs'))
        ->assertDontSee(__('dashboard.kpi.collected'));
});

it('keeps caretakers out of every money page', function (string $route, array $params = []) {
    $caretaker = userWithRole(Role::Caretaker);

    $this->actingAs($caretaker)->get(route($route, $params))->assertForbidden();
})->with([
    ['app.invoices.index'], ['app.payments.index'], ['app.expenses.index'], ['app.expenses.create'],
    ['app.reports.index'], ['app.reports.show', ['report' => 'arrears']], ['app.reports.export', ['report' => 'collections']],
    ['app.announcements.index'],
]);

it('keeps the audit log for owners', function () {
    $owner = userWithRole(Role::Owner);
    $manager = userWithRole(Role::Manager, $owner->organization);

    $this->actingAs($owner)->get(route('app.audit.index'))->assertOk();
    $this->actingAs($manager)->get(route('app.audit.index'))->assertForbidden();
});

it('shows each report and streams a CSV', function () {
    $owner = userWithRole(Role::Owner);
    $property = Property::factory()->create(['organization_id' => $owner->organization_id]);
    $lease = Lease::factory()->create(['unit_id' => Unit::factory()->create(['property_id' => $property->id])->id, 'status' => LeaseStatus::Active]);
    $lease->unit->forceFill(['status' => UnitStatus::Occupied])->save();
    Payment::factory()->create(['tenant_id' => $lease->tenant_id, 'lease_id' => $lease->id, 'amount' => 250_000_00, 'status' => PaymentStatus::Successful, 'paid_at' => now(), 'receipt_number' => 'RCT-2026-000001']);

    foreach (['rent_roll', 'arrears', 'collections'] as $report) {
        $this->actingAs($owner)->get(route('app.reports.show', $report))->assertOk();
    }
    $this->actingAs($owner)->get(route('app.reports.show', 'nope'))->assertNotFound();

    $csv = $this->actingAs($owner)->get(route('app.reports.export', 'collections'))->assertOk()->streamedContent();
    expect($csv)->toContain('RCT-2026-000001')->toContain('250000.00');

    $rentRoll = $this->actingAs($owner)->get(route('app.reports.export', 'rent_roll'))->streamedContent();
    expect($rentRoll)->toContain($lease->tenant->full_name);
});

it('records, edits and deletes an expense with a receipt', function () {
    Storage::fake('local');
    $owner = userWithRole(Role::Owner);
    $property = Property::factory()->create(['organization_id' => $owner->organization_id]);

    $this->actingAs($owner)->post(route('app.expenses.store'), [
        'property_id' => $property->id, 'category' => 'repairs', 'amount' => 1_500_000,
        'incurred_on' => today()->toDateString(), 'description' => 'Fixed borehole pump',
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertRedirect(route('app.expenses.index'));

    $expense = Expense::query()->sole();
    expect($expense->organization_id)->toBe($owner->organization_id)->and($expense->receipt_path)->not->toBeNull();
    $this->actingAs($owner)->get(route('app.expenses.receipt', $expense))->assertOk();
    $this->actingAs($owner)->get(route('app.expenses.index'))->assertSee('Fixed borehole pump');

    $this->actingAs($owner)->put(route('app.expenses.update', $expense), [
        'property_id' => $property->id, 'category' => 'utilities', 'amount' => 2_000_000,
        'incurred_on' => today()->toDateString(), 'description' => 'Pump and wiring',
    ])->assertRedirect();
    expect($expense->fresh()->amount)->toBe(2_000_000);

    $this->actingAs($owner)->delete(route('app.expenses.destroy', $expense))->assertRedirect();
    expect(Expense::query()->count())->toBe(0);
});

it('hides another organization\'s expense', function () {
    $owner = userWithRole(Role::Owner);
    $foreign = Expense::factory()->create();

    $this->actingAs($owner)->get(route('app.expenses.edit', $foreign))->assertNotFound();
});
