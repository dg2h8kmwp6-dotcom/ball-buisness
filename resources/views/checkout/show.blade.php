<x-layout title="Checkout">
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6">
        <a href="{{ route('order.create') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-pit-600 underline-offset-4 hover:text-pit-900 hover:underline">
            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd" />
            </svg>
            Back to your mix
        </a>

        <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">Checkout</h1>

        @unless ($stripeConfigured)
            <div role="alert" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Payments are not connected yet.</p>
                <p class="mt-1">
                    Add <code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-xs">STRIPE_KEY</code> and
                    <code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-xs">STRIPE_SECRET</code> to your
                    <code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-xs">.env</code> file to take real payments.
                    See the <strong>Payments</strong> section of <code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-xs">README.md</code>.
                </p>
            </div>
        @endunless

        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
            {{-- Contact details --}}
            <form method="POST" action="{{ route('checkout.store') }}" class="space-y-6">
                @csrf

                <div class="rounded-2xl border border-pit-200 bg-white p-6 shadow-sm">
                    <h2 class="font-semibold">Where should we send it?</h2>
                    <p class="mt-1 text-sm text-pit-600">
                        We collect the shipping address on the secure payment page.
                    </p>

                    <div class="mt-5 space-y-4">
                        <div>
                            <label for="customer_name" class="block text-sm font-medium text-pit-800">
                                Full name
                            </label>
                            <input
                                id="customer_name"
                                name="customer_name"
                                type="text"
                                autocomplete="name"
                                required
                                value="{{ old('customer_name') }}"
                                @class([
                                    'mt-1.5 block w-full rounded-lg border bg-white px-3 py-2.5 shadow-sm focus:outline-none focus:ring-2',
                                    'border-red-400 focus:border-red-500 focus:ring-red-500/30' => $errors->has('customer_name'),
                                    'border-pit-300 focus:border-pit-500 focus:ring-pit-500/30' => ! $errors->has('customer_name'),
                                ])
                            >
                            @error('customer_name')
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="customer_email" class="block text-sm font-medium text-pit-800">
                                Email address
                            </label>
                            <input
                                id="customer_email"
                                name="customer_email"
                                type="email"
                                autocomplete="email"
                                required
                                value="{{ old('customer_email') }}"
                                @class([
                                    'mt-1.5 block w-full rounded-lg border bg-white px-3 py-2.5 shadow-sm focus:outline-none focus:ring-2',
                                    'border-red-400 focus:border-red-500 focus:ring-red-500/30' => $errors->has('customer_email'),
                                    'border-pit-300 focus:border-pit-500 focus:ring-pit-500/30' => ! $errors->has('customer_email'),
                                ])
                            >
                            @error('customer_email')
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                            <p class="mt-1.5 text-sm text-pit-600">Your receipt and tracking go here.</p>
                        </div>
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-pit-600 px-6 py-4 text-base font-semibold text-white shadow-lg shadow-pit-600/25 transition hover:bg-pit-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500 focus-visible:ring-offset-2"
                >
                    Continue to payment
                </button>

                <p class="text-center text-xs text-pit-500">
                    You will be redirected to Stripe to pay securely. We never see your card details.
                </p>
            </form>

            {{-- Order summary --}}
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-2xl border border-pit-200 bg-white p-6 shadow-sm">
                    <h2 class="font-semibold">Order summary</h2>

                    <ul class="mt-4 divide-y divide-pit-100">
                        @foreach ($lines as $line)
                            <li class="flex items-start gap-3 py-3">
                                <span
                                    class="mt-0.5 size-7 shrink-0 rounded-full ring-1 ring-inset ring-black/10"
                                    style="background: radial-gradient(circle at 34% 30%, rgba(255,255,255,.72) 0%, {{ $line['ballType']->color_hex }} 38%, rgba(0,0,0,.14) 100%);"
                                    aria-hidden="true"
                                ></span>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ $line['ballType']->name }}</p>
                                    <p class="text-xs text-pit-600">
                                        {{ number_format($line['quantity']) }} × {{ $line['ballType']->formattedUnitPrice() }}
                                    </p>
                                </div>

                                <p class="text-sm font-semibold tabular-nums">
                                    ${{ number_format($line['line_total_cents'] / 100, 2) }}
                                </p>
                            </li>
                        @endforeach
                    </ul>

                    <dl class="mt-2 space-y-2 border-t border-pit-200 pt-4 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-pit-600">Total balls</dt>
                            <dd class="font-semibold tabular-nums">{{ number_format($totalBalls) }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-pit-600">Subtotal</dt>
                            <dd class="font-semibold tabular-nums">${{ number_format($subtotalCents / 100, 2) }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-pit-600">Shipping</dt>
                            <dd class="font-semibold tabular-nums">${{ number_format($shippingCents / 100, 2) }}</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-pit-200 pt-2 text-base">
                            <dt class="font-semibold">Total</dt>
                            <dd class="font-bold tabular-nums">${{ number_format($totalCents / 100, 2) }}</dd>
                        </div>
                    </dl>
                </div>
            </aside>
        </div>
    </div>
</x-layout>
