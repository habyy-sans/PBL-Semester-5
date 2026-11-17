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
        <x-icon name="menu" size="1.4em" />
    </button>

    <div class="breadcrumb">
        <span>KopSync</span>
        <h2>@yield('title')</h2>
        <p>@yield('description')</p>
    </div>

    <div class="header-right">

        @stack('header_custom')
        
        @if ($role === 'admin')           
        @endif

        @if (in_array($role, ['dc', 'manager', 'ic'], true))
            <x-notification-bell />
        @endif

        <div class="profile">
            <div class="profile-avatar">{{ $initial }}</div>

            <div class="profile-info">
                <strong>{{ $user->nama_lengkap }}</strong>
                <span>{{ $branch }}</span>
            </div>
        </div>

    </div>

</header>