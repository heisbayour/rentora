<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Read and write through App\Services\SettingsService, which handles encryption. */
class Setting extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected $fillable = ['organization_id', 'key', 'value', 'is_encrypted'];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }
}
