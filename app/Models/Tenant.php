<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaseStatus;
use App\Enums\TenantStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasDocuments;
use App\Models\Contracts\Documentable;
use App\Support\Money;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $user_id
 * @property string $full_name
 * @property string|null $email
 * @property string $phone
 * @property string|null $alt_phone
 * @property string|null $occupation
 * @property string|null $employer
 * @property string|null $id_type
 * @property string|null $id_number
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $notes
 * @property TenantStatus $status
 * @property Carbon|null $invite_sent_at
 * @property array{sms?: bool, email?: bool}|null $notification_prefs
 * @property int $credit_balance
 * @property-read User|null $user
 * @property-read Lease|null $activeLease
 */
class Tenant extends Model implements Documentable
{
    use BelongsToOrganization;
    use HasDocuments;

    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use Notifiable;
    use SoftDeletes;

    public const DEFAULT_PREFS = ['sms' => true, 'email' => true];

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'alt_phone',
        'occupation',
        'employer',
        'id_type',
        'id_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
        'status',
        'notification_prefs',
    ];

    protected $attributes = [
        'status' => 'pending_invite',
        'credit_balance' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'invite_sent_at' => 'datetime',
            'notification_prefs' => 'array',
            'credit_balance' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Sum still owed on open invoices. */
    public function outstanding(): Money
    {
        $owed = (int) $this->invoices()->open()->sum(DB::raw('amount - amount_paid'));

        return Money::forOrganization($owed);
    }

    public function creditBalance(): Money
    {
        return Money::forOrganization($this->credit_balance);
    }

    /** Whether the tenant wants non-critical messages on a channel (spec 8.8). */
    public function wants(string $channel): bool
    {
        return (bool) (($this->notification_prefs ?? self::DEFAULT_PREFS)[$channel] ?? true);
    }

    public function hasPortalAccount(): bool
    {
        return $this->user_id !== null;
    }
}
