@props(['role'])

@php
    $sections = config("menu.menus.$role", []);
@endphp

<aside id="sidebar" class="sidebar" :class="{ 'show': sidebarOpen }" aria-label="Menu utama">

    <!-- LOGO -->
    <div class="sidebar-logo sidebar-logo-row">
        <div class="flex items-center gap-3 min-w-0">
            <div class="logo-box">
                <img src="{{ asset('image/logo_kopsync.png') }}" alt="Logo KopSync" width="40" height="40">
            </div>

            <div>
                <h1>KopSync</h1>
                <span>POS Management</span>
            </div>
        </div>

        <button
            type="button"
            class="sidebar-close-button"
            @click="sidebarOpen = false; $nextTick(() => document.querySelector('.mobile-menu-button')?.focus())"
            aria-label="Tutup menu navigasi"
        >
            <x-icon name="x" size="1.1em" />
        </button>
    </div>

    <!-- MENU (sesuai role) -->
    <nav class="sidebar-nav">

        @foreach ($sections as $title => $items)
            <div class="menu-section">

                <p class="menu-title">{{ $title }}</p>

                @foreach ($items as $item)
                    @php
                        $isActive = request()->routeIs($item['route'])
                            || (str_ends_with($item['route'], '.index') && request()->routeIs(substr($item['route'], 0, -6) . '.*'));
                    @endphp
                    <a
                        href="{{ route($item['route']) }}"
                        class="menu-item {{ $isActive ? 'active' : '' }}"
                        @if ($isActive) aria-current="page" @endif
                        @click="sidebarOpen = false"
                    >
                        @if (! empty($item['lucide']))
                            <x-icon :name="$item['lucide']" size="1.05em" class="sidebar-item-icon" />
                        @endif
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach

            </div>
        @endforeach

    </nav>

    <!-- LOGOUT -->
    <div class="sidebar-bottom">
        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="logout-button">
                Logout
            </button>
        </form>
    </div>

</aside>
