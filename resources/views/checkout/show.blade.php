<x-layout title="Checkout">
    <div class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6">
        <a href="{{ route('order.create') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-pit-600 underline-offset-4 hover:text-pit-900 hover:underline">
            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd" />
            </svg>
            Back to your mix
        </a>

        <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">Checkout</h1>

        @unless ($stripeEnabled || $paypalEnabled)
            <div role="alert" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Payments are not connected yet.</p>
                <p class="mt-1">
                    Add Stripe keys (<code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-xs">STRIPE_KEY</code>,
                    <code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-xs">STRIPE_SECRET</code>) or PayPal
                    credentials (<code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-xs">PAYPAL_CLIENT_ID</code>,
                    <code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-xs">PAYPAL_CLIENT_SECRET</code>) to your
                    <code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-xs">.env</code> file.
                    See the <strong>Payments</strong> section of <code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-xs">README.md</code>.
                </p>
            </div>
        @endunless

        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
            {{-- Contact details --}}
            <form method="POST" action="{{ route('checkout.store') }}" class="space-y-6">
                @csrf

                {{-- Contact --}}
                <div class="rounded-2xl border border-pit-200 bg-white p-6 shadow-sm">
                    <h2 class="font-semibold">Contact</h2>
                    <p class="mt-1 text-sm text-pit-600">Your receipt and tracking go here.</p>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <x-checkout-field name="customer_name" label="Full name" autocomplete="name" required />
                        <x-checkout-field name="customer_email" label="Email address" type="email" autocomplete="email" required />
                    </div>
                </div>

                {{-- Shipping --}}
                <div class="rounded-2xl border border-pit-200 bg-white p-6 shadow-sm">
                    <h2 class="font-semibold">Shipping address</h2>
                    <p class="mt-1 text-sm text-pit-600">
                        Where should the balls go? Pit balls ship in bulk cartons, so a kerbside or
                        loading-dock address works best.
                    </p>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <x-checkout-field
                                name="shipping_name"
                                label="Recipient name"
                                autocomplete="shipping name"
                                required
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <x-checkout-field
                                name="shipping_line1"
                                label="Street address"
                                autocomplete="shipping address-line1"
                                required
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <x-checkout-field
                                name="shipping_line2"
                                label="Apartment, suite, unit"
                                autocomplete="shipping address-line2"
                                hint="Optional"
                            />
                        </div>

                        <x-checkout-field
                            name="shipping_city"
                            label="City"
                            autocomplete="shipping address-level2"
                            required
                        />

                        <x-checkout-field
                            name="shipping_region"
                            label="{{ $regionLabel }}"
                            autocomplete="shipping address-level1"
                            required
                        />

                        <x-checkout-field
                            name="shipping_postal_code"
                            label="Postal code"
                            autocomplete="shipping postal-code"
                            required
                        />

                        <div>
                            <label for="shipping_country" class="block text-sm font-medium text-pit-800">
                                Country
                            </label>
                            <select
                                id="shipping_country"
                                name="shipping_country"
                                autocomplete="shipping country"
                                required
                                @class([
                                    'mt-1.5 block w-full rounded-lg border bg-white px-3 py-2.5 shadow-sm focus:outline-none focus:ring-2',
                                    'border-red-400 focus:border-red-500 focus:ring-red-500/30' => $errors->has('shipping_country'),
                                    'border-pit-300 focus:border-pit-500 focus:ring-pit-500/30' => ! $errors->has('shipping_country'),
                                ])
                            >
                                @foreach ($shippingCountries as $code => $name)
                                    <option value="{{ $code }}" @selected(old('shipping_country', array_key_first($shippingCountries)) === $code)>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('shipping_country')
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <x-checkout-field
                                name="shipping_phone"
                                label="Phone"
                                type="tel"
                                autocomplete="shipping tel"
                                hint="Optional — couriers use it for bulk deliveries."
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label for="delivery_notes" class="block text-sm font-medium text-pit-800">
                                Delivery notes
                            </label>
                            <p class="mt-0.5 text-xs text-pit-500">Optional — gate codes, access hours, where to leave it.</p>
                            <textarea
                                id="delivery_notes"
                                name="delivery_notes"
                                rows="2"
                                class="mt-1.5 block w-full rounded-lg border border-pit-300 px-3 py-2.5 shadow-sm focus:border-pit-500 focus:outline-none focus:ring-2 focus:ring-pit-500/30"
                            >{{ old('delivery_notes') }}</textarea>
                            @error('delivery_notes')
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Payment method: one button per configured provider. --}}
                <div class="rounded-2xl border border-pit-200 bg-white p-6 shadow-sm">
                    <h2 class="font-semibold">How would you like to pay?</h2>

                    @error('provider')
                        <p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror

                    <div class="mt-4 space-y-3">
                        @if ($stripeEnabled)
                            <button
                                type="submit"
                                name="provider"
                                value="stripe"
                                class="flex w-full items-center justify-center gap-2.5 rounded-xl bg-pit-600 px-6 py-4 text-base font-semibold text-white shadow-lg shadow-pit-600/25 transition hover:bg-pit-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-pit-500 focus-visible:ring-offset-2"
                            >
                                <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M2 7.5A2.5 2.5 0 0 1 4.5 5h15A2.5 2.5 0 0 1 22 7.5V9H2V7.5Zm0 3.5h20v5.5A2.5 2.5 0 0 1 19.5 19h-15A2.5 2.5 0 0 1 2 16.5V11Zm3 4a1 1 0 1 0 0 2h3a1 1 0 1 0 0-2H5Z" />
                                </svg>
                                Pay by card
                            </button>
                        @endif

                        @if ($paypalEnabled)
                            <button
                                type="submit"
                                name="provider"
                                value="paypal"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#ffc439] px-6 py-4 text-base font-bold text-[#003087] shadow-lg shadow-amber-500/20 transition hover:bg-[#f0b72f] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#003087] focus-visible:ring-offset-2"
                            >
                                <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M9.6 21.2H6.1a.6.6 0 0 1-.6-.7L8.3 3.2a.8.8 0 0 1 .8-.7h6c3.4 0 5.4 1.7 4.9 5-.5 3.4-3 5.2-6.4 5.2h-2a.8.8 0 0 0-.8.7l-.6 7a.6.6 0 0 1-.6.8Z" />
                                    <path d="M18.7 8.1c.5 1.2.3 2.7-.2 4.2-.9 2.6-3.1 3.9-6.2 3.9h-1.5a.7.7 0 0 0-.7.6l-.7 4.6a.5.5 0 0 0 .5.6h2.7a.7.7 0 0 0 .7-.6l.5-3.4a.7.7 0 0 1 .7-.6h1.2c3 0 5.2-1.6 6-5 .4-1.8.2-3.3-.8-4.3a3.1 3.1 0 0 0-.9-.6c-.1.1-.2.1-.3 0Z" fill-opacity="0.65" />
                                </svg>
                                PayPal
                            </button>
                        @endif
                    </div>

                    <p class="mt-4 text-xs leading-relaxed text-pit-500">
                        @if ($stripeEnabled && $paypalEnabled)
                            You will be taken to Stripe or PayPal to pay securely. We never see your card details.
                        @elseif ($paypalEnabled)
                            You will be taken to PayPal to pay securely.
                        @else
                            You will be taken to Stripe to pay securely. We never see your card details.
                        @endif
                    </p>
                </div>
            </form>

            {{-- Order summary --}}
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-2xl border border-pit-200 bg-white p-6 shadow-sm">
                    <h2 class="font-semibold">Order summary</h2>

                    <ul class="mt-4 divide-y divide-pit-100">
                        @foreach ($lines as $line)
                            <li class="flex items-start gap-3 py-3">
                                <x-ball-swatch :ball-type="$line['ballType']" class="mt-0.5 size-7" />

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
