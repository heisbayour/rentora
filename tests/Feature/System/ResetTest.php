<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Inquiry;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceUpdate;
use App\Models\Organization;
use App\Models\OutboundMessage;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\UnitPhoto;
use App\Models\User;
use App\Services\SequenceService;
use App\Support\Installation;
use App\Support\PrivateFiles;
use App\Support\PublicUploads;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

const RESET_KEY = 'test-install-key';

/** Every table the reset empties for the organization. */
const RESET_TABLES = [
    'properties', 'units', 'tenants', 'leases', 'invoices', 'payments', 'maintenance_requests', 'vendors', 'expenses',
    'announcements', 'outbound_messages', 'reminder_logs', 'inquiries', 'documents', 'activity_logs',
];

/** @return array{organization: Organization, owner: User} */
function seededDemo(): array
{
    Storage::fake(PrivateFiles::DISK);
    Storage::fake(PublicUploads::DISK);
    test()->seed(DatabaseSeeder::class);

    $owner = User::query()->where('email', 'owner@demo.test')->firstOrFail();
    $organization = Organization::query()->findOrFail($owner->organization_id);
    $id = $organization->id;

    // Records the demo does not create, each with a real file where one exists.
    $unit = Unit::query()->withoutGlobalScopes()->where('organization_id', $id)->firstOrFail();
    $photo = UnitPhoto::factory()->create(['unit_id' => $unit->id]);
    Storage::disk(PublicUploads::DISK)->put($photo->path, 'jpg');
    $property = Property::query()->withoutGlobalScopes()->findOrFail($unit->property_id);
    $property->forceFill(['cover_image_path' => 'properties/cover.jpg'])->save();
    Storage::disk(PublicUploads::DISK)->put('properties/cover.jpg', 'jpg');
    $organization->forceFill(['logo_path' => 'logos/logo.png'])->save();
    Storage::disk(PublicUploads::DISK)->put('logos/logo.png', 'png');

    $request = MaintenanceRequest::query()->withoutGlobalScopes()->where('organization_id', $id)->firstOrFail();
    $attachment = MaintenanceAttachment::factory()->create(['maintenance_request_id' => $request->id]);
    Storage::disk(PrivateFiles::DISK)->put($attachment->path, 'jpg');
    MaintenanceUpdate::factory()->create(['maintenance_request_id' => $request->id]);

    $payment = Payment::query()->withoutGlobalScopes()->where('organization_id', $id)->firstOrFail();
    $payment->forceFill(['proof_path' => 'proofs/proof.pdf'])->save();
    Storage::disk(PrivateFiles::DISK)->put('proofs/proof.pdf', 'pdf');

    $document = Document::factory()->create(['organization_id' => $id, 'documentable_type' => $property->getMorphClass(), 'documentable_id' => $property->id]);
    Storage::disk(PrivateFiles::DISK)->put($document->path, 'pdf');

    Inquiry::factory()->create(['organization_id' => $id, 'unit_id' => $unit->id]);
    Announcement::factory()->create(['organization_id' => $id]);
    OutboundMessage::factory()->create(['organization_id' => $id]);
    DB::table('reminder_logs')->insert(['organization_id' => $id, 'remindable_type' => 'invoice', 'remindable_id' => 1, 'kind' => 'due_3', 'sent_at' => now()]);
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(), 'type' => 'test', 'notifiable_type' => $owner->getMorphClass(), 'notifiable_id' => $owner->id,
        'data' => '{}', 'created_at' => now(), 'updated_at' => now(),
    ]);
    ActivityLog::factory()->create(['organization_id' => $id]);
    Setting::query()->create(['organization_id' => $id, 'key' => 'bank.account_number', 'value' => '0123456789']);

    return ['organization' => $organization, 'owner' => $owner];
}

function resetForm(array $overrides = []): array
{
    return ['key' => RESET_KEY, 'confirm' => 'RESET', 'password' => 'password', ...$overrides];
}

it('lists what will be deleted with counts', function () {
    ['owner' => $owner] = seededDemo();

    $this->actingAs($owner)->get(route('system.reset', ['key' => RESET_KEY]))
        ->assertOk()
        ->assertSee(__('reset.warning_title'))
        ->assertSee('phpMyAdmin')
        ->assertSee(__('reset.labels.properties'))
        ->assertSee(__('reset.labels.tenant_users'))
        ->assertSee('data-count="units"', false);
});

it('deletes every business record, file and non-owner user, and keeps the organization, settings and owners', function () {
    ['organization' => $organization, 'owner' => $owner] = seededDemo();
    $id = $organization->id;
    $otherOwner = userWithRole(Role::Owner, $organization);
    $settingsBefore = Setting::query()->withoutGlobalScopes()->where('organization_id', $id)->count();
    $files = [
        ...collect(Storage::disk(PrivateFiles::DISK)->allFiles())->all(),
    ];
    expect($files)->not->toBeEmpty();

    // A second organization must not be touched.
    $foreign = Unit::factory()->create();

    $this->actingAs($owner)->post(route('system.reset.run'), resetForm())
        ->assertRedirect(route('system.reset', ['key' => RESET_KEY]))
        ->assertSessionHas('reset.summary');

    foreach (RESET_TABLES as $table) {
        $left = DB::table($table)->where('organization_id', $id)->when($table === 'activity_logs', fn ($q) => $q->where('action', '!=', 'system.reset'))->count();
        expect($left)->toBe(0, "{$table} still has {$left} rows");
    }
    expect(DB::table('unit_photos')->count())->toBe(0)
        ->and(DB::table('payment_allocations')->count())->toBe(0)
        ->and(DB::table('maintenance_updates')->count())->toBe(0)
        ->and(DB::table('maintenance_attachments')->count())->toBe(0)
        ->and(DB::table('announcement_tenant')->count())->toBe(0)
        ->and(DB::table('notifications')->count())->toBe(0);

    // Files of the deleted records are gone; the logo stays.
    expect(Storage::disk(PrivateFiles::DISK)->allFiles())->toBe([])
        ->and(Storage::disk(PublicUploads::DISK)->allFiles())->toBe(['logos/logo.png']);

    // Only owners are left.
    $roles = User::query()->withoutGlobalScopes()->where('organization_id', $id)->pluck('role')->map(fn ($r) => $r->value)->unique()->values()->all();
    expect($roles)->toBe(['owner'])
        ->and(User::query()->whereKey([$owner->id, $otherOwner->id])->count())->toBe(2);

    // Kept: organization with its logo, settings, the install lock, the other organization.
    expect($organization->fresh()->logo_path)->toBe('logos/logo.png')
        ->and(Setting::query()->withoutGlobalScopes()->where('organization_id', $id)->count())->toBe($settingsBefore)
        ->and(Installation::isInstalled())->toBeTrue()
        ->and(Unit::query()->withoutGlobalScopes()->whereKey($foreign->id)->exists())->toBeTrue();

    // One audit entry, written after the wipe.
    $logs = ActivityLog::query()->withoutGlobalScopes()->where('organization_id', $id)->get();
    expect($logs)->toHaveCount(1)
        ->and($logs->first()->action)->toBe('system.reset')
        ->and($logs->first()->user_id)->toBe($owner->id);
});

it('restarts invoice, receipt and repair numbers at 000001', function () {
    ['organization' => $organization, 'owner' => $owner] = seededDemo();
    $year = now()->format('Y');
    expect(DB::table('sequences')->where('organization_id', $organization->id)->where('next_value', '>', 1)->exists())->toBeTrue();

    $this->actingAs($owner)->post(route('system.reset.run'), resetForm());

    $sequences = app(SequenceService::class);
    expect($sequences->next(SequenceService::INVOICE, null, $organization->id))->toBe("INV-{$year}-000001")
        ->and($sequences->next(SequenceService::RECEIPT, null, $organization->id))->toBe("RCT-{$year}-000001")
        ->and($sequences->next(SequenceService::MAINTENANCE, null, $organization->id))->toBe("MR-{$year}-000001");
});

it('creates an extra owner and shows the temporary password once', function () {
    ['owner' => $owner] = seededDemo();

    $this->actingAs($owner)->post(route('system.reset.run'), resetForm([
        'new_owner_name' => 'Client Owner', 'new_owner_email' => 'Client@Example.com',
    ]));

    $summary = session('reset.summary');
    $client = User::query()->where('email', 'client@example.com')->firstOrFail();
    expect($client->role)->toBe(Role::Owner)
        ->and($client->organization_id)->toBe($owner->organization_id)
        ->and(password_verify($summary['new_owner']['password'], $client->password))->toBeTrue();

    $this->actingAs($owner)->get(route('system.reset', ['key' => RESET_KEY]))
        ->assertSee($summary['new_owner']['password']);
    $this->actingAs($owner)->get(route('system.reset', ['key' => RESET_KEY]))
        ->assertDontSee($summary['new_owner']['password']);
});

it('needs RESET typed exactly and the owner password', function (array $input, string $field) {
    ['organization' => $organization, 'owner' => $owner] = seededDemo();

    $this->actingAs($owner)->post(route('system.reset.run'), resetForm($input))->assertSessionHasErrors($field);

    expect(Property::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->exists())->toBeTrue();
})->with([
    'lowercase' => [['confirm' => 'reset'], 'confirm'],
    'missing' => [['confirm' => ''], 'confirm'],
    'wrong password' => [['password' => 'wrong'], 'password'],
]);

it('forbids managers, caretakers and tenants', function (Role $role) {
    $organization = organization();
    Property::factory()->create(['organization_id' => $organization->id]);
    $user = userWithRole($role, $organization);

    $this->actingAs($user)->get(route('system.reset', ['key' => RESET_KEY]))->assertForbidden();
    $this->actingAs($user)->post(route('system.reset.run'), resetForm())->assertForbidden();

    expect(Property::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->exists())->toBeTrue();
})->with([Role::Manager, Role::Caretaker, Role::Tenant]);

it('forbids an owner without the install key', function (?string $key) {
    $organization = organization();
    Property::factory()->create(['organization_id' => $organization->id]);
    $owner = userWithRole(Role::Owner, $organization);

    $this->actingAs($owner)->get(route('system.reset', array_filter(['key' => $key])))->assertForbidden();
    $this->actingAs($owner)->post(route('system.reset.run'), resetForm(['key' => $key]))->assertForbidden();

    expect(Property::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->exists())->toBeTrue();
})->with([null, 'wrong-key']);

it('sends guests to the login page', function () {
    $this->get(route('system.reset', ['key' => RESET_KEY]))->assertRedirect(route('login'));
});

it('is not linked from the console', function () {
    $owner = userWithRole(Role::Owner);

    $this->actingAs($owner)->get(route('app.dashboard'))->assertDontSee('system/reset');
    $this->actingAs($owner)->get(route('app.health'))->assertDontSee('system/reset');
});
