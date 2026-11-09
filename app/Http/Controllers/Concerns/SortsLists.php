<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** ?sort=column&dir=asc|desc limited to an allowlist, for every list page (spec 6.4). */
trait SortsLists
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, string>  $allowed  request key => column
     * @return array{0: Builder<TModel>, 1: string, 2: string}
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $default, string $defaultDirection = 'asc'): array
    {
        $sort = (string) $request->query('sort', $default);
        $sort = array_key_exists($sort, $allowed) ? $sort : $default;
        $direction = $request->query('dir') === 'desc' ? 'desc' : ($request->query('dir') === 'asc' ? 'asc' : $defaultDirection);

        return [$query->orderBy($allowed[$sort], $direction), $sort, $direction];
    }
}
