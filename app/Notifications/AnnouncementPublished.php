<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use App\Services\Messaging\NotificationMessage;
use Illuminate\Database\Eloquent\Model;

/** An announcement on the channels the sender chose (spec 9.3 announcement.published). */
class AnnouncementPublished extends OutboxNotification
{
    public function __construct(public readonly Announcement $announcement) {}

    public function event(): string
    {
        return 'announcement.published';
    }

    protected function defaultChannels(object $notifiable): array
    {
        return array_values(array_intersect(Announcement::CHANNELS, $this->announcement->channels));
    }

    public function related(): ?Model
    {
        return $this->announcement;
    }

    public function message(object $notifiable): NotificationMessage
    {
        return new NotificationMessage(
            title: $this->announcement->title,
            body: $this->announcement->body,
            link: self::link('portal.notices'),
            actionLabel: __('messaging.announcement.action'),
            sms: $this->announcement->title.': '.$this->announcement->body,
        );
    }
}
