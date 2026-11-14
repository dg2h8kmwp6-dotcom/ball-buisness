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
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|bricolage-grotesque:600,700,800" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-pit-50 font-sans text-pit-950 antialiased">
    <header class="sticky top-0 z-30 border-b-2 border-pit-950 bg-pit-50/95 backdrop-blur">
        <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="group flex items-center gap-2.5 rounded-full focus:outline-none focus-visible:ring-4 focus-visible:ring-sun">
                <x-logo class="size-10 shrink-0 transition-transform group-hover:-rotate-6 motion-reduce:transform-none" />
                <span class="font-display text-lg font-extrabold tracking-tight">{{ config('ballpit.brand_name') }}</span>
            </a>

            <nav class="flex items-center gap-2">
                <a href="{{ route('order.create') }}" class="rounded-full px-4 py-2 font-display text-sm font-bold tracking-tight text-pit-800 transition hover:bg-sun focus:outline-none focus-visible:ring-4 focus-visible:ring-sun">
                    Build a mix
                </a>
                <a href="{{ route('checkout.show') }}" class="inline-flex items-center gap-1.5 rounded-full border-2 border-pit-950 bg-white px-4 py-2 font-display text-sm font-bold tracking-tight transition hover:-translate-y-0.5 hover:shadow-pop-sm focus:outline-none focus-visible:ring-4 focus-visible:ring-sun motion-reduce:transform-none">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                        <path d="M6 7h12l-1.2 12.2a2 2 0 0 1-2 1.8H9.2a2 2 0 0 1-2-1.8L6 7Z" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M9.5 7V5.5a2.5 2.5 0 0 1 5 0V7" stroke-linecap="round" />
                    </svg>
                    Bag
                </a>
            </nav>
        </div>
    </header>

    @if (session('status') || session('error'))
        <div class="mx-auto w-full max-w-6xl px-4 pt-6 sm:px-6">
            @if (session('status'))
                <div role="status" class="card-flat flex items-start gap-3 px-4 py-3 text-sm font-medium">
                    <span class="mt-0.5 size-4 shrink-0 rounded-full bg-grass"></span>
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div role="alert" class="card-flat flex items-start gap-3 border-berry bg-berry/5 px-4 py-3 text-sm font-medium text-pit-900">
                    <span class="mt-0.5 size-4 shrink-0 rounded-full bg-berry"></span>
                    {{ session('error') }}
                </div>
            @endif
        </div>
    @endif

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="mt-20 border-t-2 border-pit-950 bg-pit-950 text-pit-100">
        <div class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6">
            <div class="flex flex-wrap items-center gap-2">
                {{-- Literal classes so Tailwind's scanner keeps them. --}}
                @foreach (['bg-berry', 'bg-tangerine', 'bg-sun', 'bg-grass', 'bg-mint', 'bg-sky', 'bg-grape', 'bg-bubble'] as $accent)
                    <span class="size-5 rounded-full {{ $accent }}"></span>
                @endforeach
            </div>

            <div class="mt-6 flex flex-col gap-2 text-sm sm:flex-row sm:items-center sm:justify-between">
                <p class="font-display font-bold tracking-tight">
                    {{ config('ballpit.brand_name') }}
                    <span class="font-sans font-normal text-pit-400">· placeholder branding</span>
                </p>
                <a href="mailto:{{ config('ballpit.support_email') }}" class="font-medium text-pit-200 underline-offset-4 hover:text-sun hover:underline">
                    {{ config('ballpit.support_email') }}
                </a>
            </div>
        </div>
    </footer>
</body>
</html>
