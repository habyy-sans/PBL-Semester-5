@php
    // DEMO: role dibaca dari URL (/kasir/..., /ic/..., dst.)
    // Nanti diganti: role dari akun yang sedang login.
    $segment = (string) request()->segment(1);
    $role = array_key_exists($segment, config('menu.roles')) ? $segment : 'manager';
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>KopSync - POS Management</title>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Tailwind + style.css + Alpine -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body x-data="{ sidebarOpen: false }">

    <x-sidebar :role="$role" />

    <main class="main-content">

        <x-header :role="$role" />

        <div class="page-container">
            @yield('content')
        </div>

    </main>

    @stack('scripts')

</body>

</html>