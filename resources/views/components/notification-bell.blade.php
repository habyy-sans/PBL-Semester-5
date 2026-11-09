@php
    $user = auth()->user();

    // Angka awal dari server supaya badge tidak berkedip dari 0.
    // URL dibuat relatif agar aman jika APP_URL berbeda dengan host yang dipakai.
    $config = [
        'unread' => \App\Models\Notification::visibleTo($user)->unread()->count(),
        'urls' => [
            'list'    => route('notifications.index', [], false),
            'count'   => route('notifications.unread-count', [], false),
            'readAll' => route('notifications.read-all', [], false),
        ],
    ];
@endphp

<div
    class="notification-wrapper"
    x-data="notificationBell({{ Js::from($config) }})"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>

    <button
        type="button"
        class="notification-button"
        @click="toggle()"
        :aria-expanded="open ? 'true' : 'false'"
        :aria-label="unread > 0 ? 'Notifikasi, ' + unread + ' belum dibaca' : 'Notifikasi'"
        aria-haspopup="true"
    >
        🔔
        <b class="notification-badge" x-show="unread > 0" x-cloak x-text="badgeText"></b>
    </button>

    <div
        class="notification-panel"
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        role="dialog"
        aria-label="Daftar notifikasi"
    >

        <div class="notification-panel-header">
            <strong>Notifikasi</strong>

            <button
                type="button"
                class="notification-readall"
                @click="markAllRead()"
                :disabled="unread === 0 || busyAll"
            >
                Tandai semua dibaca
            </button>
        </div>

        <div class="notification-list" @scroll.passive="onScroll($el)">

            <template x-if="loading">
                <p class="notification-state">Memuat notifikasi...</p>
            </template>

            <template x-if="!loading && loadError && items.length === 0">
                <p class="notification-state">
                    Gagal memuat notifikasi.
                    <button type="button" @click="loadList()">Coba lagi</button>
                </p>
            </template>

            <template x-if="!loading && !loadError && items.length === 0">
                <p class="notification-state">Tidak ada notifikasi</p>
            </template>

            <template x-for="item in items" :key="item.id">
                <button
                    type="button"
                    class="notification-item"
                    :class="{ 'is-unread': !item.is_read }"
                    :disabled="busyId === item.id"
                    @click="openItem(item)"
                >
                    <span class="notification-icon" :class="{ 'is-danger': isDanger(item.type) }" x-text="icon(item.type)"></span>

                    <span class="notification-body">
                        <span class="notification-title" x-text="item.title"></span>
                        <span class="notification-desc" x-text="item.description"></span>
                        <span class="notification-time" x-text="timeAgo(item.created_at)"></span>
                    </span>

                    <span class="notification-dot" x-show="!item.is_read" aria-label="Belum dibaca"></span>
                </button>
            </template>

            <p class="notification-state" x-show="loadingMore">Memuat notifikasi lama...</p>

        </div>

        <p class="notification-error" x-show="actionError" x-text="actionError"></p>

    </div>

</div>