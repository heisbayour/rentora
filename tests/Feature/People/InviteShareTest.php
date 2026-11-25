<?php

declare(strict_types=1);

use App\Actions\Tenants\InviteTenant;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

it('shows a copyable tenant invite link and a WhatsApp share after inviting', function () {
    Notification::fake();
    $manager = userWithRole(Role::Manager);
    $tenant = Tenant::factory()->create(['organization_id' => $manager->organization_id, 'email' => 'ada@example.com', 'phone' => '+2348031234567']);

    $this->actingAs($manager)->from(route('app.tenants.show', $tenant))
        ->post(route('app.tenants.invite', $tenant))
        ->assertSessionHas('invite_share', fn (array $share) => strtok($share['link'], '?') === strtok(InviteTenant::link($tenant->fresh()), '?')
            && str_contains($share['link'], 'signature=')
            && str_starts_with($share['whatsapp'], 'https://wa.me/2348031234567?text='));

    $this->get(route('app.tenants.show', $tenant))
        ->assertOk()
        ->assertSee(__('ui.invite_share.copy'))
        ->assertSee(__('ui.invite_share.whatsapp'))
        ->assertSee('https://wa.me/2348031234567', false);
});

it('shows the staff invite link after inviting and after resending', function () {
    Notification::fake();
    $owner = userWithRole(Role::Owner);

    $this->actingAs($owner)->post(route('app.staff.store'), [
        'name' => 'Musa Bello',
        'email' => 'musa@example.com',
        'role' => 'caretaker',
    ])->assertSessionHas('invite_share', fn (array $share) => str_contains($share['link'], '/invite/staff/')
        && str_starts_with($share['whatsapp'], 'https://wa.me/?text='));

    $this->get(route('app.staff.index'))->assertOk()->assertSee(__('ui.invite_share.copy'));

    $member = User::query()->where('email', 'musa@example.com')->firstOrFail();
    $this->post(route('app.staff.resend', $member))->assertSessionHas('invite_share');
});

it('the shared staff link opens the set-password page', function () {
    Notification::fake();
    $owner = userWithRole(Role::Owner);
    $this->actingAs($owner)->post(route('app.staff.store'), ['name' => 'Musa Bello', 'email' => 'musa@example.com', 'role' => 'manager']);
    $link = session('invite_share')['link'];
    auth()->logout();

    $this->get($link)->assertOk()->assertSee('musa@example.com');
});
