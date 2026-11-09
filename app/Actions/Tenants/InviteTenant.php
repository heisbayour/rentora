<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Notifications\TenantInvitation;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Sends the portal invitation (spec 8.4): a signed link valid for 7 days.
 * The link is bound to invite_sent_at, so resending invalidates older links,
 * and it stops working once the tenant has a portal account.
 */
class InviteTenant
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Tenant $tenant): string
    {
        if ($tenant->hasPortalAccount()) {
            throw ValidationException::withMessages(['tenant' => __('tenants.errors.already_registered')]);
        }
        if (($tenant->email ?? '') === '' && $tenant->phone === '') {
            throw ValidationException::withMessages(['tenant' => __('tenants.errors.no_contact')]);
        }

        $tenant->forceFill(['invite_sent_at' => now()])->save();
        $link = self::link($tenant);

        Notification::send($tenant, new TenantInvitation($link));
        $this->logger->log('tenant.invited', $tenant, ['email' => $tenant->email !== null, 'sms' => $tenant->phone !== '']);

        return $link;
    }

    public static function link(Tenant $tenant): string
    {
        return URL::temporarySignedRoute(
            'invite.tenant',
            now()->addDays((int) config('rentora.links.invite_days')),
            ['tenant' => $tenant->getKey(), 'h' => self::fingerprint($tenant)],
        );
    }

    public static function fingerprint(Tenant $tenant): string
    {
        $basis = $tenant->getKey().'|'.$tenant->invite_sent_at?->getTimestamp().'|'.($tenant->user_id ?? '');

        return substr(hash_hmac('sha256', $basis, (string) config('app.key')), 0, 16);
    }
}
