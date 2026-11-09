<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\SequenceService;
use App\Services\SettingsService;
use App\Support\CurrentOrganization;

it('scopes queries to the current organization and fills organization_id on create', function () {
    $mine = organization();
    $theirs = organization();
    userWithRole(Role::Manager, $mine);
    userWithRole(Role::Manager, $theirs);

    app(CurrentOrganization::class)->set($mine);

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->first()->organization_id)->toBe($mine->id);

    $log = ActivityLog::query()->create(['action' => 'test.created']);
    expect($log->organization_id)->toBe($mine->id);
});

it('stores secrets encrypted and only shows the last 4 characters', function () {
    $organization = organization();
    app(CurrentOrganization::class)->set($organization);
    $settings = app(SettingsService::class);

    $settings->set('paystack.secret_key', 'sk_test_abcdef1234', encrypted: true);

    $row = Setting::query()->where('key', 'paystack.secret_key')->firstOrFail();
    expect($row->is_encrypted)->toBeTrue()
        ->and($row->value)->not->toContain('sk_test')
        ->and($settings->get('paystack.secret_key'))->toBe('sk_test_abcdef1234')
        ->and($settings->masked('paystack.secret_key'))->toBe('********1234');
});

it('logs setting changes without their values', function () {
    app(CurrentOrganization::class)->set(organization());

    app(SettingsService::class)->set('smtp.password', 'hunter2', encrypted: true);

    $log = ActivityLog::query()->where('action', 'settings.changed')->firstOrFail();
    expect($log->properties)->toBe(['key' => 'smtp.password'])
        ->and(json_encode($log->properties))->not->toContain('hunter2');
});

it('keeps settings separate per organization', function () {
    $a = organization();
    $b = organization();
    $current = app(CurrentOrganization::class);

    $current->set($a);
    app(SettingsService::class)->set('billing.grace_days', '5');
    $current->set($b);
    app(SettingsService::class)->flush();

    expect(app(SettingsService::class)->get('billing.grace_days', 'fallback'))->toBe('fallback');
});

it('numbers documents per organization, key and year', function () {
    $organization = organization();
    app(CurrentOrganization::class)->set($organization);
    $sequences = app(SequenceService::class);

    expect($sequences->next(SequenceService::INVOICE, now()->setDate(2026, 3, 1)))->toBe('INV-2026-000001')
        ->and($sequences->next(SequenceService::INVOICE, now()->setDate(2026, 9, 1)))->toBe('INV-2026-000002')
        ->and($sequences->next(SequenceService::RECEIPT, now()->setDate(2026, 9, 1)))->toBe('RCT-2026-000001')
        ->and($sequences->next(SequenceService::MAINTENANCE, now()->setDate(2027, 1, 2)))->toBe('MR-2027-000001')
        ->and($sequences->next(SequenceService::INVOICE, now()->setDate(2027, 1, 2)))->toBe('INV-2027-000001');

    app(CurrentOrganization::class)->set(organization());
    expect($sequences->next(SequenceService::INVOICE, now()->setDate(2026, 3, 1)))->toBe('INV-2026-000001');
});

it('rejects unknown sequence keys', function () {
    app(CurrentOrganization::class)->set(organization());

    app(SequenceService::class)->next('nope');
})->throws(InvalidArgumentException::class);
