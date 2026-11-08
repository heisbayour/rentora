<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Role;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar groups for the landlord console (spec 6.2). Items are hidden by
 * role; an item whose route is not built yet renders without a link target.
 */
final class AdminNavigation
{
    private const ALL_STAFF = ['owner', 'manager', 'caretaker'];

    private const MONEY = ['owner', 'manager'];

    private const OWNER = ['owner'];

    /**
     * @return list<array{key: string, items: list<array{label: string, route: string, icon: string, roles: list<string>}>}>
     */
    public static function definition(): array
    {
        return [
            ['key' => 'overview', 'items' => [
                self::item('dashboard', 'app.dashboard', 'home', self::ALL_STAFF),
            ]],
            ['key' => 'portfolio', 'items' => [
                self::item('properties', 'app.properties.index', 'building-office-2', self::MONEY),
                self::item('units', 'app.units.index', 'squares-2x2', self::ALL_STAFF),
                self::item('listings', 'app.listings.index', 'globe-alt', self::MONEY),
            ]],
            ['key' => 'people', 'items' => [
                self::item('tenants', 'app.tenants.index', 'users', self::ALL_STAFF),
                self::item('leases', 'app.leases.index', 'document-text', self::MONEY),
                self::item('vendors', 'app.vendors.index', 'wrench', self::ALL_STAFF),
            ]],
            ['key' => 'money', 'items' => [
                self::item('invoices', 'app.invoices.index', 'document-currency-dollar', self::MONEY),
                self::item('payments', 'app.payments.index', 'banknotes', self::MONEY),
                self::item('expenses', 'app.expenses.index', 'receipt-percent', self::MONEY),
            ]],
            ['key' => 'operations', 'items' => [
                self::item('maintenance', 'app.maintenance.index', 'wrench-screwdriver', self::ALL_STAFF),
                self::item('announcements', 'app.announcements.index', 'megaphone', self::MONEY),
                self::item('inbox', 'app.inquiries.index', 'inbox', self::MONEY),
            ]],
            ['key' => 'insights', 'items' => [
                self::item('reports', 'app.reports.index', 'chart-bar', self::MONEY),
            ]],
            ['key' => 'admin', 'items' => [
                self::item('staff', 'app.staff.index', 'user-group', self::OWNER),
                self::item('settings', 'app.settings.edit', 'cog-6-tooth', self::OWNER),
                self::item('templates', 'app.templates.index', 'chat-bubble-left-right', self::MONEY),
                self::item('audit', 'app.audit.index', 'shield-check', self::OWNER),
                self::item('health', 'app.health', 'heart', self::OWNER),
            ]],
        ];
    }

    /**
     * Groups visible to a role, each item resolved to a label, href and active flag.
     *
     * @return list<array{label: string, items: list<array{label: string, href: ?string, icon: string, active: bool}>}>
     */
    public static function for(?Role $role): array
    {
        $groups = [];
        foreach (self::definition() as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if ($role !== null && ! in_array($role->value, $item['roles'], true)) {
                    continue;
                }
                $exists = Route::has($item['route']);
                $items[] = [
                    'label' => $item['label'],
                    'href' => $exists ? route($item['route']) : null,
                    'icon' => $item['icon'],
                    'active' => $exists && request()->routeIs(self::activePattern($item['route'])),
                ];
            }
            if ($items !== []) {
                $groups[] = ['label' => __('nav.groups.'.$group['key']), 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * @param  list<string>  $roles
     * @return array{label: string, route: string, icon: string, roles: list<string>}
     */
    private static function item(string $key, string $route, string $icon, array $roles): array
    {
        return ['label' => __('nav.items.'.$key), 'route' => $route, 'icon' => $icon, 'roles' => $roles];
    }

    private static function activePattern(string $route): string
    {
        $parts = explode('.', $route);
        if (count($parts) > 2) {
            array_pop($parts);
        }

        return implode('.', $parts).'*';
    }
}
