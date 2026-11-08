<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Developer-only page rendering every design-system component and layout
 * (spec Phase 1). Available in the local and testing environments only.
 */
class StyleguideController extends Controller
{
    private const LAYOUTS = ['admin', 'portal', 'public', 'auth', 'install'];

    public function __invoke(Request $request): View
    {
        abort_unless(app()->environment('local', 'testing'), 404);

        $layout = (string) $request->query('layout', 'admin');
        abort_unless(in_array($layout, self::LAYOUTS, true), 404);

        $paginator = new LengthAwarePaginator(
            items: range(1, 25),
            total: 240,
            perPage: 25,
            currentPage: max(1, (int) $request->query('page', 3)),
            options: ['path' => $request->url(), 'query' => ['layout' => $layout]],
        );

        return view('admin.styleguide', [
            'layout' => $layout,
            'layouts' => self::LAYOUTS,
            'paginator' => $paginator,
            'roles' => Role::cases(),
        ]);
    }
}
