<?php

declare(strict_types=1);

namespace App\Services\System;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailer as IlluminateMailer;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

/**
 * Connects to the SMTP server and logs in (EHLO, STARTTLS, AUTH), then
 * disconnects. No message is sent. Throws with the server's error.
 */
class SmtpProbe
{
    public function check(Mailer $mailer): void
    {
        if (! $mailer instanceof IlluminateMailer) {
            throw new RuntimeException('The mailer is not an SMTP mailer.');
        }
        $transport = $mailer->getSymfonyTransport();
        if (! $transport instanceof SmtpTransport) {
            throw new RuntimeException('The mailer is not an SMTP mailer.');
        }

        try {
            $transport->start();
        } finally {
            $transport->stop();
        }
    }
}
