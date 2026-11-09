<?php

declare(strict_types=1);

use App\Actions\Leases\ActivateLease;
use App\Actions\Leases\EndLease;
use App\Actions\Leases\RenewLease;
use App\Actions\Leases\TerminateLease;
use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Enums\UnitStatus;
use App\Models\ActivityLog;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

function leaseFor(User $staff, array $attributes = []): Lease
{
    $unit = Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $staff->organization_id])->id]);

    return Lease::factory()->create(['unit_id' => $unit->id, ...$attributes]);
}

it('activates a lease and marks the unit occupied and unlisted', function () {
    $manager = userWithRole(Role::Manager);
    $lease = leaseFor($manager);
    $lease->unit->forceFill(['is_listed' => true])->save();

    $this->actingAs($manager)->post(route('app.leases.activate', $lease))->assertRedirect(route('app.leases.show', $lease));

    expect($lease->fresh()->status)->toBe(LeaseStatus::Active)
        ->and($lease->unit->fresh()->status)->toBe(UnitStatus::Occupied)
        ->and($lease->unit->fresh()->is_listed)->toBeFalse()
        ->and(ActivityLog::query()->where('action', 'lease.activated')->exists())->toBeTrue();
});

it('refuses a second active lease on the same unit', function () {
    $manager = userWithRole(Role::Manager);
    $first = leaseFor($manager);
    app(ActivateLease::class)->handle($first);
    $second = Lease::factory()->create(['unit_id' => $first->unit_id]);

    $this->actingAs($manager)->post(route('app.leases.activate', $second))->assertSessionHasErrors('lease');

    expect($second->fresh()->status)->toBe(LeaseStatus::Draft)
        ->and(Lease::query()->where('unit_id', $first->unit_id)->active()->count())->toBe(1);
});

it('refuses to activate a lease twice', function () {
    $lease = leaseFor(userWithRole(Role::Manager));
    app(ActivateLease::class)->handle($lease);

    app(ActivateLease::class)->handle($lease);
})->throws(ValidationException::class);

it('terminates early with a reason and frees the unit', function () {
    $manager = userWithRole(Role::Manager);
    $lease = leaseFor($manager);
    app(ActivateLease::class)->handle($lease);

    $this->actingAs($manager)->post(route('app.leases.terminate', $lease), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($manager)->post(route('app.leases.terminate', $lease), ['reason' => 'Relocating abroad'])->assertRedirect();

    $lease->refresh();
    expect($lease->status)->toBe(LeaseStatus::Terminated)
        ->and($lease->termination_reason)->toBe('Relocating abroad')
        ->and($lease->terminated_at)->not->toBeNull()
        ->and($lease->unit->fresh()->status)->toBe(UnitStatus::Vacant)
        ->and($lease->tenant->fresh()->status)->toBe(TenantStatus::Former)
        ->and(ActivityLog::query()->where('action', 'lease.terminated')->exists())->toBeTrue();
});

it('ends a lease only on or after its end date', function () {
    $manager = userWithRole(Role::Manager);
    $lease = leaseFor($manager, ['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    app(ActivateLease::class)->handle($lease);

    Carbon::setTestNow('2026-12-30');
    $this->actingAs($manager)->post(route('app.leases.end', $lease))->assertSessionHasErrors('lease');

    Carbon::setTestNow('2026-12-31');
    $this->actingAs($manager)->post(route('app.leases.end', $lease))->assertRedirect();

    expect($lease->fresh()->status)->toBe(LeaseStatus::Ended)
        ->and($lease->unit->fresh()->status)->toBe(UnitStatus::Vacant);
    Carbon::setTestNow();
});

it('renews into a draft lease starting the day after the end date', function () {
    $manager = userWithRole(Role::Manager);
    $lease = leaseFor($manager, ['start_date' => '2026-03-15', 'end_date' => '2027-03-14', 'rent_amount' => 100_000_000]);
    app(ActivateLease::class)->handle($lease);

    $this->actingAs($manager)->post(route('app.leases.renew', $lease), [
        'end_date' => '2028-03-14',
        'rent_amount' => '120000000',
        'service_charge_amount' => '0',
    ])->assertRedirect();

    $renewal = Lease::query()->where('renewal_of_id', $lease->id)->firstOrFail();
    expect($renewal->start_date->toDateString())->toBe('2027-03-15')
        ->and($renewal->end_date->toDateString())->toBe('2028-03-14')
        ->and($renewal->rent_amount)->toBe(120_000_000)
        ->and($renewal->status)->toBe(LeaseStatus::Draft)
        ->and($renewal->tenant_id)->toBe($lease->tenant_id);
});

it('defaults a renewal to the same length, across month ends and leap years', function (string $start, string $end, string $renewalEnd) {
    $lease = leaseFor(userWithRole(Role::Manager), ['start_date' => $start, 'end_date' => $end]);
    app(ActivateLease::class)->handle($lease);

    $renewal = app(RenewLease::class)->handle($lease);

    expect($renewal->start_date->toDateString())->toBe(Carbon::parse($end)->addDay()->toDateString())
        ->and($renewal->end_date->toDateString())->toBe($renewalEnd);
})->with([
    'one year' => ['2026-01-01', '2026-12-31', '2027-12-31'],
    'into a leap year' => ['2027-03-01', '2028-02-29', '2029-02-28'],
    'six months from a month end' => ['2026-08-31', '2027-02-27', '2027-08-27'],
    'one month' => ['2026-01-31', '2026-02-27', '2026-03-27'],
]);

it('refuses to renew twice', function () {
    $lease = leaseFor(userWithRole(Role::Manager));
    app(ActivateLease::class)->handle($lease);
    app(RenewLease::class)->handle($lease);

    app(RenewLease::class)->handle($lease);
})->throws(ValidationException::class);

it('activates the renewal when the current lease ends, keeping the unit occupied', function () {
    $lease = leaseFor(userWithRole(Role::Manager), ['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    app(ActivateLease::class)->handle($lease);
    $renewal = app(RenewLease::class)->handle($lease);

    Carbon::setTestNow('2027-01-01');
    $activated = app(EndLease::class)->handle($lease);
    Carbon::setTestNow();

    expect($activated?->id)->toBe($renewal->id)
        ->and($renewal->fresh()->status)->toBe(LeaseStatus::Active)
        ->and($lease->fresh()->status)->toBe(LeaseStatus::Ended)
        ->and($lease->unit->fresh()->status)->toBe(UnitStatus::Occupied);
});

it('discards a prepared renewal when the lease is terminated', function () {
    $lease = leaseFor(userWithRole(Role::Manager));
    app(ActivateLease::class)->handle($lease);
    $renewal = app(RenewLease::class)->handle($lease);

    app(TerminateLease::class)->handle($lease, 'Breach');

    expect(Lease::query()->find($renewal->id))->toBeNull();
});

it('blocks deleting a unit or property with an active lease', function () {
    $owner = userWithRole(Role::Owner);
    $lease = leaseFor($owner);
    app(ActivateLease::class)->handle($lease);

    $this->actingAs($owner)->delete(route('app.units.destroy', $lease->unit))->assertSessionHasErrors('unit');
    $this->actingAs($owner)->delete(route('app.properties.destroy', $lease->unit->property_id))->assertSessionHasErrors('property');

    expect(Unit::query()->find($lease->unit_id))->not->toBeNull();
});

it('allows deleting the unit once the lease has ended', function () {
    $owner = userWithRole(Role::Owner);
    $lease = leaseFor($owner);
    app(ActivateLease::class)->handle($lease);
    app(TerminateLease::class)->handle($lease, 'Moved out');

    $this->actingAs($owner)->delete(route('app.units.destroy', $lease->unit))->assertSessionHasNoErrors();

    expect(Unit::withTrashed()->find($lease->unit_id)->trashed())->toBeTrue();
});

it('keeps leases away from caretakers and other organizations', function () {
    $manager = userWithRole(Role::Manager);
    $lease = leaseFor($manager);

    $this->actingAs(userWithRole(Role::Caretaker, $manager->organization))->get(route('app.leases.show', $lease))->assertForbidden();
    $this->actingAs(userWithRole(Role::Manager))->get(route('app.leases.show', $lease))->assertNotFound();
    $this->actingAs($manager)->get(route('app.leases.show', $lease))->assertOk();
    $this->actingAs($manager)->get(route('app.leases.index'))->assertOk()->assertSee($lease->tenant->full_name);
});

it('blocks deleting a tenant with an active lease', function () {
    $owner = userWithRole(Role::Owner);
    $lease = leaseFor($owner);
    app(ActivateLease::class)->handle($lease);

    $this->actingAs($owner)->delete(route('app.tenants.destroy', $lease->tenant))->assertSessionHasErrors('tenant');

    expect(Tenant::query()->find($lease->tenant_id))->not->toBeNull();
});
