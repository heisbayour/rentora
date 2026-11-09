<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum InvoiceStatus: string implements HasPresentation
{
    use HasOptions;

    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return __('enums.invoice_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Void => 'neutral',
        };
    }

    /** Statuses that still owe money. @return list<string> */
    public static function open(): array
    {
        return [self::Unpaid->value, self::Partial->value];
    }
}
