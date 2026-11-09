<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query to the current organization. With no current
 * organization (console, installer) the query is left unscoped.
 */
class OrganizationScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $id = app(CurrentOrganization::class)->id();
        if ($id !== null) {
            $builder->where($model->qualifyColumn('organization_id'), $id);
        }
    }
}
