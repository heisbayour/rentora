<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Channels\OutboxEmailChannel;
use App\Notifications\Channels\OutboxSmsChannel;
use App\Services\Messaging\NotificationMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Base for every Rentora notification (spec 9.1): the in-app inbox is written
 * immediately; email and SMS go to the outbox and are sent by the scheduler.
 * Tenant preferences are respected unless the message is critical (spec 8.8).
 */
abstract class OutboxNotification extends Notification
{
    /** The spec 9.3 event key, for example "invoice.issued". */
    abstract public function event(): string;

    abstract public function message(object $notifiable): NotificationMessage;

    /** @return list<string> subset of in_app, email, sms */
    abstract protected function defaultChannels(object $notifiable): array;

    /** Critical messages (receipt, invite) always go by email when there is an address. */
    protected function critical(): bool
    {
        return false;
    }

    public function related(): ?Model
    {
        return null;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $via = [];
        foreach ($this->defaultChannels($notifiable) as $channel) {
            if ($channel === 'in_app' && self::hasInbox($notifiable)) {
                $via[] = 'database';
            }
            if ($channel === 'email' && self::emailOf($notifiable) !== null && ($this->critical() || self::wants($notifiable, 'email'))) {
                $via[] = OutboxEmailChannel::class;
            }
            if ($channel === 'sms' && self::phoneOf($notifiable) !== null && ($this->critical() || self::wants($notifiable, 'sms'))) {
                $via[] = OutboxSmsChannel::class;
            }
        }

        return array_values(array_unique($via));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $message = $this->message($notifiable);

        return [
            'event' => $this->event(),
            'title' => $message->title,
            'body' => $message->body,
            'link' => $message->link,
        ];
    }

    public static function hasInbox(object $notifiable): bool
    {
        return $notifiable instanceof User || ($notifiable instanceof Tenant && $notifiable->user_id !== null);
    }

    public static function emailOf(object $notifiable): ?string
    {
        $email = $notifiable instanceof Model ? $notifiable->getAttribute('email') : null;

        return is_string($email) && $email !== '' ? $email : null;
    }

    public static function phoneOf(object $notifiable): ?string
    {
        $phone = $notifiable instanceof Model ? $notifiable->getAttribute('phone') : null;

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    public static function nameOf(object $notifiable): ?string
    {
        if (! $notifiable instanceof Model) {
            return null;
        }
        $name = $notifiable->getAttribute('full_name') ?? $notifiable->getAttribute('name');

        return is_string($name) ? $name : null;
    }

    /** A route URL, or the portal home while a screen does not exist. */
    protected static function link(string $route, mixed $parameters = []): string
    {
        return Route::has($route) ? route($route, $parameters) : route('portal.home');
    }

    protected static function wants(object $notifiable, string $channel): bool
    {
        return ! $notifiable instanceof Tenant || $notifiable->wants($channel);
    }

    protected static function organizationName(object $notifiable): string
    {
        $id = $notifiable instanceof Model ? $notifiable->getAttribute('organization_id') : null;
        $name = $id === null ? null : Organization::query()->whereKey($id)->value('name');

        return is_string($name) ? $name : (string) config('rentora.name');
    }
}
