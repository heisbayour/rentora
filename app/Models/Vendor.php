<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * External artisan with no account; works through a signed job link (spec 1.2).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $trade
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $notes
 * @property bool $is_active
 */
class Vendor extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    use Notifiable;

    protected $fillable = ['name', 'trade', 'phone', 'email', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<MaintenanceRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }
}
