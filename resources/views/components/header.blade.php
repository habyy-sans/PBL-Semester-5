@props(['role'])

@php
    $meta    = config("menu.roles.$role");
    $initial = strtoupper(mb_substr($meta['name'], 0, 1));
@endphp

<header class="top-header">

    <button
        type="button"
        class="mobile-menu-button"
        @click="sidebarOpen = !sidebarOpen"
    >
        ☰
    </button>

    <div class="breadcrumb">
        <span>KopSync / Management System</span>
        <h2>@yield('title')</h2>
        <p>@yield('description')</p>
    </div>

    <div class="header-right">

        <button
            type="button"
            class="notification-button"
            @click="alert('Belum ada implementasi notifikasi.')"
        >
            🔔
            <span></span>
        </button>

        <div class="profile">
            <div class="profile-avatar">{{ $initial }}</div>

            <div class="profile-info">
                <strong>{{ $meta['name'] }}</strong>
                <span>{{ $meta['branch'] }}</span>
            </div>
        </div>

    </div>

</header>