<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lease;
use App\Models\Tenant;
use App\Services\Messaging\NotificationMessage;
use Illuminate\Database\Eloquent\Model;

/** A lease ends in 60 or 30 days (spec 9.3 lease.expiring), for the tenant and owners/managers. */
class LeaseExpiring extends OutboxNotification
{
    public function __construct(
        public readonly Lease $lease,
        public readonly int $days,
        public readonly string $tenantName,
        public readonly string $unitName,
    ) {}

    public function event(): string
    {
        return 'lease.expiring';
    }

    protected function defaultChannels(object $notifiable): array
    {
        return ['in_app', 'email'];
    }

    public function related(): ?Model
    {
        return $this->lease;
    }

    public function message(object $notifiable): NotificationMessage
    {
        $replace = [
            'days' => $this->days,
            'date' => $this->lease->end_date->format('j M Y'),
            'tenant' => $this->tenantName,
            'unit' => $this->unitName,
        ];
        $who = $notifiable instanceof Tenant ? 'tenant' : 'staff';

        return new NotificationMessage(
            title: __('messaging.lease_expiring.title', $replace),
            body: __('messaging.lease_expiring.'.$who.'_body', $replace),
            link: $notifiable instanceof Tenant ? route('portal.home') : route('app.leases.show', $this->lease),
        );
    }
}
