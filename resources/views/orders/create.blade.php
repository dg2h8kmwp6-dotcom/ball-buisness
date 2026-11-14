<x-layout title="Build your mix">
    <div
        data-order-builder
        data-currency="{{ config('ballpit.currency') }}"
        data-shipping-cents="{{ (int) config('ballpit.shipping_flat_cents') }}"
        class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6"
    >
        <div class="max-w-2xl">
            <p class="eyebrow">Step one</p>
            <h1 class="mt-3 font-display text-4xl font-extrabold tracking-tighter sm:text-5xl">Build your mix</h1>
            <p class="mt-4 text-lg text-pit-700">
                Set a quantity for each colour you want. The bag fills as you go, so you can see the
                blend before you order.
            </p>
        </div>

        @error('quantities')
            <div role="alert" class="card-flat mt-6 border-berry bg-berry/5 px-4 py-3 text-sm font-medium">
                {{ $message }}
            </div>
        @enderror

        <form method="POST" action="{{ route('order.store') }}" class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
            @csrf

            {{-- Ball catalogue --}}
            <div>
                <div class="flex items-center justify-between gap-4">
                    <h2 class="eyebrow">Choose your balls</h2>
                    <button
                        type="button"
                        data-clear-all
                        class="font-display text-sm font-bold tracking-tight text-pit-700 underline decoration-2 underline-offset-4 hover:text-berry"
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
                            class="card-soft p-4 transition-[border-color,box-shadow,transform] data-[selected=true]:border-pit-950 data-[selected=true]:shadow-pop"
                        >
                            <div class="flex flex-wrap items-start gap-4">
                                <x-ball-swatch :ball-type="$ballType" class="mt-0.5 size-10" />

                                <div class="min-w-48 flex-1">
                                    <h3 class="font-display text-lg font-extrabold tracking-tight">{{ $ballType->name }}</h3>
                                    <p class="mt-0.5 text-sm font-medium text-pit-600">
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
                                        class="inline-flex size-10 items-center justify-center rounded-full border-2 border-pit-950 bg-white transition hover:-translate-y-0.5 hover:bg-sun hover:shadow-pop-sm focus:outline-none focus-visible:ring-4 focus-visible:ring-sun motion-reduce:transform-none"
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
                                        class="w-24 rounded-xl border-2 border-pit-950 bg-white px-3 py-2 text-center font-display text-lg font-extrabold tabular-nums focus:outline-none focus:ring-4 focus:ring-sun [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                    >

                                    <button
                                        type="button"
                                        data-step-up
                                        aria-label="Increase {{ $ballType->name }} quantity by {{ $ballType->quantity_step }}"
                                        class="inline-flex size-10 items-center justify-center rounded-full border-2 border-pit-950 bg-white transition hover:-translate-y-0.5 hover:bg-sun hover:shadow-pop-sm focus:outline-none focus-visible:ring-4 focus-visible:ring-sun motion-reduce:transform-none"
                                    >
                                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M10 4a.75.75 0 0 1 .75.75v4.5h4.5a.75.75 0 0 1 0 1.5h-4.5v4.5a.75.75 0 0 1-1.5 0v-4.5h-4.5a.75.75 0 0 1 0-1.5h4.5v-4.5A.75.75 0 0 1 10 4Z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-2 border-t-2 border-dashed border-pit-200 pt-3">
                                <span class="eyebrow">Quick add</span>
                                @foreach (config('ballpit.quick_add_quantities') as $amount)
                                    <button
                                        type="button"
                                        data-quick-add="{{ $amount }}"
                                        class="chip"
                                    >
                                        +{{ number_format($amount) }}
                                    </button>
                                @endforeach
                                <button
                                    type="button"
                                    data-clear
                                    class="ml-auto font-display text-xs font-bold text-pit-500 underline decoration-2 underline-offset-4 hover:text-berry"
                                >
                                    Remove
                                </button>
                            </div>

                            @error("quantities.{$ballType->id}")
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </li>
                    @empty
                        <li class="card-flat border-dashed p-8 text-center">
                            <p class="font-display text-lg font-extrabold tracking-tight">No ball colours are available yet.</p>
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
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between border-b-2 border-pit-950 bg-sun px-5 py-3">
                        <h2 class="font-display text-sm font-extrabold uppercase tracking-wider">Bag preview</h2>
                        <span data-total-balls class="font-display text-sm font-extrabold tabular-nums">0</span>
                    </div>

                    <div class="bg-pit-100 px-5 pt-4">
                        <div class="relative">
                            <canvas
                                data-ball-bag
                                class="aspect-square w-full"
                                role="img"
                                aria-label="An animated bag filling with the plastic balls you have selected"
                            ></canvas>

                            <p
                                data-bag-empty
                                class="pointer-events-none absolute inset-x-6 bottom-1/4 text-center text-sm font-medium text-pit-500"
                            >
                                Nothing in the bag yet. Add some balls and watch them drop in.
                            </p>
                        </div>

                        <p data-scale-note class="min-h-5 pb-3 text-center text-xs font-medium text-pit-600"></p>
                    </div>

                    <div class="border-t-2 border-pit-950 p-5">
                        <ul data-legend class="space-y-2 empty:hidden"></ul>

                        <dl class="mt-4 space-y-2 border-t-2 border-dashed border-pit-200 pt-4 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="font-medium text-pit-600">Subtotal</dt>
                                <dd data-subtotal class="font-display font-bold tabular-nums">—</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="font-medium text-pit-600">Shipping</dt>
                                <dd data-shipping class="font-display font-bold tabular-nums">—</dd>
                            </div>
                            <div class="flex items-center justify-between border-t-2 border-pit-950 pt-2">
                                <dt class="font-display text-base font-extrabold tracking-tight">Total</dt>
                                <dd data-total class="font-display text-xl font-extrabold tabular-nums">—</dd>
                            </div>
                        </dl>

                        <button type="submit" data-submit disabled class="btn-primary mt-5 w-full">
                            Continue to your bag
                        </button>

                        <p class="mt-3 text-center text-xs font-medium text-pit-500">
                            Next you will add shipping details and pay. Nothing is ordered yet.
                        </p>
                    </div>
                </div>
            </aside>
        </form>
    </div>
</x-layout>
