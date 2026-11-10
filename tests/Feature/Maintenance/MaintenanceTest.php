<?php

declare(strict_types=1);

use App\Actions\Leases\ActivateLease;
use App\Enums\MaintenanceStatus;
use App\Enums\Role;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceUpdate;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\MaintenanceCreated;
use App\Notifications\MaintenanceStatusChanged;
use App\Notifications\VendorJobAssigned;
use App\Services\Maintenance\MaintenanceWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** A tenant user with an active lease on a unit in the organization. */
function leasedTenantUser(Organization $organization): array
{
    $unit = Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $organization->id])->id]);
    $lease = Lease::factory()->create(['unit_id' => $unit->id]);
    app(ActivateLease::class)->handle($lease);
    $user = userWithRole(Role::Tenant, $organization);
    $lease->tenant->forceFill(['user_id' => $user->id])->save();

    return [$user, $lease->tenant->fresh(), $unit];
}

function repairPayload(array $overrides = []): array
{
    return array_merge(['category' => 'plumbing', 'title' => 'Burst pipe', 'description' => 'Water everywhere', 'priority' => 'normal'], $overrides);
}

it('lets a tenant report a repair with photos on their own unit', function () {
    Storage::fake('local');
    $organization = organization();
    [$user, $tenant, $unit] = leasedTenantUser($organization);

    $this->actingAs($user)->post(route('portal.repairs.store'), repairPayload([
        'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ]))->assertRedirect();

    $request = MaintenanceRequest::query()->sole();
    expect($request->unit_id)->toBe($unit->id)
        ->and($request->tenant_id)->toBe($tenant->id)
        ->and($request->number)->toStartWith('MR-')
        ->and($request->status)->toBe(MaintenanceStatus::Submitted)
        ->and($request->attachments()->count())->toBe(2);
});

it('caps a tenant at five photos', function () {
    [$user] = leasedTenantUser(organization());
    $photos = array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 6));

    $this->actingAs($user)->post(route('portal.repairs.store'), repairPayload(['photos' => $photos]))->assertSessionHasErrors('photos');
});

it('notifies the tenant when staff change the status, but not for their own change', function () {
    Notification::fake();
    $organization = organization();
    [$user] = leasedTenantUser($organization);
    $manager = userWithRole(Role::Manager, $organization);

    $this->actingAs($user)->post(route('portal.repairs.store'), repairPayload())->assertRedirect();
    $request = MaintenanceRequest::query()->sole();
    Notification::assertNotSentTo($user, MaintenanceStatusChanged::class);

    $this->actingAs($manager)->post(route('app.maintenance.status', $request), ['status' => 'acknowledged'])->assertSessionHasNoErrors();
    Notification::assertSentTo($user, MaintenanceStatusChanged::class, fn (MaintenanceStatusChanged $n) => $n->request->status === MaintenanceStatus::Acknowledged);
    Notification::assertSentToTimes($user, MaintenanceStatusChanged::class, 1);

    $this->actingAs($user)->post(route('portal.repairs.cancel', $request))->assertSessionHasNoErrors();
    Notification::assertSentToTimes($user, MaintenanceStatusChanged::class, 1);
});

it('notifies active staff of a new request and flags emergencies', function () {
    Notification::fake();
    $organization = organization();
    [$user] = leasedTenantUser($organization);
    $owner = userWithRole(Role::Owner, $organization);
    $caretaker = userWithRole(Role::Caretaker, $organization);
    $inactive = userWithRole(Role::Manager, $organization);
    $inactive->forceFill(['is_active' => false])->save();
    $outsider = userWithRole(Role::Owner, organization());

    $this->actingAs($user)->post(route('portal.repairs.store'), repairPayload(['priority' => 'emergency']))->assertRedirect();

    Notification::assertSentTo([$owner, $caretaker], MaintenanceCreated::class, fn (MaintenanceCreated $n) => $n->isEmergency());
    Notification::assertNotSentTo([$inactive, $outsider, $user], MaintenanceCreated::class);
});

it('rejects invalid status transitions', function () {
    $manager = userWithRole(Role::Manager);
    $request = MaintenanceRequest::factory()->create(['unit_id' => Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id])->id]);

    $this->actingAs($manager)->post(route('app.maintenance.status', $request), ['status' => 'resolved'])->assertSessionHasErrors('status');
    expect($request->fresh()->status)->toBe(MaintenanceStatus::Submitted);

    $this->actingAs($manager)->post(route('app.maintenance.status', $request), ['status' => 'acknowledged'])->assertSessionHasNoErrors();
    $this->actingAs($manager)->post(route('app.maintenance.status', $request), ['status' => 'closed'])->assertSessionHasErrors('status');
    expect($request->fresh()->status)->toBe(MaintenanceStatus::Acknowledged)
        ->and($request->fresh()->first_response_at)->not->toBeNull();
});

it('follows every allowed transition in the spec', function () {
    foreach (MaintenanceStatus::cases() as $from) {
        foreach (MaintenanceStatus::cases() as $to) {
            $allowed = match ($from) {
                MaintenanceStatus::Submitted => ['acknowledged', 'cancelled'],
                MaintenanceStatus::Acknowledged => ['scheduled', 'in_progress', 'on_hold', 'cancelled'],
                MaintenanceStatus::Scheduled => ['in_progress', 'on_hold', 'cancelled'],
                MaintenanceStatus::InProgress => ['resolved', 'on_hold'],
                MaintenanceStatus::OnHold => ['in_progress', 'scheduled', 'cancelled'],
                MaintenanceStatus::Resolved => ['closed', 'in_progress'],
                default => [],
            };
            expect($from->canMoveTo($to))->toBe(in_array($to->value, $allowed, true));
        }
    }
});

it('hides internal comments from the tenant', function () {
    $organization = organization();
    [$user, $tenant, $unit] = leasedTenantUser($organization);
    $manager = userWithRole(Role::Manager, $organization);
    $request = MaintenanceRequest::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id]);

    $this->actingAs($manager)->post(route('app.maintenance.comment', $request), ['body' => 'Vendor overcharges, find another', 'is_internal' => '1']);
    $this->actingAs($manager)->post(route('app.maintenance.comment', $request), ['body' => 'A plumber will visit tomorrow']);

    $this->actingAs($user)->get(route('portal.repairs.show', $request))
        ->assertOk()->assertSee('A plumber will visit tomorrow')->assertDontSee('Vendor overcharges');
    $this->actingAs($manager)->get(route('app.maintenance.show', $request))->assertSee('Vendor overcharges');
});

it('never lets a tenant post an internal note', function () {
    $organization = organization();
    [$user, $tenant, $unit] = leasedTenantUser($organization);
    $request = MaintenanceRequest::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id]);

    $this->actingAs($user)->post(route('portal.repairs.comment', $request), ['body' => 'Still leaking', 'is_internal' => '1']);

    expect(MaintenanceUpdate::query()->sole()->is_internal)->toBeFalse();
});

it('shows tenants only their own requests', function () {
    $organization = organization();
    [$user, $tenant, $unit] = leasedTenantUser($organization);
    [, $other, $otherUnit] = leasedTenantUser($organization);
    $mine = MaintenanceRequest::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'title' => 'My tap']);
    $theirs = MaintenanceRequest::factory()->create(['unit_id' => $otherUnit->id, 'tenant_id' => $other->id, 'title' => 'Their roof']);

    $this->actingAs($user)->get(route('portal.repairs.index'))->assertSee('My tap')->assertDontSee('Their roof');
    $this->actingAs($user)->get(route('portal.repairs.show', $theirs))->assertForbidden();
    $this->actingAs($user)->get(route('app.maintenance.show', $mine))->assertForbidden();
});

it('lets a tenant cancel only while submitted, then confirm and rate a resolved repair', function () {
    $organization = organization();
    [$user, $tenant, $unit] = leasedTenantUser($organization);
    $submitted = MaintenanceRequest::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id]);
    $acknowledged = MaintenanceRequest::factory()->status(MaintenanceStatus::Acknowledged)->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id]);
    $resolved = MaintenanceRequest::factory()->status(MaintenanceStatus::Resolved)->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id]);

    $this->actingAs($user)->post(route('portal.repairs.cancel', $acknowledged))->assertForbidden();
    $this->actingAs($user)->post(route('portal.repairs.cancel', $submitted))->assertRedirect();
    expect($submitted->fresh()->status)->toBe(MaintenanceStatus::Cancelled);

    $this->actingAs($user)->post(route('portal.repairs.rate', $resolved), ['rating' => '4', 'feedback' => 'Quick fix'])->assertRedirect();
    expect($resolved->fresh())->status->toBe(MaintenanceStatus::Closed)->tenant_rating->toBe(4)->tenant_feedback->toBe('Quick fix');
});

it('records the cost of a resolved repair as an expense', function () {
    $manager = userWithRole(Role::Manager);
    $request = MaintenanceRequest::factory()->status(MaintenanceStatus::InProgress)->create([
        'unit_id' => Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id])->id,
    ]);

    $this->actingAs($manager)->post(route('app.maintenance.status', $request), [
        'status' => 'resolved', 'cost_amount' => '2500000', 'record_expense' => '1',
    ])->assertRedirect();

    $expense = Expense::query()->sole();
    expect($request->fresh()->cost_amount)->toBe(2_500_000)
        ->and($request->fresh()->resolved_at)->not->toBeNull()
        ->and($expense->amount)->toBe(2_500_000)
        ->and($expense->maintenance_request_id)->toBe($request->id)
        ->and($expense->unit_id)->toBe($request->unit_id);
});

it('lets caretakers work on maintenance', function () {
    $caretaker = userWithRole(Role::Caretaker);
    $request = MaintenanceRequest::factory()->create(['unit_id' => Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $caretaker->organization_id])->id])->id]);

    $this->actingAs($caretaker)->get(route('app.maintenance.index'))->assertOk()->assertSee($request->title);
    $this->actingAs($caretaker)->get(route('app.maintenance.index', ['view' => 'kanban']))->assertOk()->assertSee($request->number);
    $this->actingAs($caretaker)->post(route('app.maintenance.status', $request), ['status' => 'acknowledged'])->assertRedirect();
});

it('sends the vendor a signed link that works only for that request and vendor', function () {
    Notification::fake();
    $manager = userWithRole(Role::Manager);
    $unit = Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id]);
    $request = MaintenanceRequest::factory()->status(MaintenanceStatus::Acknowledged)->create(['unit_id' => $unit->id]);
    $other = MaintenanceRequest::factory()->status(MaintenanceStatus::Acknowledged)->create(['unit_id' => $unit->id]);
    $vendor = Vendor::factory()->create(['organization_id' => $manager->organization_id]);

    $this->actingAs($manager)->post(route('app.maintenance.assign', $request), ['vendor_id' => $vendor->id])->assertRedirect();
    Notification::assertSentTo($vendor, VendorJobAssigned::class);
    auth()->logout();

    $link = MaintenanceWorkflow::vendorLink($request->fresh());
    $this->get($link)->assertOk()->assertSee($request->title);

    $this->post($link, ['status' => 'in_progress', 'body' => 'On my way'])->assertRedirect();
    expect($request->fresh()->status)->toBe(MaintenanceStatus::InProgress)
        ->and(MaintenanceUpdate::query()->where('vendor_id', $vendor->id)->exists())->toBeTrue();

    // Tampered: same signature, other request.
    $this->get(str_replace('/vendor/job/'.$request->id, '/vendor/job/'.$other->id, $link))->assertForbidden();
    // Unsigned.
    $this->get(route('vendor.job', ['job' => $request->id, 'vendor' => $vendor->id]))->assertForbidden();
    // Expired after 14 days.
    $this->travel(15)->days();
    $this->get($link)->assertForbidden();
});

it('invalidates the vendor link when the job is reassigned', function () {
    $manager = userWithRole(Role::Manager);
    $request = MaintenanceRequest::factory()->status(MaintenanceStatus::Acknowledged)->create([
        'unit_id' => Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id])->id,
    ]);
    [$first, $second] = Vendor::factory()->count(2)->create(['organization_id' => $manager->organization_id])->all();
    app(MaintenanceWorkflow::class)->assign($request, $manager, null, $first->id);
    $link = MaintenanceWorkflow::vendorLink($request->fresh());

    app(MaintenanceWorkflow::class)->assign($request->fresh(), $manager, null, $second->id);

    $this->get($link)->assertForbidden();
});

it('auto-closes requests resolved for 7 days', function () {
    $request = MaintenanceRequest::factory()->status(MaintenanceStatus::Resolved)->create(['resolved_at' => now()->subDays(8)]);
    $recent = MaintenanceRequest::factory()->status(MaintenanceStatus::Resolved)->create(['resolved_at' => now()->subDays(2)]);

    expect(app(MaintenanceWorkflow::class)->autoCloseResolved())->toBe(1)
        ->and($request->fresh()->status)->toBe(MaintenanceStatus::Closed)
        ->and($recent->fresh()->status)->toBe(MaintenanceStatus::Resolved);
});

it('manages vendors as owner or manager only', function () {
    $manager = userWithRole(Role::Manager);

    $this->actingAs($manager)->post(route('app.vendors.store'), ['name' => 'Emeka Plumbing', 'phone' => '08031112222', 'is_active' => '1'])->assertRedirect();
    $this->actingAs($manager)->get(route('app.vendors.index'))->assertOk()->assertSee('Emeka Plumbing');

    $caretaker = userWithRole(Role::Caretaker, $manager->organization);
    $this->actingAs($caretaker)->get(route('app.vendors.index'))->assertOk();
    $this->actingAs($caretaker)->post(route('app.vendors.store'), ['name' => 'X', 'phone' => '1', 'is_active' => '1'])->assertForbidden();
});

it('serves maintenance photos only to people who can see the request', function () {
    Storage::fake('local');
    $organization = organization();
    [$user, $tenant, $unit] = leasedTenantUser($organization);
    [$otherUser] = leasedTenantUser($organization);
    $request = MaintenanceRequest::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id]);
    $photo = $request->attachments()->create(['path' => 'maintenance/x.jpg']);
    Storage::disk('local')->put('maintenance/x.jpg', 'jpg');

    $this->actingAs($user)->get(route('files.maintenance', $photo))->assertOk();
    $this->actingAs($otherUser)->get(route('files.maintenance', $photo))->assertForbidden();
    $this->actingAs(userWithRole(Role::Caretaker, $organization))->get(route('files.maintenance', $photo))->assertOk();
});
