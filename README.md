# Ball Pit Palette

A storefront for custom plastic ball-pit orders. Customers pick colours and
quantities, watch their mix pour into a bag, and check out through Stripe.

Branding (name, logo, palette, copy) is placeholder — see
[Replacing the placeholder branding](#replacing-the-placeholder-branding).

## Stack

- Laravel 13 / PHP 8.4, SQLite by default
- Blade + Tailwind CSS 4, bundled with Vite
- [Laravel Cashier (Stripe)](https://laravel.com/docs/billing) for payments
- A dependency-free canvas animation for the bag

## Getting started

```sh
composer install
npm install
cp .env.example .env        # if you don't already have one
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed  # seeds the 10 ball types
npm run build               # or: npm run dev
php artisan serve
```

The catalogue lives in `database/seeders/BallTypeSeeder.php`. Re-running
`php artisan db:seed` updates existing rows in place rather than duplicating
them.

## How an order flows

| Step | Route | What happens |
| --- | --- | --- |
| 1 | `GET /` | Landing page with the **Order now** call to action |
| 2 | `GET /order` | Pick colours and quantities; the bag animates the mix |
| 3 | `POST /order` | Selection is validated and stored in the session cart |
| 4 | `GET /checkout` | Review the order, collect name and email |
| 5 | `POST /checkout` | A `pending` order is written, then Stripe Checkout opens |
| 6 | `GET /checkout/success` | Confirms payment and empties the cart |
| 7 | `POST /stripe/webhook` | Marks the order paid — the source of truth |

Orders snapshot the ball name, colour, and unit price at the time of purchase,
so later catalogue edits never rewrite past orders.

Payment state is driven by the webhook, not the browser redirect: a customer
who closes the tab after paying still gets a paid order. The success page
double-checks the session so the customer sees an accurate status immediately.

## Payments

### Stripe

Add your keys from the [Stripe dashboard](https://dashboard.stripe.com/test/apikeys):

```dotenv
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Until `STRIPE_SECRET` is set, the checkout page shows a notice and refuses to
create orders rather than failing at the payment step.

Forward webhooks while developing with the
[Stripe CLI](https://docs.stripe.com/stripe-cli):

```sh
stripe listen --forward-to localhost:8000/stripe/webhook
```

Cashier registers `POST /stripe/webhook` for you and verifies the signature
when `STRIPE_WEBHOOK_SECRET` is set. `App\Listeners\FulfillOrderOnPayment`
handles the `checkout.session.*` events; its `TODO` marks where the
confirmation email and warehouse notification belong.

### PayPal — decided, not yet built

**Decision: a direct PayPal integration alongside Stripe.** The store runs on a
US Stripe account, which rules out the simpler routes.

Background, verified against Stripe's docs on 2026-11-14:

- **PayPal processed by Stripe** is only available to Stripe accounts based in
  [supported European countries](https://docs.stripe.com/payments/paypal#paypal-business-locations)
  (EEA, UK, Switzerland). Customers may be anywhere, but the *merchant* account
  cannot be in the US. Not an option here.
- **Stripe's [PayPal custom payment method](https://docs.stripe.com/payments/payment-methods/custom-payment-methods/paypal)**
  is in preview and gated: it needs access from a Stripe account manager, and
  even once granted you still "build, host, and maintain the integration
  between Checkout and PayPal" yourself. Same work as going direct, plus a
  gatekeeper.

So PayPal will be wired up directly against the
[PayPal Orders v2 API](https://developer.paypal.com/docs/api/orders/v2/), using
the merchant's own PayPal account, offered beside the existing Stripe card
checkout. Planned shape:

- OAuth2 client-credentials token from `/v1/oauth2/token`
- Create order at `/v2/checkout/orders` with `intent: CAPTURE` and the mix as
  `purchase_units[].items`
- Redirect the buyer to the `payer-action` link, with `return_url` and
  `cancel_url` set in `payment_source.paypal.experience_context`
- Capture at `/v2/checkout/orders/{id}/capture` on return, with a
  `PAYMENT.CAPTURE.COMPLETED` webhook as the source of truth, mirroring how
  Stripe is handled today

`App\Support\StripeCheckout` is the only place a Checkout session is built, so
a PayPal provider slots in beside it without touching the cart, order, or
webhook plumbing. **Not implemented yet** — the current checkout is Stripe only.

### Why inline prices, not Stripe Prices

Every order is a bespoke mix, so there is no fixed catalogue of Stripe Prices.
`StripeCheckout` sends each line as inline `price_data`, with the ball colour
in the line description.

## Tests

```sh
php artisan test        # 31 PHP tests: cart, validation, checkout, webhooks
npm run test:bag        # the bag animation, headless
vendor/bin/pint         # formatting
```

`npm run test:bag` runs `tests/js/ball-bag.test.mjs`, which drives the real
animation module under Node with a stubbed canvas and asserts on what it
draws: that balls stay inside the bag, that colour proportions survive
sampling, that a small accent colour never disappears, and that the fill level
tracks the order size. It needs no test framework — just `node`.

## The bag animation

`resources/js/ball-bag.js` is a small canvas simulation with no dependencies.

A 20,000-ball order cannot be drawn ball by ball, so the bag renders a
**sample**: at most 170 balls, allocated across the selected colours by
largest-remainder rounding so the proportions stay true and any ordered colour
gets at least one ball. Below 170 balls the mix is drawn one-for-one. Above it
the bag is simply full, and the caption states how many real balls each drawn
ball represents.

Other behaviour worth knowing:

- Balls pour in over ~0.9s regardless of batch size, shuffled so colours blend
  rather than arriving in layers.
- Simulation stops ~3.5s after the last change. A dense pile jitters forever
  under positional correction and never reaches a true velocity rest, so the
  loop would otherwise never idle.
- `prefers-reduced-motion` skips the fall: the pile is settled before the
  first paint.
- Bag updates are debounced 140ms, so typing "5000" pours once, not four
  times.

## Replacing the placeholder branding

| What | Where |
| --- | --- |
| Name, tagline, support email | `config/ballpit.php` or `BALLPIT_*` in `.env` |
| Logo | `resources/views/components/logo.blade.php` |
| Colour palette | the `--color-pit-*` tokens in `resources/css/app.css` |
| Hero illustration | `resources/views/landing.blade.php` |

## Known gaps

Deliberately out of scope for this scaffold:

- PayPal is designed but not built — see [PayPal](#paypal--decided-not-yet-built)
- No confirmation email yet (see the `TODO` in `FulfillOrderOnPayment`)
- No admin view of orders, and no stock or lead-time tracking
- No volume discounts — pricing is flat per ball, per type
- Shipping is a flat fee from config, not calculated by weight or destination
- Guest checkout only; no customer accounts or order history
