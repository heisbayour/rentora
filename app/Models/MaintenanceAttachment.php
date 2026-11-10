<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MaintenanceAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A private photo on a repair request, served through FileController.
 *
 * @property int $id
 * @property int $maintenance_request_id
 * @property string $path
 * @property int|null $uploaded_by_user_id
 * @property bool $uploaded_by_vendor
 * @property-read MaintenanceRequest $request
 */
class MaintenanceAttachment extends Model
{
    /** @use HasFactory<MaintenanceAttachmentFactory> */
    use HasFactory;

    protected $fillable = ['maintenance_request_id', 'path', 'uploaded_by_user_id', 'uploaded_by_vendor'];

    protected function casts(): array
    {
        return ['uploaded_by_vendor' => 'boolean'];
    }

    /** @return BelongsTo<MaintenanceRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }
}
