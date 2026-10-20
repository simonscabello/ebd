<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- Aplica o tema escuro antes da renderização para evitar "piscar" --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        <style>
            html {
                background-color: oklch(0.982 0.006 85);
            }

            html.dark {
                background-color: oklch(0.18 0.008 70);
            }
        </style>

        {{-- PWA --}}
        <link rel="manifest" href="/manifest.webmanifest?v=didache">
        <meta name="theme-color" content="{{ config('brand.theme_color') }}">
        <meta name="color-scheme" content="light dark">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ config('brand.name') }}">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        {{-- Nome próprio do ícone: celulares e o serviço do Google guardam a imagem pelo endereço. --}}
        <link rel="apple-touch-icon" href="/apple-touch-icon-didache.png">

        {{-- Prévia do link (WhatsApp, redes). As páginas de lição completam título e descrição. --}}
        <meta property="og:site_name" content="{{ config('brand.name') }}">
        <meta property="og:image" content="{{ url('/brand/og-didache.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        {{-- Páginas do site (SiteController) mandam título, descrição e endereço em $meta. --}}
        <meta name="description" content="{{ $meta['description'] ?? config('brand.name').' · '.config('brand.tagline') }}">
        @isset($meta)
            <meta property="og:type" content="website">
            <meta property="og:title" content="{{ $meta['title'] }}">
            <meta property="og:description" content="{{ $meta['description'] }}">
            <meta property="og:url" content="{{ $meta['url'] }}">
            <link rel="canonical" href="{{ $meta['url'] }}">
        @endisset

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $meta['title'] ?? config('brand.name') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        @include('partials.splash')
        <x-inertia::app />
    </body>
</html>
