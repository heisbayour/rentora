<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\MessageStatus;
use App\Models\Organization;
use App\Models\OutboundMessage;
use App\Notifications\OutboxNotification;
use App\Support\CurrentOrganization;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Message;
use Throwable;

/**
 * The outbound_messages table (spec 9.1 and 9.2): notifications queue emails
 * and SMS here, and the scheduler drains them in small batches with retries
 * after 5, 15 and 60 minutes before giving up.
 */
class Outbox
{
    public function __construct(
        private readonly CurrentOrganization $current,
        private readonly MessagingConfig $config,
    ) {}

    public function queueEmail(object $notifiable, string $email, string $event, NotificationMessage $message, ?Model $related = null): OutboundMessage
    {
        $organization = self::organizationName($notifiable);
        $html = view('mail.outbox', [
            'organization' => $organization,
            'name' => OutboxNotification::nameOf($notifiable),
            'message' => $message,
        ])->render();

        $text = $message->body.($message->link !== null ? "\n\n".($message->actionLabel ?? $message->link).': '.$message->link : '');

        return $this->insert($notifiable, [
            'channel' => OutboundMessage::EMAIL,
            'event' => $event,
            'recipient' => $email,
            'subject' => $message->title,
            'body' => $text,
            'html_body' => $html,
        ], $related);
    }

    public function queueSms(object $notifiable, string $phone, string $event, NotificationMessage $message, ?Model $related = null): OutboundMessage
    {
        $e164 = PhoneNormalizer::toE164($phone);
        $attributes = [
            'channel' => OutboundMessage::SMS,
            'event' => $event,
            'recipient' => $e164 ?? $phone,
            'body' => $message->smsText(self::organizationName($notifiable)),
        ];
        if ($e164 === null) {
            // Invalid numbers fail at once with a clear error (spec 9.2).
            $attributes += ['status' => MessageStatus::Failed, 'last_error' => __('messaging.errors.invalid_phone', ['phone' => $phone])];
        }

        return $this->insert($notifiable, $attributes, $related);
    }

    /**
     * Sends due messages, oldest first, until the batch or the deadline runs out.
     *
     * @return array{sent: int, failed: int, retried: int}
     */
    public function drain(?float $deadline = null, ?int $batch = null): array
    {
        $batch ??= (int) config('rentora.outbox.batch', 10);
        $result = ['sent' => 0, 'failed' => 0, 'retried' => 0];

        $ids = OutboundMessage::query()->withoutGlobalScopes()
            ->where('status', MessageStatus::Queued->value)
            ->where(fn ($q) => $q->whereNull('send_after')->orWhere('send_after', '<=', now()))
            ->orderBy('id')
            ->limit($batch)
            ->pluck('id');

        foreach ($ids as $id) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }
            // Claim atomically so two ticks can never send the same row.
            $claimed = OutboundMessage::query()->withoutGlobalScopes()
                ->whereKey($id)->where('status', MessageStatus::Queued->value)
                ->update(['status' => MessageStatus::Sending->value, 'updated_at' => now()]);
            if ($claimed !== 1) {
                continue;
            }

            $message = OutboundMessage::query()->withoutGlobalScopes()->findOrFail($id);
            $outcome = $this->deliver($message);
            $result[$outcome]++;
        }

        return $result;
    }

    /** Puts every failed message back in the queue (System health "Retry failed"). */
    public function retryFailed(): int
    {
        return OutboundMessage::query()
            ->where('status', MessageStatus::Failed->value)
            ->update(['status' => MessageStatus::Queued->value, 'attempts' => 0, 'send_after' => now(), 'updated_at' => now()]);
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $counts = array_fill_keys(MessageStatus::values(), 0);
        foreach (OutboundMessage::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') as $status => $total) {
            $counts[(string) $status] = (int) $total;
        }

        return $counts;
    }

    /** @return 'sent'|'failed'|'retried' */
    private function deliver(OutboundMessage $message): string
    {
        $organization = Organization::query()->find($message->organization_id);
        if ($organization === null) {
            return $this->fail($message, 'Organization not found.', permanent: true);
        }

        // With no SMTP server there is nothing to connect to: fail at once instead
        // of spending the scheduler's time budget on a connection timeout.
        // "Retry failed" on System health queues it again once email is set up.
        $emailOff = $message->channel === OutboundMessage::EMAIL
            && ! $this->current->runAs($organization, fn (): bool => $this->config->emailConfigured());
        if ($emailOff) {
            return $this->fail($message, __('messaging.errors.email_not_set_up'), permanent: true);
        }

        try {
            $error = $this->current->runAs($organization, function () use ($message, $organization): ?string {
                if ($message->channel === OutboundMessage::SMS) {
                    return $this->sendSms($message);
                }
                $this->sendEmail($message, $organization);

                return null;
            });
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 1000);
        }

        if ($error === null) {
            $message->forceFill(['status' => MessageStatus::Sent, 'sent_at' => now(), 'last_error' => null, 'attempts' => $message->attempts + 1])->save();

            return 'sent';
        }

        return $this->fail($message, $error);
    }

    private function sendSms(OutboundMessage $message): ?string
    {
        $result = $this->config->smsDriver()->send($message->recipient, $message->body);

        return $result->ok ? null : (string) $result->error;
    }

    private function sendEmail(OutboundMessage $message, Organization $organization): void
    {
        $from = $this->config->from($organization->name);
        $callback = function (Message $mail) use ($message, $from): void {
            $mail->to($message->recipient, $message->recipient_name)->subject((string) $message->subject);
            if ($from['address'] !== '') {
                $mail->from($from['address'], $from['name']);
            }
        };

        $mailer = $this->config->mailer();
        if ($message->html_body !== null) {
            $mailer->html($message->html_body, $callback);
        } else {
            $mailer->raw($message->body, $callback);
        }
    }

    /** @return 'failed'|'retried' */
    private function fail(OutboundMessage $message, string $error, bool $permanent = false): string
    {
        $attempts = $message->attempts + 1;
        $backoff = (array) config('rentora.outbox.backoff_minutes', [5, 15, 60]);
        $maxAttempts = (int) config('rentora.outbox.max_attempts', 4);

        if ($permanent || $attempts >= $maxAttempts) {
            $message->forceFill(['status' => MessageStatus::Failed, 'attempts' => $attempts, 'last_error' => $error])->save();

            return 'failed';
        }

        $delay = (int) ($backoff[$attempts - 1] ?? end($backoff));
        $message->forceFill([
            'status' => MessageStatus::Queued,
            'attempts' => $attempts,
            'last_error' => $error,
            'send_after' => now()->addMinutes($delay),
        ])->save();

        return 'retried';
    }

    /** @param  array<string, mixed>  $attributes */
    private function insert(object $notifiable, array $attributes, ?Model $related): OutboundMessage
    {
        $organizationId = $notifiable instanceof Model ? $notifiable->getAttribute('organization_id') : null;

        $message = new OutboundMessage([
            'recipient_name' => OutboxNotification::nameOf($notifiable),
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'send_after' => now(),
            ...$attributes,
        ]);
        $message->organization_id = (int) ($organizationId ?? $this->current->id());
        $message->save();

        return $message;
    }

    private static function organizationName(object $notifiable): string
    {
        $id = $notifiable instanceof Model ? $notifiable->getAttribute('organization_id') : null;
        $name = $id === null ? null : Organization::query()->whereKey($id)->value('name');

        return is_string($name) ? $name : (string) config('rentora.name');
    }
}
