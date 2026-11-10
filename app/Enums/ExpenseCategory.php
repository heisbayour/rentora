<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum ExpenseCategory: string implements HasPresentation
{
    use HasOptions;

    case Repairs = 'repairs';
    case Utilities = 'utilities';
    case Cleaning = 'cleaning';
    case Security = 'security';
    case Taxes = 'taxes';
    case Insurance = 'insurance';
    case Salaries = 'salaries';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.expense_category.'.$this->value);
    }

    public function color(): string
    {
        return 'neutral';
    }
}
