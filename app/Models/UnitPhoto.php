<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UnitPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Public photo in public/uploads/units. Scoped through its unit.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $path
 * @property int $sort_order
 */
class UnitPhoto extends Model
{
    /** @use HasFactory<UnitPhotoFactory> */
    use HasFactory;

    protected $fillable = ['unit_id', 'path', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function url(): string
    {
        return asset('uploads/'.$this->path);
    }
}
