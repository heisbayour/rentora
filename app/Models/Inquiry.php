<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InquiryStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\InquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message from the public website (spec 8.11).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $unit_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string $message
 * @property InquiryStatus $status
 * @property string|null $ip
 * @property Carbon $created_at
 * @property-read Unit|null $unit
 */
class Inquiry extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<InquiryFactory> */
    use HasFactory;

    protected $fillable = ['unit_id', 'name', 'email', 'phone', 'message', 'status'];

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return ['status' => InquiryStatus::class];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }
}
