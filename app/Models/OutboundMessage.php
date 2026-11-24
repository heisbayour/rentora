<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\OutboundMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One email or SMS waiting in (or sent from) the outbox (spec 9.1).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $channel
 * @property string|null $event
 * @property string $recipient
 * @property string|null $recipient_name
 * @property string|null $subject
 * @property string $body
 * @property string|null $html_body
 * @property MessageStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property string|null $related_type
 * @property int|null $related_id
 * @property Carbon|null $send_after
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 */
class OutboundMessage extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<OutboundMessageFactory> */
    use HasFactory;

    public const EMAIL = 'email';

    public const SMS = 'sms';

    protected $fillable = [
        'organization_id',
        'channel',
        'event',
        'recipient',
        'recipient_name',
        'subject',
        'body',
        'html_body',
        'status',
        'attempts',
        'last_error',
        'related_type',
        'related_id',
        'send_after',
        'sent_at',
    ];

    protected $attributes = [
        'status' => 'queued',
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'attempts' => 'integer',
            'send_after' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
