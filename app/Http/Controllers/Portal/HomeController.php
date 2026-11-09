<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Tenant portal home. Balance, lease and repairs arrive in Phase 9 (spec 8.13). */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('portal.home');
    }
}
