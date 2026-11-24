@props(['role'])

@php
    $user = auth()->user();
    $initial = strtoupper(mb_substr($user->nama_lengkap, 0, 1));
    $branch = $role === 'admin'
        ? 'Admin Sistem'
        : ($user->cabang?->nama_cabang ?? $user->dc?->nama_dc ?? 'Seluruh Cabang');
    $headerCustomContent = trim($__env->yieldPushContent('header_custom'));
    $hasHeaderCustom = $headerCustomContent !== '';
@endphp

<header class="top-header">

    <button
        type="button"
        class="mobile-menu-button"
        @click="sidebarOpen = !sidebarOpen"
        :aria-expanded="sidebarOpen ? 'true' : 'false'"
        aria-controls="sidebar"
        aria-label="Buka menu navigasi"
    >
        <x-icon name="menu" size="1.25em" />
        <span class="mobile-menu-label">Menu</span>
    </button>

    <div class="breadcrumb">
        <span>KopSync</span>
        <h2>@yield('title')</h2>
        <p>@yield('description')</p>
    </div>

    <div class="header-right {{ $hasHeaderCustom ? 'has-custom' : '' }}">

        @if ($hasHeaderCustom)
            <div class="header-custom-slot">{!! $headerCustomContent !!}</div>
        @endif

        @if (in_array($role, ['dc', 'manager', 'ic'], true))
            <x-notification-bell />
        @endif

        <div class="profile">
            <div class="profile-avatar" aria-hidden="true">{{ $initial }}</div>

            <div class="profile-info">
                <strong>{{ $user->nama_lengkap }}</strong>
                <span>{{ $branch }}</span>
            </div>
        </div>

    </div>

</header>
