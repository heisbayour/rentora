<?php

declare(strict_types=1);

use App\Enums\AnnouncementAudience;
use App\Enums\LeaseStatus;
use App\Enums\MessageStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\OutboundMessage;
use App\Models\Payment;
use App\Models\Property;
use App\Models\ReminderLog;
use App\Models\SystemState;
use App\Models\Tenant;
use App\Models\Unit;
use App\Notifications\Channels\OutboxEmailChannel;
use App\Notifications\Channels\OutboxSmsChannel;
use App\Notifications\ProofSubmitted;
use App\Notifications\RentReminder;
use App\Notifications\TenantInvitation;
use App\Services\Messaging\AnnouncementAudienceResolver;
use App\Services\Messaging\Outbox;
use App\Services\Messaging\RentReminders;
use App\Services\Scheduling\LazyScheduler;
use App\Services\Scheduling\TaskRegistry;
use App\Services\SettingsService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/** A tenant with a portal login and one open invoice due on $due. */
function tenantWithDueInvoice(CarbonInterface $due, array $tenantAttributes = []): array
{
    $organization = organization();
    $user = userWithRole(Role::Tenant, $organization);
    $tenant = Tenant::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, ...$tenantAttributes]);
    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'due_date' => $due, 'amount' => 5_000_000]);

    return [$tenant, $invoice, $user];
}

it('sends each rent reminder kind once per invoice', function () {
    [$tenant, $invoice] = tenantWithDueInvoice(today()->addDays(7));

    expect(app(RentReminders::class)->run())->toBe(1)
        ->and(app(RentReminders::class)->run())->toBe(0);

    expect(ReminderLog::query()->where('kind', 'pre_due_7')->count())->toBe(1)
        ->and(OutboundMessage::query()->withoutGlobalScopes()->where('channel', 'email')->count())->toBe(1)
        ->and(OutboundMessage::query()->withoutGlobalScopes()->where('channel', 'sms')->count())->toBe(1)
        ->and(DatabaseNotification::query()->count())->toBe(1);

    // Three days later the next kind goes out, still only once.
    $this->travel(4)->days();
    expect(app(RentReminders::class)->run())->toBe(1)
        ->and(app(RentReminders::class)->run())->toBe(0)
        ->and(ReminderLog::query()->pluck('kind')->sort()->values()->all())->toBe(['pre_due_3', 'pre_due_7']);
});

it('maps days past due to reminder kinds', function (int $days, ?string $kind) {
    expect(RentReminders::kindFor($days))->toBe($kind);
})->with([
    [-8, null], [-7, 'pre_due_7'], [-4, 'pre_due_7'], [-3, 'pre_due_3'], [-1, 'pre_due_3'], [0, 'due_today'],
    [1, null], [3, 'overdue_3'], [6, 'overdue_3'], [7, 'overdue_7'], [13, 'overdue_7'], [14, null],
]);

it('does not remind about paid invoices', function () {
    [, $invoice] = tenantWithDueInvoice(today());
    $invoice->forceFill(['amount_paid' => $invoice->amount, 'status' => 'paid'])->save();

    expect(app(RentReminders::class)->run())->toBe(0);
});

it('respects tenant preferences for non-critical messages but always sends critical ones', function () {
    Notification::fake();
    [$tenant, $invoice] = tenantWithDueInvoice(today(), ['notification_prefs' => ['sms' => false, 'email' => false]]);

    app(RentReminders::class)->run();
    Notification::assertSentTo($tenant, RentReminder::class, fn (RentReminder $n, array $channels) => $channels === ['database']);

    $invite = new TenantInvitation('https://example.test/invite');
    expect($invite->via($tenant))->toContain(OutboxEmailChannel::class, OutboxSmsChannel::class);
});

it('drains the outbox and sends email through the mailer', function () {
    Mail::fake();
    $message = OutboundMessage::factory()->create(['html_body' => '<p>Hello</p>']);

    $result = app(Outbox::class)->drain();

    expect($result['sent'])->toBe(1)
        ->and($message->fresh()->status)->toBe(MessageStatus::Sent)
        ->and($message->fresh()->sent_at)->not->toBeNull();
});

it('retries failed SMS after 5, 15 and 60 minutes, then marks it failed', function () {
    $message = OutboundMessage::factory()->sms()->create();
    app(CurrentOrganization::class)->runAs($message->organization, function () {
        app(SettingsService::class)->setMany(['sms.driver' => 'termii', 'sms.termii_api_key' => 'key', 'sms.termii_sender_id' => 'Rentora']);
    });
    Http::fake(['*/api/sms/send' => Http::response(['message' => 'Server error'], 500)]);
    $outbox = app(Outbox::class);

    foreach ([5, 15, 60] as $attempt => $minutes) {
        expect($outbox->drain()['retried'])->toBe(1);
        $fresh = $message->fresh();
        expect($fresh->status)->toBe(MessageStatus::Queued)
            ->and($fresh->attempts)->toBe($attempt + 1)
            ->and($fresh->send_after->diffInMinutes(now()->addMinutes($minutes)) < 1)->toBeTrue()
            ->and($outbox->drain()['retried'])->toBe(0); // not due yet
        $this->travel($minutes + 1)->minutes();
    }

    expect($outbox->drain()['failed'])->toBe(1)
        ->and($message->fresh()->status)->toBe(MessageStatus::Failed)
        ->and($message->fresh()->attempts)->toBe(4)
        ->and($message->fresh()->last_error)->toContain('Termii 500');
});

it('fails an SMS to an invalid number immediately', function () {
    [$tenant] = tenantWithDueInvoice(today(), ['phone' => '12']);

    app(RentReminders::class)->run();

    $sms = OutboundMessage::query()->withoutGlobalScopes()->where('channel', 'sms')->sole();
    expect($sms->status)->toBe(MessageStatus::Failed)->and($sms->last_error)->toContain('not valid');
});

it('blocks a second tick while the lock is held', function () {
    SystemState::put(LazyScheduler::LOCK_KEY, now()->utc()->addMinute()->format('Y-m-d H:i:s').'|other');

    expect(app(LazyScheduler::class)->tick())->toBeNull();

    $this->travel(2)->minutes();
    expect(app(LazyScheduler::class)->tick())->toBeArray();
});

it('runs due tasks only and keeps going when a task throws', function () {
    $ran = [];
    $registry = Mockery::mock(TaskRegistry::class);
    $registry->shouldReceive('tasks')->andReturn([
        'broken' => ['every' => 'tick', 'run' => function () use (&$ran) {
            $ran[] = 'broken';
            throw new RuntimeException('boom');
        }],
        'hourly' => ['every' => 'minutes:60', 'run' => function () use (&$ran) {
            $ran[] = 'hourly';
        }],
        'every_tick' => ['every' => 'tick', 'run' => function () use (&$ran) {
            $ran[] = 'every_tick';
        }],
    ]);
    $scheduler = new LazyScheduler($registry);

    $first = $scheduler->tick();
    expect($ran)->toBe(['broken', 'hourly', 'every_tick'])
        ->and($first['broken']['status'])->toBe('failed')
        ->and(SystemState::getValue(LazyScheduler::lastErrorKey('broken')))->toBe('boom');

    $ran = [];
    $scheduler->tick();
    expect($ran)->toBe(['broken', 'every_tick']);
});

it('only runs daily tasks after their hour, once per day', function () {
    $scheduler = app(LazyScheduler::class);
    $task = ['every' => 'daily', 'after_hour' => 9];

    $this->travelTo(CarbonImmutable::parse('2026-11-24 07:00', 'Africa/Lagos'));
    expect($scheduler->isDue('x', $task))->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-11-24 09:30', 'Africa/Lagos'));
    expect($scheduler->isDue('x', $task))->toBeTrue();
    SystemState::put(LazyScheduler::lastRunKey('x'), now()->toIso8601String());
    expect($scheduler->isDue('x', $task))->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-11-25 09:30', 'Africa/Lagos'));
    expect($scheduler->isDue('x', $task))->toBeTrue();
});

it('runs a tick from the console and from the keyed URL only', function () {
    $this->artisan('rentora:tick')->assertSuccessful();
    expect(SystemState::getValue(LazyScheduler::LAST_TICK_KEY))->not->toBeNull();

    $this->get('/system/tick')->assertNotFound();
    $this->get('/system/tick?key=wrong')->assertNotFound();
    $this->get('/system/tick?key=test-tick-key')->assertOk()->assertJson(['status' => 'ok']);
});

it('runs the lazy scheduler after a response in lazy mode', function () {
    config(['rentora.scheduler.mode' => 'lazy']);
    $owner = userWithRole(Role::Owner);

    $this->actingAs($owner)->get(route('app.dashboard'))->assertOk();

    expect(SystemState::getValue(LazyScheduler::LAST_TICK_KEY))->not->toBeNull();
});

it('lets a user open only their own notifications', function () {
    [$tenantA, , $userA] = tenantWithDueInvoice(today());
    [$tenantB, , $userB] = tenantWithDueInvoice(today());
    app(RentReminders::class)->run();

    $mine = $userA->notifications()->sole();
    $theirs = $userB->notifications()->sole();

    $this->actingAs($userA)->get(route('portal.inbox'))->assertOk()->assertSee($mine->data['title']);
    $this->actingAs($userA)->post(route('portal.inbox.read', $theirs->id))->assertNotFound();
    expect($theirs->fresh()->read_at)->toBeNull();

    $this->actingAs($userA)->post(route('portal.inbox.read', $mine->id))->assertRedirect();
    expect($mine->fresh()->read_at)->not->toBeNull();
});

it('shows staff their unread count and marks all read', function () {
    $owner = userWithRole(Role::Owner);
    $owner->notify(new ProofSubmitted(Payment::factory()->create([
        'tenant_id' => Tenant::factory()->create(['organization_id' => $owner->organization_id])->id,
    ]), 'Ada'));

    $this->actingAs($owner)->get(route('app.notifications.index'))->assertOk()->assertSee('Transfer proof from Ada');
    $this->actingAs($owner)->get(route('app.dashboard'))->assertSee('data-testid="unread-count"', false);
    $this->actingAs($owner)->post(route('app.notifications.read-all'))->assertRedirect();
    expect($owner->unreadNotifications()->count())->toBe(0);
});

it('resolves announcement audiences', function () {
    $organization = organization();
    $propertyA = Property::factory()->create(['organization_id' => $organization->id]);
    $propertyB = Property::factory()->create(['organization_id' => $organization->id]);
    $leaseA = Lease::factory()->create(['unit_id' => Unit::factory()->create(['property_id' => $propertyA->id])->id, 'status' => LeaseStatus::Active]);
    $leaseB = Lease::factory()->create(['unit_id' => Unit::factory()->create(['property_id' => $propertyB->id])->id, 'status' => LeaseStatus::Active]);
    Lease::factory()->create(['unit_id' => Unit::factory()->create(['property_id' => $propertyB->id])->id, 'status' => LeaseStatus::Ended]);
    $outsider = Lease::factory()->create(['status' => LeaseStatus::Active]);

    app(CurrentOrganization::class)->runAs($organization, function () use ($leaseA, $leaseB, $propertyA, $outsider) {
        $resolver = app(AnnouncementAudienceResolver::class);
        expect($resolver->query(AnnouncementAudience::AllTenants)->pluck('id')->sort()->values()->all())->toBe(collect([$leaseA->tenant_id, $leaseB->tenant_id])->sort()->values()->all())
            ->and($resolver->query(AnnouncementAudience::Property, [$propertyA->id])->pluck('id')->all())->toBe([$leaseA->tenant_id])
            ->and($resolver->query(AnnouncementAudience::Tenants, [$leaseB->tenant_id, $outsider->tenant_id])->pluck('id')->all())->toBe([$leaseB->tenant_id]);
    });
});

it('publishes an announcement to the chosen audience and channels', function () {
    $owner = userWithRole(Role::Owner);
    $property = Property::factory()->create(['organization_id' => $owner->organization_id]);
    $lease = Lease::factory()->create(['unit_id' => Unit::factory()->create(['property_id' => $property->id])->id, 'status' => LeaseStatus::Active]);
    $tenantUser = userWithRole(Role::Tenant, $owner->organization);
    $lease->tenant->forceFill(['user_id' => $tenantUser->id])->save();

    $this->actingAs($owner)->getJson(route('app.announcements.recipients', ['audience' => 'all_tenants']))->assertJson(['count' => 1]);
    $this->actingAs($owner)->post(route('app.announcements.store'), [
        'title' => 'Water outage', 'body' => 'No water on Saturday.', 'audience' => 'property',
        'property_ids' => [$property->id], 'channels' => ['in_app', 'sms'],
    ])->assertRedirect(route('app.announcements.index'));

    expect(Announcement::query()->sole()->recipient_count)->toBe(1)
        ->and($tenantUser->notifications()->count())->toBe(1)
        ->and(OutboundMessage::query()->withoutGlobalScopes()->where('channel', 'sms')->count())->toBe(1)
        ->and(OutboundMessage::query()->withoutGlobalScopes()->where('channel', 'email')->count())->toBe(0);

    $this->actingAs($tenantUser)->get(route('portal.notices'))->assertOk()->assertSee('Water outage');
});

it('keeps announcements away from caretakers', function () {
    $caretaker = userWithRole(Role::Caretaker);

    $this->actingAs($caretaker)->get(route('app.announcements.create'))->assertForbidden();
});
