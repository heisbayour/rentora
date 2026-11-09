<?php

declare(strict_types=1);

use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Notifications\TenantInvitation;
use Illuminate\Support\Facades\Notification;

it('creates a tenant and an active lease in five steps', function () {
    Notification::fake();
    $manager = userWithRole(Role::Manager);
    $property = Property::factory()->create(['organization_id' => $manager->organization_id]);
    $unit = Unit::factory()->create(['property_id' => $property->id, 'rent_amount' => 90_000_000, 'caution_deposit_amount' => 10_000_000]);
    Unit::factory()->occupied()->create(['property_id' => $property->id, 'label' => 'Taken 1']);

    $this->actingAs($manager)->get(route('app.leases.wizard.start'))->assertRedirect(route('app.leases.wizard.show', 'tenant'));
    $this->actingAs($manager)->post(route('app.leases.wizard.store', 'tenant'), [
        'full_name' => 'Musa Bello', 'phone' => '08035550000', 'email' => 'musa@example.com',
    ])->assertRedirect(route('app.leases.wizard.show', 'unit'));

    $this->actingAs($manager)->get(route('app.leases.wizard.show', 'unit'))->assertOk()->assertSee($unit->label)->assertDontSee('Taken 1');
    $this->actingAs($manager)->post(route('app.leases.wizard.store', 'unit'), ['unit_id' => $unit->id])
        ->assertRedirect(route('app.leases.wizard.show', 'terms'));

    // Prefilled from the unit: the money input's hidden field carries the unit rent in kobo.
    $this->actingAs($manager)->get(route('app.leases.wizard.show', 'terms'))->assertOk()->assertSee('value="90000000"', false);
    $this->actingAs($manager)->post(route('app.leases.wizard.store', 'terms'), [
        'start_date' => '2026-12-01', 'end_date' => '2027-11-30',
        'rent_amount' => '95000000', 'rent_frequency' => 'annual', 'service_charge_amount' => '0',
        'billing_anchor_day' => '1', 'invoice_lead_days' => '30', 'grace_days' => '5',
        'late_fee_type' => 'percent', 'late_fee_value_fixed' => '0', 'late_fee_value_percent' => '500',
    ])->assertRedirect(route('app.leases.wizard.show', 'deposit'));

    $this->actingAs($manager)->post(route('app.leases.wizard.store', 'deposit'), [
        'deposit_amount' => '10000000', 'deposit_status' => 'held', 'issue_first_invoices' => '1',
    ])->assertRedirect(route('app.leases.wizard.show', 'review'));

    $this->actingAs($manager)->get(route('app.leases.wizard.show', 'review'))->assertOk()->assertSee('Musa Bello');
    $this->actingAs($manager)->post(route('app.leases.wizard.store', 'review'), ['activate_now' => '1', 'send_invite' => '1'])
        ->assertRedirect();

    $lease = Lease::query()->with('tenant')->firstOrFail();
    expect($lease->status)->toBe(LeaseStatus::Active)
        ->and($lease->rent_amount)->toBe(95_000_000)
        ->and($lease->late_fee_value)->toBe(500)
        ->and($lease->deposit_amount)->toBe(10_000_000)
        ->and($lease->tenant->full_name)->toBe('Musa Bello')
        ->and($unit->fresh()->status)->toBe(UnitStatus::Occupied);
    Notification::assertSentTo($lease->tenant, TenantInvitation::class);
});

it('starts at the unit step for an existing tenant and can save a draft', function () {
    $manager = userWithRole(Role::Manager);
    $tenant = Tenant::factory()->create(['organization_id' => $manager->organization_id]);
    $unit = Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id]);

    $this->actingAs($manager)->get(route('app.leases.wizard.start', ['tenant' => $tenant->id]))
        ->assertRedirect(route('app.leases.wizard.show', 'unit'));
    $this->actingAs($manager)->post(route('app.leases.wizard.store', 'unit'), ['unit_id' => $unit->id]);
    $this->actingAs($manager)->post(route('app.leases.wizard.store', 'terms'), [
        'start_date' => '2026-12-01', 'end_date' => '2027-05-31', 'rent_amount' => '5000000', 'rent_frequency' => 'monthly',
        'service_charge_amount' => '0', 'billing_anchor_day' => '1', 'invoice_lead_days' => '7', 'grace_days' => '5', 'late_fee_type' => 'none',
    ]);
    $this->actingAs($manager)->post(route('app.leases.wizard.store', 'deposit'), ['deposit_amount' => '0', 'deposit_status' => 'unpaid', 'issue_first_invoices' => '0']);
    $this->actingAs($manager)->post(route('app.leases.wizard.store', 'review'), ['activate_now' => '0', 'send_invite' => '0']);

    $lease = Lease::query()->firstOrFail();
    expect($lease->tenant_id)->toBe($tenant->id)
        ->and($lease->status)->toBe(LeaseStatus::Draft)
        ->and(Tenant::query()->count())->toBe(1)
        ->and($unit->fresh()->status)->toBe(UnitStatus::Vacant);
});

it('does not let a deep link skip ahead', function () {
    $manager = userWithRole(Role::Manager);

    $this->actingAs($manager)->get(route('app.leases.wizard.start'));
    $this->actingAs($manager)->get(route('app.leases.wizard.show', 'review'))
        ->assertRedirect(route('app.leases.wizard.show', 'tenant'));
});

it('refuses an occupied or foreign unit', function () {
    $manager = userWithRole(Role::Manager);
    $occupied = Unit::factory()->occupied()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id]);
    $foreign = Unit::factory()->create();

    $this->actingAs($manager)->get(route('app.leases.wizard.start'));
    $this->actingAs($manager)->post(route('app.leases.wizard.store', 'tenant'), ['full_name' => 'X', 'phone' => '0803']);
    $this->actingAs($manager)->post(route('app.leases.wizard.store', 'unit'), ['unit_id' => $occupied->id])->assertSessionHasErrors('unit_id');
    $this->actingAs($manager)->post(route('app.leases.wizard.store', 'unit'), ['unit_id' => $foreign->id])->assertSessionHasErrors('unit_id');
});

it('is closed to caretakers', function () {
    $this->actingAs(userWithRole(Role::Caretaker))->get(route('app.leases.wizard.start'))->assertForbidden();
});
