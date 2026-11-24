<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The in-app inbox (spec 8.8), shared by the console and the portal. Every
 * lookup goes through the signed-in user's own notifications, so another
 * user's notification id is a 404.
 */
trait HandlesInbox
{
    /** @return LengthAwarePaginator<int, DatabaseNotification> */
    protected function inboxPage(Request $request): LengthAwarePaginator
    {
        /** @var User $user */
        $user = $request->user();

        return $user->notifications()
            ->when($request->query('filter') === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->paginate((int) config('rentora.per_page'))
            ->withQueryString();
    }

    protected function openNotification(Request $request, string $id, string $fallbackRoute): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $notification = $user->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $link = $notification->data['link'] ?? null;

        return is_string($link) && str_starts_with($link, (string) config('app.url'))
            ? redirect()->to($link)
            : redirect()->route($fallbackRoute);
    }

    protected function markAllRead(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('toast', __('messaging.inbox.marked'));
    }
}
