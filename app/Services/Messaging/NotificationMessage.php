<?php

declare(strict_types=1);

namespace App\Services\Messaging;

/** What one notification says, shared by the inbox, email and SMS channels. */
final class NotificationMessage
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $link = null,
        public readonly ?string $actionLabel = null,
        public readonly ?string $sms = null,
    ) {}

    /** SMS text: the dedicated short text, or title, body and link. */
    public function smsText(string $organizationName): string
    {
        $text = $this->sms ?? trim($this->title.'. '.$this->body);
        if ($this->link !== null && ! str_contains($text, $this->link)) {
            $text .= ' '.$this->link;
        }

        return $organizationName.': '.$text;
    }
}
