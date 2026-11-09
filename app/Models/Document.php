<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentVisibility;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * A private file attached to any model (property, tenant, lease, ...).
 * Stored on the private "local" disk and only served by FileController.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $documentable_type
 * @property int $documentable_id
 * @property string $name
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property DocumentVisibility $visibility
 * @property int|null $uploaded_by
 */
class Document extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $fillable = ['name', 'path', 'mime', 'size', 'visibility', 'uploaded_by'];

    protected function casts(): array
    {
        return [
            'visibility' => DocumentVisibility::class,
            'size' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Used by BelongsToOrganization when no organization was set explicitly. */
    public function parentOrganizationId(): ?int
    {
        $class = Relation::getMorphedModel($this->documentable_type) ?? $this->documentable_type;
        if (! class_exists($class)) {
            return null;
        }
        $id = $class::query()->withoutGlobalScopes()->whereKey($this->documentable_id)->value('organization_id');

        return $id === null ? null : (int) $id;
    }

    public function humanSize(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : max(1, (int) ceil($this->size / 1024)).' KB';
    }
}
