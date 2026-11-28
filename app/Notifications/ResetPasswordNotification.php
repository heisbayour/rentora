<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Channels\DirectMailChannel;
use Illuminate\Auth\Notifications\ResetPassword;

/** Laravel's reset email, sent through the Settings SMTP server instead of the .env mailer. */
class ResetPasswordNotification extends ResetPassword
{
    /** @return list<string> */
    public function via($notifiable): array
    {
        return [DirectMailChannel::class];
    }
}
