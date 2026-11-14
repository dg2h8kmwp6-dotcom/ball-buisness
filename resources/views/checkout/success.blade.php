<x-layout title="Order confirmed">
    @php
        $isPaid = $order->status === \App\Enums\OrderStatus::Paid;
    @endphp

    <div class="mx-auto w-full max-w-2xl px-4 py-16 sm:px-6">
        <div class="rounded-2xl border border-pit-200 bg-white p-8 text-center shadow-sm">
            @if ($isPaid)
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-green-100">
                    <svg class="size-8 text-green-700" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                </div>

                <h1 class="mt-5 text-3xl font-bold tracking-tight">Thanks — your balls are on the way</h1>
                <p class="mt-3 text-pit-700">
                    We have your payment and your custom mix is queued for bagging. A receipt is on its way to
                    <span class="font-medium text-pit-900">{{ $order->customer_email }}</span>.
                </p>
            @else
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-amber-100">
                    <svg class="size-8 text-amber-700" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-11.25a.75.75 0 0 0-1.5 0v4.5a.75.75 0 0 0 1.5 0v-4.5Zm-.75 8.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                    </svg>
                </div>

                <h1 class="mt-5 text-3xl font-bold tracking-tight">We're confirming your payment</h1>
                <p class="mt-3 text-pit-700">
                    Your order is recorded and we are waiting on final confirmation from our payment provider.
                    We will email <span class="font-medium text-pit-900">{{ $order->customer_email }}</span> as
                    soon as it clears.
                </p>
            @endif

            <dl class="mx-auto mt-8 grid max-w-sm grid-cols-2 gap-4 border-t border-pit-200 pt-6 text-left">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-pit-600">Order</dt>
                    <dd class="mt-1 font-mono text-sm font-semibold">{{ $order->reference }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-pit-600">Status</dt>
                    <dd class="mt-1 text-sm font-semibold">{{ $order->status->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-pit-600">Total balls</dt>
                    <dd class="mt-1 text-sm font-semibold tabular-nums">{{ number_format($order->totalBalls()) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-pit-600">Paid</dt>
                    <dd class="mt-1 text-sm font-semibold tabular-nums">{{ $order->formattedTotal() }}</dd>
                </div>
            </dl>

            @if ($order->hasShippingAddress())
                <div class="mx-auto mt-6 max-w-sm border-t border-pit-200 pt-6 text-left">
                    <h2 class="text-xs font-medium uppercase tracking-wide text-pit-600">Shipping to</h2>
                    <address class="mt-2 text-sm not-italic leading-relaxed text-pit-800">
                        @foreach ($order->shippingAddressLines() as $line)
                            {{ $line }}<br>
                        @endforeach
                    </address>

                    @if ($order->delivery_notes)
                        <p class="mt-2 text-sm text-pit-600">
                            <span class="font-medium">Notes:</span> {{ $order->delivery_notes }}
                        </p>
                    @endif
                </div>
            @endif

            <ul class="mx-auto mt-6 max-w-sm space-y-2 border-t border-pit-200 pt-6 text-left">
                @foreach ($order->items as $item)
                    <li class="flex items-center gap-3 text-sm">
                        <span
                            class="size-5 shrink-0 rounded-full ring-1 ring-inset ring-black/10"
                            style="background: radial-gradient(circle at 34% 30%, rgba(255,255,255,.72) 0%, {{ $item->color_hex }} 38%, rgba(0,0,0,.14) 100%);"
                            aria-hidden="true"
                        ></span>
                        <span class="flex-1 truncate">{{ $item->ball_type_name }}</span>
                        <span class="font-medium tabular-nums">{{ number_format($item->quantity) }}</span>
                    </li>
                @endforeach
            </ul>

            <a
                href="{{ route('home') }}"
                class="mt-8 inline-flex items-center gap-2 rounded-xl bg-pit-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-pit-600/25 transition hover:bg-pit-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500 focus-visible:ring-offset-2"
            >
                Back to the shop
            </a>
        </div>
    </div>
</x-layout>
