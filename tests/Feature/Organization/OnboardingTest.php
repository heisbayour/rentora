<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Organization;
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

it('saves organization details and logo, then finishes', function () {
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
    ])->assertRedirect(route('app.dashboard'));

    $organization = $owner->organization->fresh();
    expect($organization->name)->toBe('Adebayo Homes')
        ->and($organization->primary_color)->toBe('#0B8043')
        ->and($organization->logo_path)->toStartWith('logos/');
    Storage::disk('uploads')->assertExists($organization->logo_path);

    $this->actingAs($owner)->get('/app')->assertOk()->assertSee('11 128 67', false);
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
