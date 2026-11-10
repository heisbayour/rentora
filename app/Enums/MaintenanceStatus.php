<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasPresentation;

enum MaintenanceStatus: string implements HasPresentation
{
    use HasOptions;

    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('enums.maintenance_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Acknowledged, self::Scheduled, self::InProgress => 'info',
            self::OnHold => 'neutral',
            self::Resolved, self::Closed => 'success',
            self::Cancelled => 'neutral',
        };
    }

    /** Allowed next statuses (spec 8.7). @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Submitted => [self::Acknowledged, self::Cancelled],
            self::Acknowledged => [self::Scheduled, self::InProgress, self::OnHold, self::Cancelled],
            self::Scheduled => [self::InProgress, self::OnHold, self::Cancelled],
            self::InProgress => [self::Resolved, self::OnHold],
            self::OnHold => [self::InProgress, self::Scheduled, self::Cancelled],
            self::Resolved => [self::Closed, self::InProgress],
            self::Closed, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->transitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Closed, self::Cancelled], true);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return array_values(array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isOpen())));
    }
}
