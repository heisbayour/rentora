<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Document;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lists and unlists a vacant unit', function () {
    $manager = userWithRole(Role::Manager);
    $unit = Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id]);

    $this->actingAs($manager)->get(route('app.listings.index'))->assertOk()->assertSee($unit->label);
    $this->actingAs($manager)->patch(route('app.listings.toggle', $unit))->assertRedirect();
    expect($unit->fresh()->is_listed)->toBeTrue();

    $this->actingAs($manager)->patch(route('app.listings.toggle', $unit));
    expect($unit->fresh()->is_listed)->toBeFalse();
});

it('will not list an occupied unit', function () {
    $manager = userWithRole(Role::Manager);
    $unit = Unit::factory()->occupied()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id]);

    $this->actingAs($manager)->patch(route('app.listings.toggle', $unit))->assertSessionHasErrors('unit');
    expect($unit->fresh()->is_listed)->toBeFalse();
});

it('scopes the publicly listed units', function () {
    $property = Property::factory()->create();
    Unit::factory()->listed()->create(['property_id' => $property->id, 'label' => 'Shown']);
    Unit::factory()->listed()->occupied()->create(['property_id' => $property->id, 'label' => 'Occupied']);
    Unit::factory()->create(['property_id' => $property->id, 'label' => 'Unlisted']);

    expect(Unit::query()->publiclyListed()->pluck('label')->all())->toBe(['Shown']);
});

it('uploads a private document and serves it only through the file controller', function () {
    Storage::fake('local');
    $manager = userWithRole(Role::Manager);
    $property = Property::factory()->create(['organization_id' => $manager->organization_id]);

    $this->actingAs($manager)->post(route('app.documents.store'), [
        'documentable_type' => 'property',
        'documentable_id' => $property->id,
        'file' => UploadedFile::fake()->create('survey.pdf', 200, 'application/pdf'),
        'name' => 'Survey plan',
        'visibility' => 'internal',
    ])->assertRedirect();

    $document = Document::query()->firstOrFail();
    expect($document->documentable_type)->toBe('property')
        ->and($document->path)->toStartWith('documents/property/')
        ->and($document->uploaded_by)->toBe($manager->id);
    Storage::disk('local')->assertExists($document->path);

    $response = $this->actingAs($manager)->get(route('files.documents', $document));
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('Survey plan.pdf');
});

it('redirects guests to login for private files', function () {
    $document = Document::factory()->create();

    $this->get(route('files.documents', $document))->assertRedirect(route('login'));
});

it('hides documents from caretakers, tenants and other organizations', function () {
    Storage::fake('local');
    $manager = userWithRole(Role::Manager);
    $document = Document::factory()->create([
        'documentable_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id,
    ]);
    Storage::disk('local')->put($document->path, 'pdf');

    $this->actingAs(userWithRole(Role::Caretaker, $manager->organization))->get(route('files.documents', $document))->assertForbidden();
    $this->actingAs(userWithRole(Role::Tenant, $manager->organization))->get(route('files.documents', $document))->assertForbidden();
    $this->actingAs(userWithRole(Role::Manager))->get(route('files.documents', $document))->assertNotFound();
});

it('rejects disallowed document types', function () {
    $manager = userWithRole(Role::Manager);
    $property = Property::factory()->create(['organization_id' => $manager->organization_id]);

    $this->actingAs($manager)->post(route('app.documents.store'), [
        'documentable_type' => 'property',
        'documentable_id' => $property->id,
        'file' => UploadedFile::fake()->create('macro.exe', 10, 'application/octet-stream'),
        'visibility' => 'internal',
    ])->assertSessionHasErrors('file');
});

it('will not attach a document to another organization\'s property', function () {
    $manager = userWithRole(Role::Manager);
    $foreign = Property::factory()->create();

    $this->actingAs($manager)->post(route('app.documents.store'), [
        'documentable_type' => 'property',
        'documentable_id' => $foreign->id,
        'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        'visibility' => 'internal',
    ])->assertNotFound();
});

it('deletes a document and its file', function () {
    Storage::fake('local');
    $owner = userWithRole(Role::Owner);
    $document = Document::factory()->create([
        'documentable_id' => Property::factory()->create(['organization_id' => $owner->organization_id])->id,
    ]);
    Storage::disk('local')->put($document->path, 'pdf');

    $this->actingAs($owner)->delete(route('app.documents.destroy', $document))->assertRedirect();

    expect(Document::query()->find($document->id))->toBeNull();
    Storage::disk('local')->assertMissing($document->path);
});
