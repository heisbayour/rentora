<?php

declare(strict_types=1);

use App\Actions\Staff\InviteStaff;
use App\Actions\Tenants\InviteTenant;
use App\Enums\MaintenanceStatus;
use App\Enums\Role;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Vendor;
use App\Services\Maintenance\MaintenanceWorkflow;
use Illuminate\Support\Facades\URL;

/*
 * v1.0.2: signed links on hosts like InfinityFree, whose browser check
 * appends "i=1" to the URL and whose proxy terminates HTTPS.
 */

function invitedTenantLink(): string
{
    $tenant = Tenant::factory()->create(['organization_id' => organization()->id]);
    $tenant->forceFill(['invite_sent_at' => now()])->save();

    return InviteTenant::link($tenant);
}

function assignedVendorLink(): string
{
    $manager = userWithRole(Role::Manager);
    $unit = Unit::factory()->create(['property_id' => Property::factory()->create(['organization_id' => $manager->organization_id])->id]);
    $request = MaintenanceRequest::factory()->status(MaintenanceStatus::Acknowledged)->create(['unit_id' => $unit->id]);
    $vendor = Vendor::factory()->create(['organization_id' => $manager->organization_id]);
    $request->forceFill(['vendor_id' => $vendor->id])->save();

    return MaintenanceWorkflow::vendorLink($request->fresh());
}

it('accepts a tenant invite link with the host "i" parameter appended', function () {
    $link = invitedTenantLink();

    $this->get($link.'&i=1')->assertOk();
    $this->post($link.'&i=1', ['password' => 'secret123', 'password_confirmation' => 'secret123'])
        ->assertRedirect(route('portal.home'));
});

it('accepts a staff invite link with the host "i" parameter appended', function () {
    $link = InviteStaff::link(userWithRole(Role::Manager));

    $this->get($link.'&i=1')->assertOk();
});

it('accepts a vendor job link with the host "i" parameter appended', function () {
    $this->get(assignedVendorLink().'&i=1')->assertOk();
});

it('still rejects a tampered signed link, with or without "i"', function () {
    $link = invitedTenantLink();
    $tampered = preg_replace('/expires=(\d+)/', 'expires=9999999999', $link);

    $this->get($tampered)->assertForbidden();
    $this->get($tampered.'&i=1')->assertForbidden();
    $this->get($link.'&i=1&extra=1')->assertForbidden();
    $this->get(str_replace('signature=', 'signature=0', $link).'&i=1')->assertForbidden();
});

it('validates https links when the proxy forwards the scheme', function () {
    config(['app.url' => 'https://rentora.test']);
    URL::forceRootUrl('https://rentora.test');
    URL::forceScheme('https');
    $link = invitedTenantLink();
    expect($link)->toStartWith('https://');

    // Proxy talks plain http to PHP but says the visitor used https.
    $this->get(str_replace('https://', 'http://', $link).'&i=1', ['X-Forwarded-Proto' => 'https'])->assertOk();
});

it('validates https links when the proxy forwards nothing', function () {
    config(['app.url' => 'https://rentora.test']);
    URL::forceRootUrl('https://rentora.test');
    URL::forceScheme('https');
    $link = invitedTenantLink();

    $this->get(str_replace('https://', 'http://', $link).'&i=1')->assertOk();
});
