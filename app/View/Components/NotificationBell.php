<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

/** Bell dropdown with the unread count and the latest 10 notifications (spec 8.8). */
class NotificationBell extends Component
{
    public int $unread = 0;

    /** @var Collection<int, DatabaseNotification> */
    public Collection $latest;

    public function __construct(
        public string $readRoute = 'app.notifications.read',
        public string $readAllRoute = 'app.notifications.read-all',
        public string $indexRoute = 'app.notifications.index',
    ) {
        $user = auth()->user();
        $this->latest = collect();
        if ($user instanceof User) {
            $this->unread = $user->unreadNotifications()->count();
            $this->latest = $user->notifications()->limit(10)->get();
        }
    }

    public function render(): View
    {
        return view('components.notification-bell');
    }
}
