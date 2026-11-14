@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title.' — '.config('ballpit.brand_name') : config('ballpit.brand_name') }}</title>
    <meta name="description" content="{{ config('ballpit.tagline') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-pit-50 font-sans text-pit-950 antialiased">
    <header class="border-b border-pit-200/70 bg-white/80 backdrop-blur-sm">
        <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500 focus-visible:ring-offset-2">
                <x-logo class="size-9 shrink-0" />
                <span class="text-lg font-bold tracking-tight">{{ config('ballpit.brand_name') }}</span>
            </a>

            <nav class="flex items-center gap-2 text-sm font-medium">
                <a href="{{ route('order.create') }}" class="rounded-lg px-3 py-2 text-pit-700 transition hover:bg-pit-100 hover:text-pit-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500">
                    Build a mix
                </a>
                <a href="{{ route('checkout.show') }}" class="rounded-lg px-3 py-2 text-pit-700 transition hover:bg-pit-100 hover:text-pit-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500">
                    Bag
                </a>
            </nav>
        </div>
    </header>

    @if (session('status') || session('error'))
        <div class="mx-auto w-full max-w-6xl px-4 pt-6 sm:px-6">
            @if (session('status'))
                <div role="status" class="rounded-xl border border-pit-200 bg-white px-4 py-3 text-sm text-pit-800 shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm">
                    {{ session('error') }}
                </div>
            @endif
        </div>
    @endif

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-pit-200/70 bg-white">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-2 px-4 py-8 text-sm text-pit-600 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <p>&copy; {{ now()->year }} {{ config('ballpit.brand_name') }}. Placeholder branding.</p>
            <a href="mailto:{{ config('ballpit.support_email') }}" class="font-medium text-pit-700 underline-offset-4 hover:underline">
                {{ config('ballpit.support_email') }}
            </a>
        </div>
    </footer>
</body>
</html>
