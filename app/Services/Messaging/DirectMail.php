<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Support\CurrentOrganization;
use Illuminate\Mail\Message;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sends one email at once through the SMTP server from Settings (or .env),
 * for staff invitations and password resets (DECISIONS 70). Callers catch
 * the exception: no user action may fail because email is broken.
 */
class DirectMail
{
    public function __construct(
        private readonly MessagingConfig $config,
        private readonly CurrentOrganization $current,
    ) {}

    public function send(string $to, ?string $name, MailMessage $message): void
    {
        $from = $this->config->from($this->current->get()?->name);
        $html = (string) $message->render();

        $this->config->mailer()->html($html, function (Message $mail) use ($to, $name, $message, $from): void {
            $mail->to($to, $name)->subject((string) $message->subject);
            if ($from['address'] !== '') {
                $mail->from($from['address'], $from['name']);
            }
        });
    }
}
