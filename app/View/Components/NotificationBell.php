<?php

namespace App\View\Components;

use App\Models\Notification;
use App\Models\Pengguna;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class NotificationBell extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof Pengguna && Notification::enabledFor($user->role_key), 403);

        return view('components.notification-bell', [
            'config' => [
                'unread' => Notification::visibleTo($user)->unread()->count(),
                'urls' => [
                    'list' => route('notifications.index', [], false),
                    'count' => route('notifications.unread-count', [], false),
                    'readAll' => route('notifications.read-all', [], false),
                ],
            ],
        ]);
    }
}
