<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum AnnouncementAudience: string implements HasPresentation
{
    use HasOptions;

    case AllTenants = 'all_tenants';
    case Property = 'property';
    case Tenants = 'tenants';

    public function label(): string
    {
        return __('enums.announcement_audience.'.$this->value);
    }

    public function color(): string
    {
        return 'info';
    }
}
