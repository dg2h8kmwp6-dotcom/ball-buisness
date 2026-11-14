@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — Admin' : 'Admin' }} · {{ config('ballpit.brand_name') }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|bricolage-grotesque:600,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-pit-50 font-sans text-pit-950 antialiased">
    <header class="border-b-2 border-pit-950 bg-white">
        <div class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <div class="flex items-center gap-3">
                <x-logo class="size-8 shrink-0" />
                <div>
                    <p class="font-display text-sm font-extrabold leading-tight tracking-tight">{{ config('ballpit.brand_name') }}</p>
                    <p class="text-xs font-medium text-pit-600">Catalogue admin</p>
                </div>
            </div>

            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('home') }}" class="font-display text-sm font-bold tracking-tight text-pit-700 underline decoration-2 underline-offset-4 hover:text-berry">
                    View shop
                </a>
                @auth
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="btn-quiet">
                            Sign out
                        </button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    @if (session('status') || session('error'))
        <div class="mx-auto w-full max-w-5xl px-4 pt-5 sm:px-6">
            @if (session('status'))
                <div role="status" class="card-flat border-grass bg-grass/10 px-4 py-3 text-sm font-medium">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div role="alert" class="card-flat border-berry bg-berry/5 px-4 py-3 text-sm font-medium">
                    {{ session('error') }}
                </div>
            @endif
        </div>
    @endif

    <main class="flex-1 py-8">
        {{ $slot }}
    </main>
</body>
</html>
