@php
    // The catalogue colours, used as the page's accent system.
    $swatches = ['#e8392e', '#f5833b', '#f6c62b', '#3faa58', '#3fc0b0', '#2b7fd4', '#8257c9', '#f279ae'];
@endphp

<x-layout>
    {{-- Hero ------------------------------------------------------------ --}}
    <section class="relative overflow-hidden border-b-2 border-pit-950">
        <div class="mx-auto grid w-full max-w-6xl items-center gap-14 px-4 py-16 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:py-24">
            <div>
                <span class="tape bg-sun">Placeholder brand</span>

                <h1 class="mt-6 font-display text-5xl font-extrabold leading-[0.95] tracking-tighter text-balance sm:text-6xl lg:text-7xl">
                    Pick your palette,<br>
                    <span class="relative inline-block">
                        <span class="relative z-10">we'll fill the pit.</span>
                        {{-- Highlighter sweep behind the second line. --}}
                        <span class="absolute inset-x-0 bottom-1 z-0 h-4 -rotate-1 bg-mint sm:h-5 lg:h-6" aria-hidden="true"></span>
                    </span>
                </h1>

                <p class="mt-7 max-w-lg text-lg leading-relaxed text-pit-700">
                    {{ config('ballpit.tagline') }} Choose your colours, set your quantities, and watch the
                    mix drop into the bag before you pay a penny.
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-4">
                    <a href="{{ route('order.create') }}" class="btn-primary group text-lg">
                        Order now
                        <svg class="size-5 transition-transform group-hover:translate-x-1 motion-reduce:transform-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                        </svg>
                    </a>

                    <a href="#how-it-works" class="font-display text-sm font-bold tracking-tight text-pit-800 underline decoration-2 underline-offset-4 hover:decoration-berry">
                        How custom orders work
                    </a>
                </div>

                @if ($colourCount > 0)
                    <dl class="mt-12 grid max-w-md grid-cols-3 gap-4">
                        @foreach ([
                            ['Colours', number_format($colourCount), 'bg-berry'],
                            ['Min order', number_format($minimumQuantity), 'bg-sky'],
                            ['Ball size', $ballDiameter.'mm', 'bg-grape'],
                        ] as [$term, $value, $accent])
                            <div class="card-flat px-4 py-3">
                                <dt class="flex items-center gap-1.5 text-[0.65rem] font-bold uppercase tracking-wider text-pit-600">
                                    <span class="size-2 rounded-full {{ $accent }}"></span>{{ $term }}
                                </dt>
                                <dd class="mt-1 font-display text-2xl font-extrabold tracking-tight">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            {{-- A heap of balls, built from the real catalogue colours. --}}
            <div class="relative mx-auto aspect-square w-full max-w-md">
                <div class="absolute inset-4 rounded-full border-2 border-pit-950 bg-sun/30"></div>

                <svg class="relative size-full" viewBox="0 0 400 400" role="img" aria-label="A heap of coloured plastic pit balls">
                    <defs>
                        @foreach ($swatches as $i => $hex)
                            <radialGradient id="hero-ball-{{ $i }}" cx="34%" cy="28%" r="78%">
                                <stop offset="0%" stop-color="#ffffff" stop-opacity="0.85" />
                                <stop offset="32%" stop-color="{{ $hex }}" />
                                <stop offset="100%" stop-color="{{ $hex }}" />
                            </radialGradient>
                        @endforeach
                    </defs>

                    @php
                        $heap = [
                            [196, 300, 64, 0], [112, 322, 50, 2], [288, 318, 54, 5],
                            [160, 212, 52, 3], [252, 220, 48, 7], [72, 236, 42, 1],
                            [334, 240, 40, 6], [204, 128, 44, 4], [124, 136, 36, 5],
                            [280, 134, 32, 0], [172, 58, 30, 2], [240, 66, 26, 3],
                        ];
                    @endphp

                    @foreach ($heap as [$cx, $cy, $r, $fill])
                        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"
                            fill="url(#hero-ball-{{ $fill }})"
                            stroke="#15100b" stroke-width="3" />
                    @endforeach
                </svg>
            </div>
        </div>
    </section>

    {{-- Colour strip ------------------------------------------------------ --}}
    <div class="flex h-5 w-full border-b-2 border-pit-950" aria-hidden="true">
        @foreach (['bg-berry', 'bg-tangerine', 'bg-sun', 'bg-grass', 'bg-mint', 'bg-sky', 'bg-grape', 'bg-bubble'] as $accent)
            <span class="flex-1 {{ $accent }}"></span>
        @endforeach
    </div>

    {{-- How it works ------------------------------------------------------ --}}
    <section id="how-it-works" class="mx-auto w-full max-w-6xl px-4 py-20 sm:px-6">
        <p class="eyebrow">Three steps</p>
        <h2 class="mt-3 max-w-2xl font-display text-4xl font-extrabold tracking-tighter text-balance sm:text-5xl">
            How a custom order works
        </h2>

        <ol class="mt-12 grid gap-6 sm:grid-cols-3">
            @foreach ([
                ['Build your palette', 'Set a quantity for each ball type. Mix as many colours as you like.', 'bg-berry', 'text-white'],
                ['Watch the bag fill', 'Your selection drops into the bag so you can see the mix before you buy.', 'bg-sun', 'text-pit-950'],
                ['Check out securely', 'Pay by card or wallet. We mix, bag, and ship your order.', 'bg-sky', 'text-white'],
            ] as $index => [$heading, $copy, $accent, $ink])
                <li class="card flex flex-col p-6">
                    <span class="inline-flex size-11 items-center justify-center rounded-full border-2 border-pit-950 {{ $accent }} {{ $ink }} font-display text-lg font-extrabold">
                        {{ $index + 1 }}
                    </span>
                    <h3 class="mt-5 font-display text-xl font-extrabold tracking-tight">{{ $heading }}</h3>
                    <p class="mt-2 leading-relaxed text-pit-700">{{ $copy }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Closing call to action --------------------------------------------- --}}
    <section class="mx-auto w-full max-w-6xl px-4 pb-20 sm:px-6">
        <div class="card relative overflow-hidden bg-pit-950 px-8 py-14 text-center sm:px-14">
            <div class="pointer-events-none absolute -right-10 -top-10 size-48 rounded-full bg-berry/30" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-14 -left-10 size-56 rounded-full bg-sky/25" aria-hidden="true"></div>

            <div class="relative">
                <h2 class="font-display text-4xl font-extrabold tracking-tighter text-white text-balance sm:text-5xl">
                    Ready to mix?
                </h2>
                <p class="mx-auto mt-4 max-w-md text-pit-200">
                    Build a bag in about a minute. Nothing is ordered until you say so.
                </p>
                <a href="{{ route('order.create') }}" class="btn-sun mt-8 text-lg">
                    Order now
                </a>
            </div>
        </div>
    </section>
</x-layout>
