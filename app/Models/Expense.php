<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Money;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property int|null $unit_id
 * @property int|null $maintenance_request_id
 * @property ExpenseCategory $category
 * @property int $amount
 * @property Carbon $incurred_on
 * @property string $description
 * @property string|null $receipt_path
 * @property int|null $recorded_by
 * @property-read Property $property
 * @property-read Unit|null $unit
 */
class Expense extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    protected $fillable = [
        'property_id',
        'unit_id',
        'maintenance_request_id',
        'category',
        'amount',
        'incurred_on',
        'description',
        'receipt_path',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'amount' => 'integer',
            'incurred_on' => 'date',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /** @return BelongsTo<MaintenanceRequest, $this> */
    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function parentOrganizationId(): ?int
    {
        $id = Property::query()->withoutGlobalScopes()->withTrashed()->whereKey($this->property_id)->value('organization_id');

        return $id === null ? null : (int) $id;
    }

    public function money(): Money
    {
        return Money::forOrganization($this->amount);
    }
}
