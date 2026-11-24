<?php

declare(strict_types=1);

namespace App\Services\Messaging\Sms;

use Illuminate\Support\Facades\Log;

/** Default driver for local and testing: writes the SMS to the log instead of sending it. */
final class LogSmsDriver implements SmsDriver
{
    public function send(string $to, string $message): SmsResult
    {
        Log::info('SMS (log driver)', ['to' => $to, 'message' => $message]);

        return SmsResult::sent('log-'.bin2hex(random_bytes(4)));
    }
}
