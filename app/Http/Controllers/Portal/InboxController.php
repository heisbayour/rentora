<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Concerns\HandlesInbox;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tenant in-app inbox (spec 8.13). */
class InboxController extends Controller
{
    use HandlesInbox;

    public function index(Request $request): View
    {
        return view('portal.inbox', [
            'notifications' => $this->inboxPage($request),
            'filter' => $request->query('filter') === 'unread' ? 'unread' : 'all',
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        return $this->openNotification($request, $notification, 'portal.inbox');
    }

    public function readAll(Request $request): RedirectResponse
    {
        return $this->markAllRead($request);
    }
}
