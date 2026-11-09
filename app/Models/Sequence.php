<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\SequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Document number counters. Use App\Services\SequenceService, never increment directly. */
class Sequence extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<SequenceFactory> */
    use HasFactory;

    protected $fillable = ['organization_id', 'key', 'year', 'next_value'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'next_value' => 'integer'];
    }
}
