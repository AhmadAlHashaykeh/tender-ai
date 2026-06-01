<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TenderAI')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Base app styles (variables, reset, global — needed by login.css overrides) --}}
    @vite(['resources/css/app.css'])

    {{-- Login-specific styles — loaded only on auth pages --}}
    @vite(['resources/css/pages/login.css'])
</head>
<body class="auth-page">
    @yield('content')

    {{--
      Page scripts (login.js via @push('scripts')) are output here.
      Lucide is loaded as a synchronous script AFTER the deferred Vite module
      so it is guaranteed to be present when login.js DOMContentLoaded fires.
    --}}
    @stack('scripts')

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') { lucide.createIcons(); }
        });
    </script>
</body>
</html>
