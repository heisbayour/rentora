<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Notifications\TenantInvitation;
use App\Services\ActivityLogger;
use App\Services\Messaging\MessagingConfig;
use App\Support\Delivery;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sends the portal invitation (spec 8.4): a signed link valid for 7 days.
 * The link is bound to invite_sent_at, so resending invalidates older links,
 * and it stops working once the tenant has a portal account. Email and SMS
 * go through the outbox; the link is always returned for the share panel
 * with what was queued (DECISIONS 104).
 */
class InviteTenant
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly MessagingConfig $messaging,
    ) {}

    /** @return array{link: string, deliveries: list<Delivery>} */
    public function handle(Tenant $tenant): array
    {
        $this->ensureInvitable($tenant);
        if (($tenant->email ?? '') === '' && $tenant->phone === '') {
            throw ValidationException::withMessages(['tenant' => __('tenants.errors.no_contact')]);
        }

        $link = $this->freshLink($tenant);
        $hasEmail = ($tenant->email ?? '') !== '';
        $hasPhone = $tenant->phone !== '';

        try {
            Notification::send($tenant, new TenantInvitation($link));
            $deliveries = [
                ! $hasEmail ? Delivery::noAddress('email') : ($this->messaging->emailConfigured() ? Delivery::queued('email') : Delivery::notSetUp('email')),
                ! $hasPhone ? Delivery::noAddress('sms') : ($this->messaging->smsConfigured() ? Delivery::queued('sms') : Delivery::notSetUp('sms')),
            ];
        } catch (Throwable $e) {
            report($e);
            $deliveries = [Delivery::failed('email', $e->getMessage()), Delivery::failed('sms', $e->getMessage())];
        }

        $this->logger->log('tenant.invited', $tenant, ['email' => $hasEmail, 'sms' => $hasPhone]);

        return ['link' => $link, 'deliveries' => $deliveries];
    }

    /** "Get invite link": a fresh link (older ones stop working), nothing sent. */
    public function issueLink(Tenant $tenant): string
    {
        $this->ensureInvitable($tenant);
        $link = $this->freshLink($tenant);
        $this->logger->log('tenant.invite_link_issued', $tenant);

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

    private function ensureInvitable(Tenant $tenant): void
    {
        if ($tenant->hasPortalAccount()) {
            throw ValidationException::withMessages(['tenant' => __('tenants.errors.already_registered')]);
        }
    }

    private function freshLink(Tenant $tenant): string
    {
        $tenant->forceFill(['invite_sent_at' => now()])->save();

        return self::link($tenant);
    }
}
