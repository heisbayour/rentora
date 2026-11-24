<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesInbox;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Staff in-app inbox (spec 8.8). */
class NotificationController extends Controller
{
    use HandlesInbox;

    public function index(Request $request): View
    {
        return view('admin.notifications.index', [
            'notifications' => $this->inboxPage($request),
            'filter' => $request->query('filter') === 'unread' ? 'unread' : 'all',
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        return $this->openNotification($request, $notification, 'app.notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        return $this->markAllRead($request);
    }
}
