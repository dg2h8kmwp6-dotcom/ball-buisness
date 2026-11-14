<x-layout title="Build your mix">
    <div
        data-order-builder
        data-currency="{{ config('ballpit.currency') }}"
        data-shipping-cents="{{ (int) config('ballpit.shipping_flat_cents') }}"
        class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6"
    >
        <div class="max-w-2xl">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Build your mix</h1>
            <p class="mt-3 text-pit-700">
                Set a quantity for each colour you want. The bag fills as you go, so you can see the
                blend before you order.
            </p>
        </div>

        @error('quantities')
            <div role="alert" class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $message }}
            </div>
        @enderror

        <form method="POST" action="{{ route('order.store') }}" class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
            @csrf

            {{-- Ball catalogue --}}
            <div>
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-pit-600">
                        Choose your balls
                    </h2>
                    <button
                        type="button"
                        data-clear-all
                        class="text-sm font-medium text-pit-600 underline-offset-4 hover:text-pit-900 hover:underline"
                    >
                        Clear all
                    </button>
                </div>

                <ul class="mt-4 space-y-3">
                    @forelse ($ballTypes as $ballType)
                        <li
                            data-ball-row
                            data-ball-id="{{ $ballType->id }}"
                            data-ball-name="{{ $ballType->name }}"
                            data-color="{{ $ballType->color_hex }}"
                            data-image="{{ $ballType->imageUrl() }}"
                            data-price-cents="{{ $ballType->price_per_ball_cents }}"
                            data-step="{{ $ballType->quantity_step }}"
                            data-selected="false"
                            class="group rounded-2xl border border-pit-200 bg-white p-4 shadow-sm transition data-[selected=true]:border-pit-400 data-[selected=true]:ring-2 data-[selected=true]:ring-pit-500/25"
                        >
                            <div class="flex flex-wrap items-start gap-4">
                                <x-ball-swatch :ball-type="$ballType" class="mt-0.5 size-10" />

                                <div class="min-w-48 flex-1">
                                    <h3 class="font-semibold">{{ $ballType->name }}</h3>
                                    <p class="mt-0.5 text-sm text-pit-600">
                                        {{ $ballType->formattedUnitPrice() }} per ball · {{ $ballType->diameter_mm }}mm
                                    </p>
                                    @if ($ballType->description)
                                        <p class="mt-1 text-sm leading-relaxed text-pit-700">
                                            {{ $ballType->description }}
                                        </p>
                                    @endif
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        data-step-down
                                        aria-label="Decrease {{ $ballType->name }} quantity by {{ $ballType->quantity_step }}"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-pit-200 text-pit-700 transition hover:bg-pit-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500"
                                    >
                                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M4 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 10Z" />
                                        </svg>
                                    </button>

                                    <input
                                        type="number"
                                        inputmode="numeric"
                                        data-qty-input
                                        name="quantities[{{ $ballType->id }}]"
                                        value="{{ $quantities[$ballType->id] ?? '' }}"
                                        min="0"
                                        max="{{ (int) config('ballpit.max_quantity_per_type') }}"
                                        step="1"
                                        placeholder="0"
                                        aria-label="Quantity of {{ $ballType->name }}"
                                        class="w-24 rounded-lg border border-pit-300 bg-white px-3 py-2 text-center font-medium tabular-nums shadow-sm focus:border-pit-500 focus:outline-none focus:ring-2 focus:ring-pit-500/30 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                    >

                                    <button
                                        type="button"
                                        data-step-up
                                        aria-label="Increase {{ $ballType->name }} quantity by {{ $ballType->quantity_step }}"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-pit-200 text-pit-700 transition hover:bg-pit-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500"
                                    >
                                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M10 4a.75.75 0 0 1 .75.75v4.5h4.5a.75.75 0 0 1 0 1.5h-4.5v4.5a.75.75 0 0 1-1.5 0v-4.5h-4.5a.75.75 0 0 1 0-1.5h4.5v-4.5A.75.75 0 0 1 10 4Z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-pit-100 pt-3">
                                <span class="text-xs font-medium uppercase tracking-wide text-pit-500">Quick add</span>
                                @foreach (config('ballpit.quick_add_quantities') as $amount)
                                    <button
                                        type="button"
                                        data-quick-add="{{ $amount }}"
                                        class="rounded-full border border-pit-200 px-3 py-1 text-xs font-semibold text-pit-700 transition hover:border-pit-400 hover:bg-pit-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500"
                                    >
                                        +{{ number_format($amount) }}
                                    </button>
                                @endforeach
                                <button
                                    type="button"
                                    data-clear
                                    class="ml-auto text-xs font-medium text-pit-500 underline-offset-4 hover:text-pit-800 hover:underline"
                                >
                                    Remove
                                </button>
                            </div>

                            @error("quantities.{$ballType->id}")
                                <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </li>
                    @empty
                        <li class="rounded-2xl border border-dashed border-pit-300 bg-white p-8 text-center">
                            <p class="font-semibold">No ball colours are available yet.</p>
                            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-pit-700">
                                The catalogue is empty, so there is nothing to order. If you run this shop,
                                load the default colours with
                                <code class="rounded bg-pit-100 px-1 py-0.5 font-mono text-xs">php artisan ballpit:sync-ball-types</code>
                                or add them in the admin area.
                            </p>
                            <a href="mailto:{{ config('ballpit.support_email') }}" class="mt-4 inline-block text-sm font-medium text-pit-700 underline-offset-4 hover:underline">
                                Contact us instead
                            </a>
                        </li>
                    @endforelse
                </ul>
            </div>

            {{-- The bag --}}
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-2xl border border-pit-200 bg-gradient-to-b from-white to-pit-100 p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-pit-600">Your bag</h2>

                    <div class="relative mt-3">
                        <canvas
                            data-ball-bag
                            class="aspect-square w-full"
                            role="img"
                            aria-label="An animated bag filling with the plastic balls you have selected"
                        ></canvas>

                        <p
                            data-bag-empty
                            class="pointer-events-none absolute inset-x-6 bottom-1/4 text-center text-sm text-pit-500"
                        >
                            Your bag is empty. Add some balls and watch them drop in.
                        </p>
                    </div>

                    <p data-scale-note class="min-h-5 text-center text-xs text-pit-500"></p>

                    <ul data-legend class="mt-4 space-y-1.5 empty:hidden"></ul>

                    <dl class="mt-5 space-y-2 border-t border-pit-200 pt-4 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-pit-600">Total balls</dt>
                            <dd data-total-balls class="font-semibold tabular-nums">0</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-pit-600">Subtotal</dt>
                            <dd data-subtotal class="font-semibold tabular-nums">—</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-pit-600">Shipping</dt>
                            <dd data-shipping class="font-semibold tabular-nums">—</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-pit-200 pt-2 text-base">
                            <dt class="font-semibold">Total</dt>
                            <dd data-total class="font-bold tabular-nums">—</dd>
                        </div>
                    </dl>

                    <button
                        type="submit"
                        data-submit
                        disabled
                        class="mt-5 w-full rounded-xl bg-pit-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-pit-600/25 transition hover:bg-pit-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-pit-300 disabled:shadow-none"
                    >
                        Place order
                    </button>

                    <p class="mt-3 text-center text-xs text-pit-500">
                        You will review everything before paying.
                    </p>
                </div>
            </aside>
        </form>
    </div>
</x-layout>
