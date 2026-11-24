<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\UnitStatus;
use App\Models\Inquiry;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Unit;
use App\Notifications\InquiryReceived;
use Illuminate\Support\Facades\Notification;

function listedUnit(?Organization $organization = null, array $attributes = []): Unit
{
    $organization ??= organization(['name' => 'Demo Properties Ltd', 'website_headline' => 'Homes in Minna']);
    $property = Property::factory()->create(['organization_id' => $organization->id, 'name' => 'Bosso Court']);

    return Unit::factory()->create(['property_id' => $property->id, 'is_listed' => true, 'status' => UnitStatus::Vacant, ...$attributes]);
}

it('shows the home page with featured vacant listings', function () {
    $unit = listedUnit();
    $hidden = Unit::factory()->create(['property_id' => $unit->property_id, 'is_listed' => true, 'status' => UnitStatus::Occupied, 'label' => 'Taken']);

    $this->get('/')->assertOk()
        ->assertSee('Homes in Minna')
        ->assertSee('Bosso Court')
        ->assertSee(route('public.unit', $unit), false)
        ->assertDontSee(route('public.unit', $hidden), false);
});

it('filters listings by type, bedrooms and maximum rent', function () {
    $flat = listedUnit(null, ['type' => 'flat', 'bedrooms' => 3, 'rent_amount' => 150_000_000, 'label' => 'Flat 3']);
    $room = Unit::factory()->create(['property_id' => $flat->property_id, 'is_listed' => true, 'status' => UnitStatus::Vacant, 'type' => 'room', 'bedrooms' => 1, 'rent_amount' => 30_000_000, 'label' => 'Room 1']);

    $this->get(route('public.listings', ['type' => 'flat']))->assertSee(route('public.unit', $flat), false)->assertDontSee(route('public.unit', $room), false);
    $this->get(route('public.listings', ['bedrooms' => 2]))->assertSee(route('public.unit', $flat), false)->assertDontSee(route('public.unit', $room), false);
    $this->get(route('public.listings', ['max_rent' => 500000]))->assertSee(route('public.unit', $room), false)->assertDontSee(route('public.unit', $flat), false);
});

it('shows a listed unit and hides unlisted ones', function () {
    $unit = listedUnit();
    $unlisted = Unit::factory()->create(['property_id' => $unit->property_id, 'is_listed' => false, 'status' => UnitStatus::Vacant]);

    $this->get(route('public.unit', $unit))->assertOk()->assertSee($unit->label);
    $this->get(route('public.unit', $unlisted))->assertNotFound();
});

it('stores an inquiry and notifies owners and managers', function () {
    Notification::fake();
    $unit = listedUnit();
    $owner = userWithRole(Role::Owner, $unit->organization);
    $caretaker = userWithRole(Role::Caretaker, $unit->organization);

    $this->post(route('public.inquire', $unit), ['name' => 'Musa', 'phone' => '08031234567', 'message' => 'Can I view on Friday?'])
        ->assertRedirect(route('public.unit', $unit));

    $inquiry = Inquiry::query()->sole();
    expect($inquiry->unit_id)->toBe($unit->id)->and($inquiry->organization_id)->toBe($unit->organization_id);
    Notification::assertSentTo($owner, InquiryReceived::class);
    Notification::assertNotSentTo($caretaker, InquiryReceived::class);
});

it('drops honeypot submissions silently', function () {
    $unit = listedUnit();

    $this->post(route('public.inquire', $unit), ['name' => 'Bot', 'email' => 'bot@example.test', 'message' => 'Buy now', 'website' => 'http://spam.test'])
        ->assertRedirect();

    expect(Inquiry::query()->count())->toBe(0);
});

it('limits inquiries to 3 per hour per IP', function () {
    $unit = listedUnit();
    $payload = ['name' => 'Musa', 'phone' => '08031234567', 'message' => 'Hello'];

    foreach (range(1, 3) as $i) {
        $this->post(route('public.inquire', $unit), $payload)->assertRedirect();
    }
    $this->post(route('public.inquire', $unit), $payload)->assertStatus(429);
    expect(Inquiry::query()->count())->toBe(3);
});

it('sends visitors to the login page when the site is off', function () {
    $unit = listedUnit();
    Setting::query()->create(['organization_id' => $unit->organization_id, 'key' => 'website.enabled', 'value' => '0']);

    $this->get('/')->assertRedirect(route('login'));
    $this->get(route('public.listings'))->assertNotFound();
    $this->get(route('public.unit', $unit))->assertNotFound();
});

it('lets managers work the inquiry inbox but not caretakers', function () {
    $unit = listedUnit();
    $inquiry = Inquiry::factory()->create(['organization_id' => $unit->organization_id, 'unit_id' => $unit->id]);
    $manager = userWithRole(Role::Manager, $unit->organization);
    $caretaker = userWithRole(Role::Caretaker, $unit->organization);

    $this->actingAs($manager)->get(route('app.inquiries.index'))->assertOk()->assertSee($inquiry->name);
    $this->actingAs($manager)->patch(route('app.inquiries.update', $inquiry), ['status' => 'contacted'])->assertRedirect();
    expect($inquiry->fresh()->status->value)->toBe('contacted');
    $this->actingAs($caretaker)->get(route('app.inquiries.index'))->assertForbidden();
});
