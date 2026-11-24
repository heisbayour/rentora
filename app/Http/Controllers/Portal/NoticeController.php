<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Announcements sent to the signed-in tenant (spec 8.13 Notices). */
class NoticeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tenant = $request->user()->tenant ?? abort(404);

        return view('portal.notices', [
            'notices' => $tenant->announcements()->latest('announcements.id')->paginate((int) config('rentora.per_page')),
        ]);
    }
}
