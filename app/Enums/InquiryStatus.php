<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum InquiryStatus: string implements HasPresentation
{
    use HasOptions;

    case New = 'new';
    case Contacted = 'contacted';
    case Closed = 'closed';

    public function label(): string
    {
        return __('enums.inquiry_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'warning',
            self::Closed => 'neutral',
        };
    }
}
