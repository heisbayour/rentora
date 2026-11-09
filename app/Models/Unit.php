<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaseStatus;
use App\Enums\RentFrequency;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Money;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property string $label
 * @property UnitType $type
 * @property int $bedrooms
 * @property int $bathrooms
 * @property string|null $size_sqm
 * @property int $rent_amount
 * @property RentFrequency $rent_frequency
 * @property int $service_charge_amount
 * @property int $caution_deposit_amount
 * @property UnitStatus $status
 * @property bool $is_listed
 * @property string|null $description
 * @property-read Property $property
 */
class Unit extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'property_id',
        'label',
        'type',
        'bedrooms',
        'bathrooms',
        'size_sqm',
        'rent_amount',
        'rent_frequency',
        'service_charge_amount',
        'caution_deposit_amount',
        'status',
        'is_listed',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => UnitType::class,
            'rent_frequency' => RentFrequency::class,
            'status' => UnitStatus::class,
            'is_listed' => 'boolean',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'rent_amount' => 'integer',
            'service_charge_amount' => 'integer',
            'caution_deposit_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return HasMany<UnitPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(UnitPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Lease, $this> */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /** @return HasOne<Lease, $this> */
    public function activeLease(): HasOne
    {
        return $this->hasOne(Lease::class)->where('status', LeaseStatus::Active->value)->latestOfMany('start_date');
    }

    public function hasActiveLease(): bool
    {
        return $this->leases()->where('status', LeaseStatus::Active->value)->exists();
    }

    /** Used by BelongsToOrganization when no organization was set explicitly. */
    public function parentOrganizationId(): ?int
    {
        $id = Property::query()->withoutGlobalScopes()->whereKey($this->property_id)->value('organization_id');

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

    public function cautionDeposit(): Money
    {
        return Money::forOrganization($this->caution_deposit_amount);
    }

    /** "Flat 3B, Lekki Gardens"; needs the property relation loaded. */
    public function displayName(): string
    {
        return $this->label.', '.$this->property->name;
    }

    /**
     * Units shown on the public website (spec 8.11).
     *
     * @param  Builder<Unit>  $query
     * @return Builder<Unit>
     */
    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query->where('is_listed', true)
            ->whereIn('status', [UnitStatus::Vacant->value, UnitStatus::Reserved->value]);
    }
}
