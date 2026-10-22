@props(['role'])

@php
    $user    = auth()->user();
    $initial = strtoupper(mb_substr($user->nama_lengkap, 0, 1));
    $branch  = $user->cabang?->nama_cabang ?? $user->dc?->nama_dc ?? 'Seluruh Cabang';
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
        <span>KopSync</span>
        <h2>@yield('title')</h2>
        <p>@yield('description')</p>
    </div>

    <div class="header-right">

        @if ($role === 'admin')
            <form class="admin-header-search" method="GET" action="{{ route('admin.pengguna.index') }}">
                <input
                    type="search"
                    name="q"
                    value="{{ request()->routeIs('admin.pengguna.index') ? request('q') : '' }}"
                    placeholder="Cari pengguna..."
                    aria-label="Cari pengguna"
                >
                <button type="submit" aria-label="Cari">⌕</button>
            </form>
            <a class="primary-button admin-quick-action" href="{{ route('admin.pengguna.create') }}">+ Pengguna</a>
        @endif

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
                <strong>{{ $user->nama_lengkap }}</strong>
                <span>{{ $branch }}</span>
            </div>
        </div>

    </div>

</header>