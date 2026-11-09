<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $tenant_id
 * @property int|null $lease_id
 * @property string $reference
 * @property string|null $receipt_number
 * @property string $verification_code
 * @property int $amount
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property string|null $gateway
 * @property string|null $gateway_reference
 * @property array<string, mixed>|null $gateway_payload
 * @property string|null $proof_path
 * @property Carbon|null $paid_at
 * @property int|null $recorded_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property string|null $note
 * @property Carbon|null $last_verified_at
 * @property int|null $allocated_sum
 * @property-read Tenant $tenant
 */
class Payment extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'lease_id',
        'reference',
        'verification_code',
        'amount',
        'method',
        'gateway',
        'paid_at',
        'proof_path',
        'recorded_by',
        'note',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_verified_at' => 'datetime',
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

    public function money(): Money
    {
        return Money::forOrganization($this->amount);
    }

    /** The part not yet allocated to invoices: the tenant's credit from this payment. */
    public function unallocatedMinor(): int
    {
        $allocated = $this->allocated_sum ?? (int) $this->allocations()->sum('amount');

        return max(0, $this->amount - (int) $allocated);
    }
}
