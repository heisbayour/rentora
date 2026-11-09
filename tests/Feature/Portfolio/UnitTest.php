<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\UnitStatus;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function unitPayload(Property $property, array $overrides = []): array
{
    return array_merge([
        'property_id' => $property->id,
        'label' => 'Flat 3B',
        'type' => 'flat',
        'bedrooms' => 3,
        'bathrooms' => 2,
        'rent_amount' => '250000000',
        'rent_frequency' => 'annual',
        'service_charge_amount' => '15000000',
        'caution_deposit_amount' => '0',
        'status' => 'vacant',
        'is_listed' => '1',
    ], $overrides);
}

it('creates a unit storing money in kobo', function () {
    $manager = userWithRole(Role::Manager);
    $property = Property::factory()->create(['organization_id' => $manager->organization_id]);

    $this->actingAs($manager)->post(route('app.units.store'), unitPayload($property))->assertRedirect();

    $unit = Unit::query()->firstOrFail();
    expect($unit->rent_amount)->toBe(250_000_000)
        ->and($unit->rent()->format())->toBe('₦2,500,000.00')
        ->and($unit->is_listed)->toBeTrue();
});

it('requires a unique label within a property', function () {
    $manager = userWithRole(Role::Manager);
    $property = Property::factory()->create(['organization_id' => $manager->organization_id]);
    Unit::factory()->create(['property_id' => $property->id, 'label' => 'Flat 3B']);
    $other = Property::factory()->create(['organization_id' => $manager->organization_id]);

    $this->actingAs($manager)->post(route('app.units.store'), unitPayload($property))->assertSessionHasErrors('label');
    $this->actingAs($manager)->post(route('app.units.store'), unitPayload($other))->assertSessionHasNoErrors();
});

it('refuses a property from another organization', function () {
    $manager = userWithRole(Role::Manager);
    $foreign = Property::factory()->create();

    $this->actingAs($manager)->post(route('app.units.store'), unitPayload($foreign))->assertSessionHasErrors('property_id');
});

it('unlists a unit that is not vacant or reserved', function () {
    $manager = userWithRole(Role::Manager);
    $property = Property::factory()->create(['organization_id' => $manager->organization_id]);

    $this->actingAs($manager)->post(route('app.units.store'), unitPayload($property, ['status' => 'occupied', 'is_listed' => '1']));

    expect(Unit::query()->firstOrFail()->is_listed)->toBeFalse();
});

it('lets caretakers read units but not change them', function () {
    $caretaker = userWithRole(Role::Caretaker);
    $property = Property::factory()->create(['organization_id' => $caretaker->organization_id]);
    $unit = Unit::factory()->create(['property_id' => $property->id, 'label' => 'Room 7']);

    $this->actingAs($caretaker)->get(route('app.units.index'))->assertOk()->assertSee('Room 7');
    $this->actingAs($caretaker)->get(route('app.units.show', $unit))->assertOk();
    $this->actingAs($caretaker)->get(route('app.units.create'))->assertForbidden();
    $this->actingAs($caretaker)->put(route('app.units.update', $unit), unitPayload($property))->assertForbidden();
    $this->actingAs($caretaker)->delete(route('app.units.destroy', $unit))->assertForbidden();
});

it('filters units by status and property', function () {
    $manager = userWithRole(Role::Manager);
    $a = Property::factory()->create(['organization_id' => $manager->organization_id]);
    $b = Property::factory()->create(['organization_id' => $manager->organization_id]);
    Unit::factory()->occupied()->create(['property_id' => $a->id, 'label' => 'Busy 1']);
    Unit::factory()->create(['property_id' => $a->id, 'label' => 'Free 1']);
    Unit::factory()->create(['property_id' => $b->id, 'label' => 'Other 1']);

    $this->actingAs($manager)->get(route('app.units.index', ['status' => 'occupied']))
        ->assertSee('Busy 1')->assertDontSee('Free 1');
    $this->actingAs($manager)->get(route('app.units.index', ['property' => $b->id]))
        ->assertSee('Other 1')->assertDontSee('Busy 1');
});

it('soft deletes a unit', function () {
    $manager = userWithRole(Role::Manager);
    $unit = Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id]);

    $this->actingAs($manager)->delete(route('app.units.destroy', $unit))->assertRedirect();

    expect(Unit::withTrashed()->find($unit->id)->trashed())->toBeTrue();
});

it('quick-adds several units at once, skipping blank rows', function () {
    $manager = userWithRole(Role::Manager);
    $property = Property::factory()->create(['organization_id' => $manager->organization_id]);

    $this->actingAs($manager)->get(route('app.properties.quick-add', $property))->assertOk();
    $this->actingAs($manager)->post(route('app.properties.quick-add.store', $property), ['units' => [
        ['label' => 'A1', 'type' => 'flat', 'rent_amount' => '100000000', 'rent_frequency' => 'annual'],
        ['label' => '', 'type' => 'flat', 'rent_amount' => '', 'rent_frequency' => 'annual'],
        ['label' => 'A2', 'type' => 'room', 'rent_amount' => '2000000', 'rent_frequency' => 'monthly'],
    ]])->assertRedirect(route('app.properties.show', $property));

    expect($property->units()->pluck('label')->sort()->values()->all())->toBe(['A1', 'A2'])
        ->and($property->units()->where('label', 'A2')->first()->status)->toBe(UnitStatus::Vacant);
});

it('rejects the whole quick-add batch when a row is invalid', function () {
    $manager = userWithRole(Role::Manager);
    $property = Property::factory()->create(['organization_id' => $manager->organization_id]);

    $this->actingAs($manager)->post(route('app.properties.quick-add.store', $property), ['units' => [
        ['label' => 'A1', 'type' => 'flat', 'rent_amount' => '100', 'rent_frequency' => 'annual'],
        ['label' => 'a1', 'type' => 'flat', 'rent_amount' => '100', 'rent_frequency' => 'annual'],
    ]])->assertSessionHasErrors(['units', 'units.1.label']);

    expect($property->units()->count())->toBe(0);
});

it('caps quick-add at 50 units', function () {
    $manager = userWithRole(Role::Manager);
    $property = Property::factory()->create(['organization_id' => $manager->organization_id]);
    $rows = array_map(fn ($i) => ['label' => "U{$i}", 'type' => 'room', 'rent_amount' => '100', 'rent_frequency' => 'monthly'], range(1, 51));

    $this->actingAs($manager)->post(route('app.properties.quick-add.store', $property), ['units' => $rows])
        ->assertSessionHasErrors('units');
});

it('uploads unit photos as JPEG, reorders and deletes them', function () {
    Storage::fake('uploads');
    $manager = userWithRole(Role::Manager);
    $unit = Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id]);

    $this->actingAs($manager)->post(route('app.units.photos.store', $unit), ['photos' => [
        UploadedFile::fake()->image('one.png', 800, 600),
        UploadedFile::fake()->image('two.webp', 800, 600),
    ]])->assertRedirect(route('app.units.show', $unit));

    [$first, $second] = $unit->photos()->get()->all();
    expect($first->path)->toMatch('#^units/[0-9a-f-]{36}\.jpg$#');
    Storage::disk('uploads')->assertExists($first->path);

    $this->actingAs($manager)->patch(route('app.units.photos.move', [$unit, $second]), ['direction' => 'up']);
    expect($unit->photos()->first()->id)->toBe($second->id);

    $this->actingAs($manager)->delete(route('app.units.photos.destroy', [$unit, $first]));
    expect(UnitPhoto::query()->find($first->id))->toBeNull();
    Storage::disk('uploads')->assertMissing($first->path);
});

it('rejects non-image photo uploads', function () {
    $manager = userWithRole(Role::Manager);
    $unit = Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id]);

    $this->actingAs($manager)->post(route('app.units.photos.store', $unit), ['photos' => [
        UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
    ]])->assertSessionHasErrors('photos.0');
});

it('does not let a photo of one unit be changed through another unit', function () {
    $manager = userWithRole(Role::Manager);
    $property = Property::factory()->create(['organization_id' => $manager->organization_id]);
    $unit = Unit::factory()->create(['property_id' => $property->id]);
    $photo = UnitPhoto::factory()->create(['unit_id' => Unit::factory()->create(['property_id' => $property->id])->id]);

    $this->actingAs($manager)->delete(route('app.units.photos.destroy', [$unit, $photo]))->assertNotFound();
});
