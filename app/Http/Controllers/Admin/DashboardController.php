<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Landlord dashboard. KPIs, charts and lists arrive in Phase 10 (spec 8.2). */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard');
    }
}
