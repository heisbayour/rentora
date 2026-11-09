<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum DocumentVisibility: string implements HasPresentation
{
    use HasOptions;

    case Internal = 'internal';
    case Tenant = 'tenant';

    public function label(): string
    {
        return __('enums.document_visibility.'.$this->value);
    }

    public function color(): string
    {
        return $this === self::Tenant ? 'info' : 'neutral';
    }
}
