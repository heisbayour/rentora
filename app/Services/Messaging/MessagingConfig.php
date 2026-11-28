<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Services\Messaging\Sms\LogSmsDriver;
use App\Services\Messaging\Sms\SmsDriver;
use App\Services\Messaging\Sms\TermiiSmsDriver;
use App\Services\SettingsService;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\MailManager;

/**
 * Builds the SMS driver and SMTP mailer for the current organization from
 * Settings (spec 8.12), falling back to .env values (spec 9.2).
 */
class MessagingConfig
{
    public const SMS_DRIVERS = ['log', 'termii'];

    public const RUNTIME_MAILER = 'rentora_smtp';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly MailFactory $mail,
    ) {}

    public function smsDriverName(): string
    {
        $driver = (string) $this->settings->get('sms.driver', (string) config('rentora.sms.driver', 'log'));

        return in_array($driver, self::SMS_DRIVERS, true) ? $driver : 'log';
    }

    public function smsDriver(): SmsDriver
    {
        return match ($this->smsDriverName()) {
            'termii' => new TermiiSmsDriver(
                (string) $this->settings->get('sms.termii_api_key', (string) config('rentora.sms.termii.api_key')),
                (string) $this->settings->get('sms.termii_sender_id', (string) config('rentora.sms.termii.sender_id')),
                (string) $this->settings->get('sms.termii_channel', (string) config('rentora.sms.termii.channel', 'generic')),
                (string) $this->settings->get('sms.termii_base_url', (string) config('rentora.sms.termii.base_url')),
            ),
            default => new LogSmsDriver,
        };
    }

    /** The SMTP mailer from Settings when a host is saved, otherwise the .env default mailer. */
    public function mailer(): Mailer
    {
        $host = $this->settings->get('mail.host');
        if ($host === null) {
            return $this->mail->mailer();
        }

        $encryption = (string) $this->settings->get('mail.encryption', 'tls');
        config(['mail.mailers.'.self::RUNTIME_MAILER => [
            'transport' => 'smtp',
            'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $host,
            'port' => $this->settings->int('mail.port', 587),
            'username' => $this->settings->get('mail.username'),
            'password' => $this->settings->get('mail.password'),
            'timeout' => 15,
        ]]);
        if ($this->mail instanceof MailManager) {
            $this->mail->purge(self::RUNTIME_MAILER);
        }

        return $this->mail->mailer(self::RUNTIME_MAILER);
    }

    /**
     * Whether an SMTP server is configured: a host saved in Settings, or an
     * SMTP default mailer with a host in .env. The live .env ships with
     * MAIL_MAILER=smtp and an empty host, which means "not set up".
     */
    public function smtpConfigured(): bool
    {
        if (trim((string) $this->settings->get('mail.host', '')) !== '') {
            return true;
        }
        $mailer = (string) config('mail.default');

        return config("mail.mailers.{$mailer}.transport") === 'smtp'
            && trim((string) config("mail.mailers.{$mailer}.host", '')) !== '';
    }

    /**
     * Whether email can be sent at all: SMTP as above, or a non-SMTP default
     * mailer (log or array locally and in tests, or a provider API).
     */
    public function emailConfigured(): bool
    {
        if ($this->smtpConfigured()) {
            return true;
        }
        $mailer = (string) config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");

        return is_string($transport) && $transport !== 'smtp';
    }

    /** Whether a real SMS provider is selected with its key and sender ID ("log" only writes to the log). */
    public function smsConfigured(): bool
    {
        if ($this->smsDriverName() !== 'termii') {
            return false;
        }

        return trim((string) $this->settings->get('sms.termii_api_key', (string) config('rentora.sms.termii.api_key'))) !== ''
            && trim((string) $this->settings->get('sms.termii_sender_id', (string) config('rentora.sms.termii.sender_id'))) !== '';
    }

    /** @return array{address: string, name: string} */
    public function from(?string $organizationName = null): array
    {
        return [
            'address' => (string) $this->settings->get('mail.from_address', (string) config('mail.from.address')),
            'name' => (string) $this->settings->get('mail.from_name', $organizationName ?? (string) config('mail.from.name')),
        ];
    }
}
