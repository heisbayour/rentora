<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current organization and fills organization_id on create.
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') !== null) {
                return;
            }
            // A child record (lease, unit, document) belongs to its parent's organization,
            // which also covers work done outside a request (console, scheduler, tests).
            $organizationId = method_exists($model, 'parentOrganizationId') ? $model->parentOrganizationId() : null;
            $model->setAttribute('organization_id', $organizationId ?? app(CurrentOrganization::class)->id());
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
