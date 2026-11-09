<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SystemStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Install-wide key/value state: scheduler lock and last-run times. Not organization scoped. */
class SystemState extends Model
{
    /** @use HasFactory<SystemStateFactory> */
    use HasFactory;

    public const CREATED_AT = null;

    protected $table = 'system_state';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key): ?string
    {
        return static::query()->where('key', $key)->value('value');
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
