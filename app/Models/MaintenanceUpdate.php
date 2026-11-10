<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MaintenanceStatus;
use Database\Factories\MaintenanceUpdateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry on a request's timeline: comment, status change, assignment or schedule.
 * Internal entries are never shown to tenants (spec 8.7).
 *
 * @property int $id
 * @property int $maintenance_request_id
 * @property int|null $user_id
 * @property int|null $vendor_id
 * @property string $type
 * @property string|null $body
 * @property MaintenanceStatus|null $old_status
 * @property MaintenanceStatus|null $new_status
 * @property bool $is_internal
 * @property-read User|null $user
 * @property-read Vendor|null $vendor
 */
class MaintenanceUpdate extends Model
{
    /** @use HasFactory<MaintenanceUpdateFactory> */
    use HasFactory;

    public const COMMENT = 'comment';

    public const STATUS_CHANGE = 'status_change';

    public const ASSIGNMENT = 'assignment';

    public const SCHEDULE = 'schedule';

    protected $fillable = ['maintenance_request_id', 'user_id', 'vendor_id', 'type', 'body', 'old_status', 'new_status', 'is_internal'];

    protected function casts(): array
    {
        return [
            'old_status' => MaintenanceStatus::class,
            'new_status' => MaintenanceStatus::class,
            'is_internal' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function authorName(): string
    {
        return match (true) {
            $this->user_id !== null => $this->user->name,
            $this->vendor_id !== null => $this->vendor->name,
            default => __('maintenance.system'),
        };
    }

    /**
     * @param  Builder<MaintenanceUpdate>  $query
     * @return Builder<MaintenanceUpdate>
     */
    public function scopeVisibleToTenant(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }
}
