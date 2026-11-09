<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Money;
use Database\Factories\InvoiceFactory;
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
 * @property int|null $lease_id
 * @property int $tenant_id
 * @property int|null $unit_id
 * @property InvoiceType $type
 * @property string|null $description
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property int $amount
 * @property int $amount_paid
 * @property Carbon $due_date
 * @property InvoiceStatus $status
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property int|null $parent_invoice_id
 * @property Carbon|null $issued_at
 * @property-read Tenant $tenant
 * @property-read Lease|null $lease
 * @property-read Unit|null $unit
 */
class Invoice extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
        'lease_id',
        'tenant_id',
        'unit_id',
        'type',
        'description',
        'period_start',
        'period_end',
        'amount',
        'due_date',
        'parent_invoice_id',
        'issued_at',
    ];

    protected $attributes = [
        'amount_paid' => 0,
        'status' => 'unpaid',
    ];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'amount' => 'integer',
            'amount_paid' => 'integer',
            'voided_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /** @return BelongsTo<Invoice, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    /** @return HasOne<Invoice, $this> */
    public function lateFee(): HasOne
    {
        return $this->hasOne(Invoice::class, 'parent_invoice_id');
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function parentOrganizationId(): ?int
    {
        $id = Tenant::query()->withoutGlobalScopes()->whereKey($this->tenant_id)->value('organization_id');

        return $id === null ? null : (int) $id;
    }

    public function outstandingMinor(): int
    {
        return $this->status === InvoiceStatus::Void ? 0 : max(0, $this->amount - $this->amount_paid);
    }

    public function total(): Money
    {
        return Money::forOrganization($this->amount);
    }

    public function paid(): Money
    {
        return Money::forOrganization($this->amount_paid);
    }

    public function outstanding(): Money
    {
        return Money::forOrganization($this->outstandingMinor());
    }

    public function isOverdue(?Carbon $today = null): bool
    {
        return in_array($this->status->value, InvoiceStatus::open(), true)
            && $this->due_date->lt(($today ?? today())->copy()->startOfDay());
    }

    /** Recomputes status from amount_paid. Call after changing amount_paid, inside the money transaction. */
    public function refreshStatus(): void
    {
        if ($this->status === InvoiceStatus::Void) {
            return;
        }
        $this->status = match (true) {
            $this->amount_paid <= 0 => InvoiceStatus::Unpaid,
            $this->amount_paid >= $this->amount => InvoiceStatus::Paid,
            default => InvoiceStatus::Partial,
        };
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', InvoiceStatus::open());
    }
}
