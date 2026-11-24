<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Records that a reminder of one kind was sent for an invoice or lease, so the
 * daily tasks never send it twice (spec 10.1).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $remindable_type
 * @property int $remindable_id
 * @property string $kind
 */
class ReminderLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['organization_id', 'remindable_type', 'remindable_id', 'kind', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /** Claims a reminder slot; false when it was already taken. */
    public static function claim(Model $subject, string $kind): bool
    {
        $inserted = static::query()->insertOrIgnore([
            'organization_id' => $subject->getAttribute('organization_id'),
            'remindable_type' => $subject->getMorphClass(),
            'remindable_id' => $subject->getKey(),
            'kind' => $kind,
            'sent_at' => now(),
        ]);

        return $inserted === 1;
    }
}
