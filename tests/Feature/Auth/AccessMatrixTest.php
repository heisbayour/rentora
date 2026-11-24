<?php

declare(strict_types=1);

use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
 * Role/route access matrix (spec 13): every named GET route under /app and
 * /portal, for every role. Allowed roles must get a page (2xx or a redirect),
 * everyone else a 403, and guests are sent to the login page.
 */

const ALL_STAFF = ['owner', 'manager', 'caretaker'];
const MONEY = ['owner', 'manager'];
const OWNER = ['owner'];
const TENANT = ['tenant'];

function accessRules(): array
{
    return [
        'app.dashboard' => ALL_STAFF,
        'app.notifications.index' => ALL_STAFF,
        'app.profile.edit' => ALL_STAFF,
        'app.maintenance.index' => ALL_STAFF,
        'app.maintenance.create' => ALL_STAFF,
        'app.maintenance.show' => ALL_STAFF,
        'app.vendors.index' => ALL_STAFF,
        'app.units.index' => ALL_STAFF,
        'app.units.show' => ALL_STAFF,
        'app.tenants.index' => ALL_STAFF,
        'app.tenants.show' => ALL_STAFF,
        'app.units.create' => MONEY,
        'app.units.edit' => MONEY,
        'app.tenants.create' => MONEY,
        'app.tenants.edit' => MONEY,
        'app.tenants.statement' => MONEY,
        'app.tenants.statement.pdf' => MONEY,
        'app.properties.index' => MONEY,
        'app.properties.create' => MONEY,
        'app.properties.show' => MONEY,
        'app.properties.edit' => MONEY,
        'app.properties.quick-add' => MONEY,
        'app.listings.index' => MONEY,
        'app.leases.index' => MONEY,
        'app.leases.show' => MONEY,
        'app.leases.wizard.start' => MONEY,
        'app.leases.wizard.show' => MONEY,
        'app.invoices.index' => MONEY,
        'app.invoices.create' => MONEY,
        'app.invoices.show' => MONEY,
        'app.invoices.pdf' => MONEY,
        'app.payments.index' => MONEY,
        'app.payments.create' => MONEY,
        'app.payments.show' => MONEY,
        'app.payments.receipt' => MONEY,
        'app.payments.proof' => MONEY,
        'app.expenses.index' => MONEY,
        'app.expenses.create' => MONEY,
        'app.expenses.edit' => MONEY,
        'app.expenses.receipt' => MONEY,
        'app.reports.index' => MONEY,
        'app.reports.show' => MONEY,
        'app.reports.export' => MONEY,
        'app.announcements.index' => MONEY,
        'app.announcements.create' => MONEY,
        'app.announcements.recipients' => MONEY,
        'app.inquiries.index' => MONEY,
        'app.audit.index' => OWNER,
        'app.health' => OWNER,
        'app.settings.edit' => OWNER,
        'app.staff.index' => OWNER,
        'app.onboarding.show' => MONEY,
    ];
}

/** Routes whose allowed response legitimately depends on a stored file or query input. */
const DENY_ONLY = ['app.payments.proof', 'app.expenses.receipt', 'app.announcements.recipients', 'app.onboarding.show', 'app.leases.wizard.show'];

it('enforces the role matrix on every /app and /portal page', function () {
    $organization = organization();
    $users = [];
    foreach (Role::cases() as $role) {
        $users[$role->value] = userWithRole($role, $organization);
    }

    $property = Property::factory()->create(['organization_id' => $organization->id]);
    $unit = Unit::factory()->create(['property_id' => $property->id]);
    $lease = Lease::factory()->create(['unit_id' => $unit->id, 'status' => LeaseStatus::Active]);
    $tenant = $lease->tenant;
    $tenant->forceFill(['user_id' => $users['tenant']->id])->save();
    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
    $payment = Payment::factory()->create(['tenant_id' => $tenant->id, 'status' => PaymentStatus::Successful, 'receipt_number' => 'RCT-M-1']);
    $request = MaintenanceRequest::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id]);
    $expense = Expense::factory()->create(['property_id' => $property->id]);

    $parameters = [
        'property' => $property, 'unit' => $unit, 'tenant' => $tenant, 'lease' => $lease, 'invoice' => $invoice,
        'payment' => $payment, 'maintenance' => $request, 'expense' => $expense, 'report' => 'arrears',
        'step' => 'organization',
    ];

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true)
            && preg_match('/^(app|portal)\./', (string) $route->getName())
            && $route->getName() !== 'app.styleguide');

    $rules = accessRules();
    $checked = 0;

    foreach ($routes as $route) {
        $name = (string) $route->getName();
        $allowed = str_starts_with($name, 'portal.') ? TENANT : ($rules[$name] ?? null);
        expect($allowed)->not->toBeNull("No access rule for route {$name}");

        $params = [];
        foreach ($route->parameterNames() as $parameter) {
            $params[$parameter] = $parameters[$parameter] ?? throw new RuntimeException("No fixture for {$parameter} in {$name}");
        }
        $url = route($name, $params);

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));

        foreach ($users as $role => $user) {
            $status = $this->actingAs($user)->get($url)->getStatusCode();
            if (in_array($role, $allowed, true)) {
                if (! in_array($name, DENY_ONLY, true)) {
                    expect($status)->toBeLessThan(400, "{$role} should reach {$name}, got {$status}");
                }
            } else {
                expect($status)->toBe(403, "{$role} must not reach {$name}, got {$status}");
            }
        }
        $checked++;
    }

    expect($checked)->toBeGreaterThan(60);
});

it('returns 404 for records of another organization', function () {
    $owner = userWithRole(Role::Owner);
    $foreignUnit = Unit::factory()->create();
    $foreignLease = Lease::factory()->create(['unit_id' => $foreignUnit->id, 'status' => LeaseStatus::Active]);
    $foreignInvoice = Invoice::factory()->create(['tenant_id' => $foreignLease->tenant_id]);
    $foreignPayment = Payment::factory()->create(['tenant_id' => $foreignLease->tenant_id]);
    $foreignRequest = MaintenanceRequest::factory()->create(['unit_id' => $foreignUnit->id]);

    foreach ([
        route('app.units.show', $foreignUnit), route('app.properties.show', $foreignUnit->property_id),
        route('app.tenants.show', $foreignLease->tenant_id), route('app.leases.show', $foreignLease),
        route('app.invoices.show', $foreignInvoice), route('app.payments.show', $foreignPayment),
        route('app.maintenance.show', $foreignRequest),
    ] as $url) {
        $this->actingAs($owner)->get($url)->assertNotFound();
    }
});

it('sends anonymous requests for private files to the login page', function () {
    $document = Document::factory()->create();

    $this->get(route('files.documents', $document))->assertRedirect(route('login'));
});

it('sends security headers on every page', function () {
    $this->get(route('login'))
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

it('seeds a demo where all four logins work', function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);

    foreach (['owner' => 'app.dashboard', 'manager' => 'app.dashboard', 'caretaker' => 'app.dashboard', 'tenant' => 'portal.home'] as $role => $home) {
        $this->post(route('login'), ['email' => $role.'@demo.test', 'password' => 'password'])->assertRedirect(route($home));
        $this->get(route($home))->assertOk();
        $this->post(route('logout'));
    }

    expect(Tenant::query()->withoutGlobalScopes()->count())->toBe(9)
        ->and(Unit::query()->withoutGlobalScopes()->count())->toBe(12)
        ->and(Payment::query()->withoutGlobalScopes()->where('status', 'pending')->count())->toBe(2);
});
