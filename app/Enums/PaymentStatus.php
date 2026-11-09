<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum PaymentStatus: string implements HasPresentation
{
    use HasOptions;

    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('enums.payment_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Successful => 'success',
            self::Failed, self::Rejected => 'danger',
        };
    }
}
