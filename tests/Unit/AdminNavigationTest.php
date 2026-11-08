<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Support\AdminNavigation;

function navLabels(?Role $role): array
{
    return collect(AdminNavigation::for($role))
        ->flatMap(fn (array $group) => array_column($group['items'], 'label'))
        ->all();
}

it('shows the owner every item', function () {
    expect(navLabels(Role::Owner))->toContain('Settings', 'Staff', 'System health', 'Invoices', 'Maintenance');
});

it('hides settings, staff and system health from managers', function () {
    expect(navLabels(Role::Manager))
        ->toContain('Invoices', 'Payments', 'Maintenance')
        ->not->toContain('Settings', 'Staff', 'System health', 'Audit log');
});

it('hides money from caretakers', function () {
    expect(navLabels(Role::Caretaker))
        ->toContain('Maintenance', 'Units', 'Tenants')
        ->not->toContain('Invoices', 'Payments', 'Expenses', 'Reports', 'Settings');
});

it('gives tenants no console items', function () {
    expect(navLabels(Role::Tenant))->toBe([]);
});
