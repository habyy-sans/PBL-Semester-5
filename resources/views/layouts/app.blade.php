@php
    // Role diambil dari akun yang sedang login (sudah lewat middleware auth + role).
    $user = auth()->user();
    $role = $user->role_key;
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'KopSync') - KopSync</title>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Tailwind + style.css + Alpine -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Icon (Iconify / Lucide) untuk JavaScript -->
    <x-icon-runtime />
</head>

<body x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" @resize.window="if (window.innerWidth >= 1024) sidebarOpen = false">

    <a href="#main-content" class="skip-link">Langsung ke konten utama</a>

    <div
        class="sidebar-backdrop"
        :class="{ 'show': sidebarOpen }"
        @click="sidebarOpen = false"
        aria-hidden="true"
    ></div>

    <x-sidebar :role="$role" />

    <main id="main-content" class="main-content">

        <x-header :role="$role" />

        <div class="page-container">
            @yield('content')
        </div>

    </main>

    @stack('scripts')

</body>

</html>
