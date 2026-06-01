<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-url" content="{{ url('/') }}">
    <title>@yield('title', 'TenderAI')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: { sans: ['Inter', 'sans-serif'] },
            colors: {
              primary: '#0D85E6',
              secondary: '#7C3AED',
              cyan: '#06B6D4',
              foreground: '#0f172a',
              background: '#ffffff',
              muted: '#f1f5f9',
              'muted-foreground': '#64748B',
              border: '#e2e8f0',
              card: '#ffffff',
            },
            opacity: { '8': '0.08', '15': '0.15' }
          }
        }
      }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @stack('styles')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="container">
        <div class="app-layout">
            <div id="sidebarOverlay" class="sidebar-overlay"></div>

            @include('partials.sidebar')

            <div class="main-content">
                @include('partials.topbar')

                @yield('content')
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
