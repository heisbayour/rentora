<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PropertyType;
use App\Enums\UnitStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasDocuments;
use App\Models\Contracts\Documentable;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $code
 * @property PropertyType $type
 * @property string $address
 * @property string|null $city
 * @property string|null $state
 * @property string $country
 * @property string|null $description
 * @property string|null $cover_image_path
 * @property bool $is_listed
 * @property int|null $units_count
 * @property int|null $occupied_units_count
 */
class Property extends Model implements Documentable
{
    use BelongsToOrganization;

    use HasDocuments;

    /** @use HasFactory<PropertyFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'address',
        'city',
        'state',
        'country',
        'description',
        'cover_image_path',
        'is_listed',
    ];

    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'is_listed' => 'boolean',
        ];
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image_path ? asset('uploads/'.$this->cover_image_path) : null;
    }

    public function fullAddress(): string
    {
        return implode(', ', array_filter([$this->address, $this->city, $this->state]));
    }

    /** Whole-number occupancy percentage; needs units_count and occupied_units_count loaded. */
    public function occupancyPercent(): int
    {
        $total = (int) $this->units_count;

        return $total === 0 ? 0 : (int) round(((int) $this->occupied_units_count / $total) * 100);
    }

    /**
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function scopeWithOccupancy($query)
    {
        return $query->withCount([
            'units',
            'units as occupied_units_count' => fn ($q) => $q->where('status', UnitStatus::Occupied->value),
        ]);
    }
}
