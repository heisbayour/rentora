<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum MessageStatus: string implements HasPresentation
{
    use HasOptions;

    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return __('enums.message_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued, self::Sending => 'info',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
