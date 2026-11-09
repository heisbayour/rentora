<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function propertyPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lekki Gardens',
        'code' => 'lkg',
        'type' => 'residential',
        'address' => '1 Admiralty Way',
        'city' => 'Lagos',
        'state' => 'Lagos',
        'country' => 'Nigeria',
        'is_listed' => '1',
    ], $overrides);
}

it('creates a property with an upper-case code and a JPEG cover image', function () {
    Storage::fake('uploads');
    $manager = userWithRole(Role::Manager);

    $response = $this->actingAs($manager)->post(route('app.properties.store'), propertyPayload([
        'cover_image' => UploadedFile::fake()->image('cover.png', 2400, 1200),
    ]));

    $property = Property::query()->firstOrFail();
    $response->assertRedirect(route('app.properties.show', $property));
    expect($property->code)->toBe('LKG')
        ->and($property->organization_id)->toBe($manager->organization_id)
        ->and($property->cover_image_path)->toMatch('#^properties/[0-9a-f-]{36}\.jpg$#');

    [$width] = getimagesizefromstring(Storage::disk('uploads')->get($property->cover_image_path));
    expect($width)->toBe(1600);
});

it('requires a unique code within the organization only', function () {
    $manager = userWithRole(Role::Manager);
    Property::factory()->create(['organization_id' => $manager->organization_id, 'code' => 'LKG']);
    Property::factory()->create(['code' => 'ABJ']);

    $this->actingAs($manager)->post(route('app.properties.store'), propertyPayload(['code' => 'lkg']))
        ->assertSessionHasErrors('code');
    $this->actingAs($manager)->post(route('app.properties.store'), propertyPayload(['code' => 'ABJ']))
        ->assertSessionHasNoErrors();
});

it('lists properties with search, type filter and occupancy', function () {
    $manager = userWithRole(Role::Manager);
    $lekki = Property::factory()->create(['organization_id' => $manager->organization_id, 'name' => 'Lekki Gardens']);
    Property::factory()->create(['organization_id' => $manager->organization_id, 'name' => 'Wuse Court', 'type' => 'commercial']);
    Unit::factory()->occupied()->create(['property_id' => $lekki->id]);
    Unit::factory()->create(['property_id' => $lekki->id]);

    $this->actingAs($manager)->get(route('app.properties.index', ['q' => 'Lekki']))
        ->assertOk()->assertSee('Lekki Gardens')->assertDontSee('Wuse Court')->assertSee('50%');
    $this->actingAs($manager)->get(route('app.properties.index', ['type' => 'commercial']))
        ->assertOk()->assertSee('Wuse Court')->assertDontSee('Lekki Gardens');
});

it('shows the property page with units and documents tabs', function () {
    $manager = userWithRole(Role::Manager);
    $property = Property::factory()->create(['organization_id' => $manager->organization_id]);
    Unit::factory()->create(['property_id' => $property->id, 'label' => 'Flat 9Z']);

    $this->actingAs($manager)->get(route('app.properties.show', $property))->assertOk()->assertSee('Flat 9Z');
    $this->actingAs($manager)->get(route('app.properties.show', [$property, 'tab' => 'documents']))->assertOk()->assertSee(__('documents.empty_title'));
});

it('updates a property and can remove its cover', function () {
    Storage::fake('uploads');
    $manager = userWithRole(Role::Manager);
    $property = Property::factory()->create(['organization_id' => $manager->organization_id, 'cover_image_path' => 'properties/old.jpg']);
    Storage::disk('uploads')->put('properties/old.jpg', 'x');

    $this->actingAs($manager)->put(route('app.properties.update', $property), propertyPayload(['name' => 'Renamed', 'code' => $property->code, 'remove_cover' => '1']))
        ->assertRedirect(route('app.properties.show', $property));

    expect($property->fresh())->name->toBe('Renamed')->cover_image_path->toBeNull();
    Storage::disk('uploads')->assertMissing('properties/old.jpg');
});

it('soft deletes a property with its units and logs it', function () {
    $owner = userWithRole(Role::Owner);
    $property = Property::factory()->create(['organization_id' => $owner->organization_id]);
    $unit = Unit::factory()->create(['property_id' => $property->id]);

    $this->actingAs($owner)->delete(route('app.properties.destroy', $property))->assertRedirect(route('app.properties.index'));

    expect(Property::query()->find($property->id))->toBeNull()
        ->and(Property::withTrashed()->find($property->id))->not->toBeNull()
        ->and(Unit::withTrashed()->find($unit->id)->trashed())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'property.deleted')->exists())->toBeTrue();
});

it('keeps caretakers out of property management', function () {
    $caretaker = userWithRole(Role::Caretaker);
    $property = Property::factory()->create(['organization_id' => $caretaker->organization_id]);

    $this->actingAs($caretaker)->get(route('app.properties.index'))->assertForbidden();
    $this->actingAs($caretaker)->get(route('app.properties.show', $property))->assertForbidden();
    $this->actingAs($caretaker)->post(route('app.properties.store'), propertyPayload())->assertForbidden();
});

it('returns 404 for a property in another organization', function () {
    $manager = userWithRole(Role::Manager);
    $foreign = Property::factory()->create();

    $this->actingAs($manager)->get(route('app.properties.show', $foreign))->assertNotFound();
    $this->actingAs($manager)->put(route('app.properties.update', $foreign), propertyPayload())->assertNotFound();
    $this->actingAs($manager)->delete(route('app.properties.destroy', $foreign))->assertNotFound();
});
