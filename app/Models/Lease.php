<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepositStatus;
use App\Enums\LateFeeType;
use App\Enums\LeaseStatus;
use App\Enums\RentFrequency;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasDocuments;
use App\Models\Contracts\Documentable;
use App\Support\Money;
use Database\Factories\LeaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $unit_id
 * @property int $tenant_id
 * @property int|null $renewal_of_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $rent_amount
 * @property RentFrequency $rent_frequency
 * @property int $service_charge_amount
 * @property int $deposit_amount
 * @property DepositStatus $deposit_status
 * @property int $billing_anchor_day
 * @property int $invoice_lead_days
 * @property int $grace_days
 * @property LateFeeType $late_fee_type
 * @property int $late_fee_value kobo for a fixed fee, basis points (1000 = 10%) for a percentage
 * @property LeaseStatus $status
 * @property Carbon|null $terminated_at
 * @property string|null $termination_reason
 * @property-read Unit $unit
 * @property-read Tenant $tenant
 * @property-read Lease|null $renewal
 */
class Lease extends Model implements Documentable
{
    use BelongsToOrganization;
    use HasDocuments;

    /** @use HasFactory<LeaseFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'tenant_id',
        'renewal_of_id',
        'start_date',
        'end_date',
        'rent_amount',
        'rent_frequency',
        'service_charge_amount',
        'deposit_amount',
        'deposit_status',
        'billing_anchor_day',
        'invoice_lead_days',
        'grace_days',
        'late_fee_type',
        'late_fee_value',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'integer',
            'rent_frequency' => RentFrequency::class,
            'service_charge_amount' => 'integer',
            'deposit_amount' => 'integer',
            'deposit_status' => DepositStatus::class,
            'billing_anchor_day' => 'integer',
            'invoice_lead_days' => 'integer',
            'grace_days' => 'integer',
            'late_fee_type' => LateFeeType::class,
            'late_fee_value' => 'integer',
            'status' => LeaseStatus::class,
            'terminated_at' => 'datetime',
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

    /** @return BelongsTo<Lease, $this> */
    public function renewalOf(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'renewal_of_id');
    }

    /** The draft lease that renews this one, if any. @return HasOne<Lease, $this> */
    public function renewal(): HasOne
    {
        return $this->hasOne(Lease::class, 'renewal_of_id');
    }

    /** Used by BelongsToOrganization when no organization was set explicitly. */
    public function parentOrganizationId(): ?int
    {
        $id = Unit::query()->withoutGlobalScopes()->whereKey($this->unit_id)->value('organization_id');

        return $id === null ? null : (int) $id;
    }

    public function rent(): Money
    {
        return Money::forOrganization($this->rent_amount);
    }

    public function serviceCharge(): Money
    {
        return Money::forOrganization($this->service_charge_amount);
    }

    public function deposit(): Money
    {
        return Money::forOrganization($this->deposit_amount);
    }

    public function isActive(): bool
    {
        return $this->status === LeaseStatus::Active;
    }

    /** Days left until end_date (negative once past). */
    public function daysRemaining(?Carbon $today = null): int
    {
        return (int) ($today ?? today())->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false);
    }

    /**
     * @param  Builder<Lease>  $query
     * @return Builder<Lease>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LeaseStatus::Active->value);
    }
}
