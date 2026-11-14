<x-layout title="Order confirmed">
    @php
        $isPaid = $order->status === \App\Enums\OrderStatus::Paid;
    @endphp

    <div class="mx-auto w-full max-w-2xl px-4 py-16 sm:px-6">
        <div class="card p-8 text-center">
            @if ($isPaid)
                <div class="mx-auto flex size-16 items-center justify-center rounded-full border-2 border-pit-950 bg-grass">
                    <svg class="size-8 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                </div>

                <h1 class="mt-6 font-display text-3xl font-extrabold tracking-tighter">Thanks — your balls are on the way</h1>
                <p class="mt-3 text-pit-700">
                    We have your payment and your custom mix is queued for bagging. A receipt is on its way to
                    <span class="font-medium text-pit-900">{{ $order->customer_email }}</span>.
                </p>
            @else
                <div class="mx-auto flex size-16 items-center justify-center rounded-full border-2 border-pit-950 bg-sun">
                    <svg class="size-8 text-pit-950" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-11.25a.75.75 0 0 0-1.5 0v4.5a.75.75 0 0 0 1.5 0v-4.5Zm-.75 8.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                    </svg>
                </div>

                <h1 class="mt-6 font-display text-3xl font-extrabold tracking-tighter">We're confirming your payment</h1>
                <p class="mt-3 text-pit-700">
                    Your order is recorded and we are waiting on final confirmation from our payment provider.
                    We will email <span class="font-medium text-pit-900">{{ $order->customer_email }}</span> as
                    soon as it clears.
                </p>
            @endif

            <dl class="mx-auto mt-8 grid max-w-sm grid-cols-2 gap-4 border-t-2 border-dashed border-pit-200 pt-6 text-left">
                <div>
                    <dt class="eyebrow">Order</dt>
                    <dd class="mt-1 font-mono text-sm font-bold">{{ $order->reference }}</dd>
                </div>
                <div>
                    <dt class="eyebrow">Status</dt>
                    <dd class="mt-1 font-display text-base font-extrabold">{{ $order->status->label() }}</dd>
                </div>
                <div>
                    <dt class="eyebrow">Total balls</dt>
                    <dd class="mt-1 font-display text-base font-extrabold tabular-nums">{{ number_format($order->totalBalls()) }}</dd>
                </div>
                <div>
                    <dt class="eyebrow">Paid</dt>
                    <dd class="mt-1 font-display text-base font-extrabold tabular-nums">{{ $order->formattedTotal() }}</dd>
                </div>
            </dl>

            @if ($order->hasShippingAddress())
                <div class="mx-auto mt-6 max-w-sm border-t-2 border-dashed border-pit-200 pt-6 text-left">
                    <h2 class="eyebrow">Shipping to</h2>
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

            <ul class="mx-auto mt-6 max-w-sm space-y-2 border-t-2 border-dashed border-pit-200 pt-6 text-left">
                @foreach ($order->items as $item)
                    <li class="flex items-center gap-3 text-sm">
                        <span
                            class="size-6 shrink-0 rounded-full border-2 border-pit-950"
                            style="background: radial-gradient(circle at 34% 28%, rgba(255,255,255,.8) 0%, {{ $item->color_hex }} 36%, {{ $item->color_hex }} 100%);"
                            aria-hidden="true"
                        ></span>
                        <span class="flex-1 truncate">{{ $item->ball_type_name }}</span>
                        <span class="font-medium tabular-nums">{{ number_format($item->quantity) }}</span>
                    </li>
                @endforeach
            </ul>

            <a
                href="{{ route('home') }}"
                class="btn-ink mt-8"
            >
                Back to the shop
            </a>
        </div>
    </div>
</x-layout>
