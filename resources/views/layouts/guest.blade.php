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

    {{-- Tailwind CDN: required by the landing page which is built with utility classes --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary:            '#0D85E6',
                        secondary:          '#7C3AED',
                        cyan:               '#06B6D4',
                        foreground:         '#0f172a',
                        background:         '#ffffff',
                        muted:              '#f1f5f9',
                        'muted-foreground': '#64748B',
                        border:             '#e2e8f0',
                        card:               '#ffffff',
                    },
                    opacity: { '8': '0.08', '15': '0.15' }
                }
            }
        }
    </script>

    {{-- Lucide icons: used by the landing page --}}
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    {{-- Page-specific head styles (pushed by child views) --}}
    @stack('head-styles')

    {{-- Compiled app CSS (variables, reset, global, landing.css, etc.) --}}
    @vite(['resources/css/app.css'])
</head>
<body>
    @yield('content')

    @stack('scripts')

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') { lucide.createIcons(); }
        });
    </script>
</body>
</html>
