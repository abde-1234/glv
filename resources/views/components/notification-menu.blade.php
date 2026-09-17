@php
    $notificationUser = auth()->user();
    $headerNotifications = $notificationUser->notifications()->latest()->limit(8)->get();
    $unreadNotificationCount = $notificationUser->unreadNotifications()->count();
@endphp
<details class="notification-menu">
    <summary aria-label="Notifications">
        <x-icon name="bell" />
        @if ($unreadNotificationCount > 0)<b>{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</b>@endif
    </summary>
    <div class="notification-dropdown">
        <header>
            <span><strong>Notifications</strong><small>{{ $unreadNotificationCount }} non lue(s)</small></span>
            @if ($unreadNotificationCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button type="submit">Tout marquer comme lu</button></form>
            @endif
        </header>
        <div class="notification-list">
            @forelse ($headerNotifications as $notification)
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}" @class(['notification-item', 'is-unread' => $notification->unread()])>
                    @csrf @method('PATCH')
                    <button type="submit">
                        <i></i><span><strong>{{ $notification->data['title'] ?? 'Notification' }}</strong><small>{{ $notification->data['message'] ?? '' }}</small><time>{{ $notification->created_at->format('d/m/Y à H:i') }}</time></span>
                    </button>
                </form>
            @empty
                <p class="notification-empty">Aucune notification.</p>
            @endforelse
        </div>
    </div>
</details>
