<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string $body
 * @property AnnouncementAudience $audience
 * @property list<int>|null $audience_ids
 * @property list<string> $channels
 * @property Carbon|null $sent_at
 * @property int $recipient_count
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read User|null $author
 */
class Announcement extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    public const CHANNELS = ['in_app', 'email', 'sms'];

    protected $fillable = ['title', 'body', 'audience', 'audience_ids', 'channels', 'sent_at', 'recipient_count', 'created_by'];

    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'audience_ids' => 'array',
            'channels' => 'array',
            'sent_at' => 'datetime',
            'recipient_count' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<Tenant, $this> */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class);
    }
}
