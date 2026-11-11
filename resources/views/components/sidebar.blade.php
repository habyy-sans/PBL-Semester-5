@props(['role'])
 
@php
    $sections = config("menu.menus.$role", []);
@endphp
 
<aside id="sidebar" class="sidebar" :class="{ 'show': sidebarOpen }">
 
    <!-- LOGO -->
    <div class="sidebar-logo">
        <div class="logo-box">K</div>
 
        <div>
            <h1>KopSync</h1>
            <span>POS Management</span>
        </div>
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
                    >
                        <span class="menu-icon">{{ $item['icon'] }}</span>
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
                <span>↪</span>
                Logout
            </button>
        </form>
    </div>
 
</aside>
 