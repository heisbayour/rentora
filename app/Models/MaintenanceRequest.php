<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Money;
use Database\Factories\MaintenanceRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $number
 * @property int $unit_id
 * @property int|null $tenant_id
 * @property int|null $reported_by_user_id
 * @property MaintenanceCategory $category
 * @property string $title
 * @property string $description
 * @property MaintenancePriority $priority
 * @property MaintenanceStatus $status
 * @property int|null $assigned_user_id
 * @property int|null $vendor_id
 * @property Carbon|null $scheduled_for
 * @property string|null $preferred_time
 * @property string|null $access_instructions
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property int|null $cost_amount
 * @property int|null $tenant_rating
 * @property string|null $tenant_feedback
 * @property Carbon|null $first_response_at
 * @property Carbon $created_at
 * @property-read Unit $unit
 * @property-read Tenant|null $tenant
 * @property-read Vendor|null $vendor
 * @property-read User|null $assignee
 */
class MaintenanceRequest extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<MaintenanceRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
        'unit_id',
        'tenant_id',
        'reported_by_user_id',
        'category',
        'title',
        'description',
        'priority',
        'preferred_time',
        'access_instructions',
        'scheduled_for',
    ];

    protected $attributes = [
        'status' => 'submitted',
        'priority' => 'normal',
    ];

    protected function casts(): array
    {
        return [
            'category' => MaintenanceCategory::class,
            'priority' => MaintenancePriority::class,
            'status' => MaintenanceStatus::class,
            'scheduled_for' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'first_response_at' => 'datetime',
            'cost_amount' => 'integer',
            'tenant_rating' => 'integer',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /** @return HasMany<MaintenanceUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(MaintenanceUpdate::class)->latest('id');
    }

    /** @return HasMany<MaintenanceAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MaintenanceAttachment::class);
    }

    /** @return HasOne<Expense, $this> */
    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class);
    }

    public function parentOrganizationId(): ?int
    {
        $id = Unit::query()->withoutGlobalScopes()->withTrashed()->whereKey($this->unit_id)->value('organization_id');

        return $id === null ? null : (int) $id;
    }

    public function cost(): ?Money
    {
        return $this->cost_amount === null ? null : Money::forOrganization($this->cost_amount);
    }

    public function isEmergency(): bool
    {
        return $this->priority === MaintenancePriority::Emergency;
    }

    /** Minutes from submission to the first staff response, for the SLA indicator (spec 8.7). */
    public function firstResponseMinutes(): ?int
    {
        return $this->first_response_at === null ? null : (int) $this->created_at->diffInMinutes($this->first_response_at);
    }

    /**
     * @param  Builder<MaintenanceRequest>  $query
     * @return Builder<MaintenanceRequest>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', MaintenanceStatus::openValues());
    }
}
