<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function freshOwner(): User
{
    $organization = Organization::factory()->create();

    return User::factory()->owner()->create(['organization_id' => $organization->id]);
}

it('sends a new owner to the first-run wizard', function () {
    $this->actingAs(freshOwner())->get('/app')
        ->assertRedirect(route('app.onboarding.show', 'organization'));
});

it('does not send managers to the wizard', function () {
    $owner = freshOwner();
    $manager = userWithRole(Role::Manager, $owner->organization);

    $this->actingAs($manager)->get('/app')->assertOk();
});

it('forbids managers from the wizard', function () {
    $owner = freshOwner();
    $manager = userWithRole(Role::Manager, $owner->organization);

    $this->actingAs($manager)->get(route('app.onboarding.show', 'organization'))->assertForbidden();
});

it('walks the owner through organization, first property and units, then finishes', function () {
    Storage::fake('uploads');
    $owner = freshOwner();

    $this->actingAs($owner)->post(route('app.onboarding.store', 'organization'), [
        'name' => 'Adebayo Homes', 'primary_color' => '#1A73E8', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
    ])->assertRedirect(route('app.onboarding.show', 'property'));

    $this->actingAs($owner)->get(route('app.onboarding.show', 'property'))->assertOk();
    $this->actingAs($owner)->post(route('app.onboarding.store', 'property'), [
        'name' => 'Lekki Gardens', 'code' => 'lkg', 'type' => 'residential', 'address' => '1 Admiralty Way', 'country' => 'Nigeria',
    ])->assertRedirect(route('app.onboarding.show', 'units'));

    $this->actingAs($owner)->get(route('app.onboarding.show', 'units'))->assertOk()->assertSee('Lekki Gardens');
    $this->actingAs($owner)->post(route('app.onboarding.store', 'units'), ['units' => [
        ['label' => 'Flat 1', 'type' => 'flat', 'rent_amount' => '150000000', 'rent_frequency' => 'annual'],
        ['label' => '', 'type' => 'flat', 'rent_amount' => '', 'rent_frequency' => 'annual'],
        ['label' => 'Shop 1', 'type' => 'shop', 'rent_amount' => '5000000', 'rent_frequency' => 'monthly'],
    ]])->assertRedirect(route('app.dashboard'));

    $property = Property::query()->withoutGlobalScopes()->where('code', 'LKG')->firstOrFail();
    expect($property->units()->count())->toBe(2);
    $this->actingAs($owner)->get('/app')->assertOk();
});

it('resumes the wizard at the first unfinished step', function () {
    $owner = freshOwner();

    $this->actingAs($owner)->post(route('app.onboarding.store', 'organization'), [
        'name' => 'Adebayo Homes', 'primary_color' => '#1A73E8', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
    ]);

    $this->actingAs($owner)->get('/app')->assertRedirect(route('app.onboarding.show', 'property'));
});

it('saves organization details and logo', function () {
    Storage::fake('uploads');
    $owner = freshOwner();

    $this->actingAs($owner)->get(route('app.onboarding.show', 'organization'))->assertOk();

    $this->actingAs($owner)->post(route('app.onboarding.store', 'organization'), [
        'name' => 'Adebayo Homes',
        'email' => 'hello@adebayo.test',
        'primary_color' => '#0B8043',
        'timezone' => 'Africa/Lagos',
        'currency' => 'NGN',
        'logo' => UploadedFile::fake()->image('logo.png', 400, 200),
    ])->assertRedirect(route('app.onboarding.show', 'property'));

    $organization = $owner->organization->fresh();
    expect($organization->name)->toBe('Adebayo Homes')
        ->and($organization->primary_color)->toBe('#0B8043')
        ->and($organization->logo_path)->toStartWith('logos/');
    Storage::disk('uploads')->assertExists($organization->logo_path);

    $this->actingAs($owner)->get(route('app.onboarding.show', 'property'))->assertOk()->assertSee('11 128 67', false);
});

it('validates the organization step', function () {
    $owner = freshOwner();

    $this->actingAs($owner)->post(route('app.onboarding.store', 'organization'), [
        'name' => '',
        'primary_color' => 'red',
        'timezone' => 'Mars/Base',
        'currency' => 'XXX',
    ])->assertSessionHasErrors(['name', 'primary_color', 'timezone', 'currency']);
});

it('returns 404 for an unknown step', function () {
    $this->actingAs(freshOwner())->get(route('app.onboarding.show', 'nope'))->assertNotFound();
});
