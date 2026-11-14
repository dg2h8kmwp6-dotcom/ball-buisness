<x-layout>
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-b from-white via-pit-50 to-pit-100"></div>

        <div class="mx-auto grid w-full max-w-6xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:py-28">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-pit-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-pit-700">
                    <span class="size-2 rounded-full bg-pit-500"></span>
                    Placeholder brand
                </div>

                <h1 class="mt-6 text-4xl font-bold leading-tight tracking-tight text-balance sm:text-5xl lg:text-6xl">
                    Pick your palette,
                    <span class="text-pit-600">we'll fill the pit.</span>
                </h1>

                <p class="mt-6 max-w-xl text-lg leading-relaxed text-pit-700">
                    {{ config('ballpit.tagline') }} Choose your colours, set your quantities, and watch your
                    mix drop into the bag before you order.
                </p>

                <div class="mt-10 flex flex-wrap items-center gap-4">
                    <a
                        href="{{ route('order.create') }}"
                        class="group inline-flex items-center gap-2 rounded-xl bg-pit-600 px-7 py-4 text-base font-semibold text-white shadow-lg shadow-pit-600/25 transition hover:-translate-y-0.5 hover:bg-pit-700 hover:shadow-xl hover:shadow-pit-600/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500 focus-visible:ring-offset-2 motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                    >
                        Order now
                        <svg class="size-5 transition-transform group-hover:translate-x-1 motion-reduce:transition-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                        </svg>
                    </a>

                    <a href="#how-it-works" class="text-sm font-semibold text-pit-700 underline-offset-4 hover:underline">
                        How custom orders work
                    </a>
                </div>

                @if ($colourCount > 0)
                    <dl class="mt-12 grid max-w-md grid-cols-3 gap-6 border-t border-pit-200 pt-8">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-pit-600">Colours</dt>
                            <dd class="mt-1 text-2xl font-bold">{{ $colourCount }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-pit-600">Min order</dt>
                            <dd class="mt-1 text-2xl font-bold">{{ number_format($minimumQuantity) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-pit-600">Ball size</dt>
                            <dd class="mt-1 text-2xl font-bold">{{ $ballDiameter }}<span class="text-base font-semibold">mm</span></dd>
                        </div>
                    </dl>
                @endif
            </div>

            <div class="relative">
                <div class="relative mx-auto aspect-square w-full max-w-md">
                    <div class="absolute inset-0 rounded-full bg-gradient-to-br from-pit-200 to-pit-100"></div>

                    {{-- Decorative pile of balls, mirroring the real catalogue colours. --}}
                    <svg class="relative size-full drop-shadow-xl" viewBox="0 0 400 400" role="img" aria-label="An illustration of a pile of coloured plastic pit balls">
                        <defs>
                            @foreach (['a' => '#e8392e', 'b' => '#2b7fd4', 'c' => '#f6c62b', 'd' => '#3faa58', 'e' => '#f279ae', 'f' => '#f5833b', 'g' => '#8257c9', 'h' => '#3fc0b0'] as $key => $hex)
                                <radialGradient id="ball-{{ $key }}" cx="35%" cy="30%" r="75%">
                                    <stop offset="0%" stop-color="#ffffff" stop-opacity="0.75" />
                                    <stop offset="35%" stop-color="{{ $hex }}" />
                                    <stop offset="100%" stop-color="{{ $hex }}" stop-opacity="0.85" />
                                </radialGradient>
                            @endforeach
                        </defs>

                        @php
                            $decorativeBalls = [
                                [200, 300, 62, 'a'], [120, 325, 48, 'c'], [285, 320, 52, 'b'],
                                [165, 215, 50, 'd'], [250, 225, 46, 'e'], [78, 240, 40, 'f'],
                                [330, 245, 38, 'g'], [205, 135, 42, 'h'], [128, 140, 34, 'b'],
                                [278, 140, 30, 'a'], [175, 65, 28, 'c'], [240, 70, 24, 'd'],
                            ];
                        @endphp

                        @foreach ($decorativeBalls as [$cx, $cy, $r, $fill])
                            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="url(#ball-{{ $fill }})" />
                        @endforeach
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-3xl font-bold tracking-tight">How a custom order works</h2>

        <ol class="mt-10 grid gap-6 sm:grid-cols-3">
            @foreach ([
                ['Build your palette', 'Set a quantity for each ball type. Mix as many colours as you like.'],
                ['Watch the bag fill', 'Your selection drops into the bag so you can see the mix before you buy.'],
                ['Check out securely', 'Pay by card or wallet. We mix, bag, and ship your order.'],
            ] as $index => [$heading, $copy])
                <li class="rounded-2xl border border-pit-200 bg-white p-6 shadow-sm">
                    <span class="inline-flex size-8 items-center justify-center rounded-full bg-pit-600 text-sm font-bold text-white">
                        {{ $index + 1 }}
                    </span>
                    <h3 class="mt-4 font-semibold">{{ $heading }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-pit-700">{{ $copy }}</p>
                </li>
            @endforeach
        </ol>

        <div class="mt-12">
            <a
                href="{{ route('order.create') }}"
                class="inline-flex items-center gap-2 rounded-xl bg-pit-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-pit-600/25 transition hover:bg-pit-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500 focus-visible:ring-offset-2"
            >
                Order now
            </a>
        </div>
    </section>
</x-layout>
