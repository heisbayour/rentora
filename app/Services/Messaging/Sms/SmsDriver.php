<?php

declare(strict_types=1);

namespace App\Services\Messaging\Sms;

/** One SMS provider (spec 9.2). $to is already in E.164. */
interface SmsDriver
{
    public function send(string $to, string $message): SmsResult;
}
