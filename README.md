# Ball Pit Palette

A storefront for custom plastic ball-pit orders. Customers pick colours and
quantities, watch their mix pour into a bag, and check out through Stripe.

Branding (name, logo, palette, copy) is placeholder — see
[Replacing the placeholder branding](#replacing-the-placeholder-branding).

## Stack

- Laravel 13 / PHP 8.4, SQLite by default
- Blade + Tailwind CSS 4, bundled with Vite
- [Laravel Cashier (Stripe)](https://laravel.com/docs/billing) for card payments
- PayPal Orders v2, called directly over HTTP (no SDK)
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
| 2 | `GET /order` | Build the mix: colours and quantities, with the bag animating |
| 3 | `POST /order` | Selection is validated and stored in the session cart |
| 4 | `GET /checkout` | The bag: review, contact details, shipping address, pay |
| 5 | `POST /checkout` | A `pending` order is written, then the chosen provider opens |
| 6 | `GET /checkout/success` or `/checkout/paypal/return` | Confirms payment and empties the cart |
| 7 | `POST /stripe/webhook` or `/paypal/webhook` | Marks the order paid — the source of truth |

`/order` builds the mix and hands off; **`/checkout` (the bag) is the only
place an order is actually placed.** An empty bag renders its own page rather
than redirecting to the builder, which previously made the two look like the
same page.

Orders snapshot the ball name, colour, and unit price at the time of purchase,
so later catalogue edits never rewrite past orders.

### Shipping

The shipping address is collected by us at checkout, not by the payment
provider, and stored in discrete columns on the order so fulfilment can read
and filter them. Both providers are then told the address rather than asking
for it again: Stripe receives it as `payment_intent_data.shipping` so it shows
on the payment in the dashboard, and PayPal receives it with
`shipping_preference: SET_PROVIDED_ADDRESS` so the buyer cannot silently
substitute another one.

Destinations come from `shipping_countries` in `config/ballpit.php` (US and
Canada by default) with a matching `region_labels` entry for the State vs
Province wording. Note that `shipping_flat_cents` is one flat rate for every
destination on that list, so review it before adding countries that cost more
to reach.

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

> **`STRIPE_WEBHOOK_SECRET` is required, not optional.** Cashier only verifies
> signatures when it is set, so without it the endpoint would accept a forged
> "payment succeeded" and mark an order paid. The listener therefore refuses to
> act on any webhook while the secret is missing, and logs a warning — so an
> unconfigured environment fulfils nothing rather than fulfilling anything.

Payment methods are **not** listed in the API call: `payment_method_types` is
deliberately never sent, which keeps Stripe's dynamic payment methods on so
each customer is offered the most relevant of whatever is enabled on the
account. Change what you accept in the Stripe Dashboard, not in code.

### PayPal

PayPal runs as a **direct integration against the PayPal Orders v2 API**, using
your own PayPal account, offered beside the Stripe card checkout. The customer
picks a button at checkout and is redirected to whichever provider they chose.

```dotenv
PAYPAL_MODE=sandbox          # "live" in production
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_WEBHOOK_ID=
```

Credentials come from the
[PayPal developer dashboard](https://developer.paypal.com/dashboard/applications/sandbox).
Create a **webhook** subscribed to `PAYMENT.CAPTURE.COMPLETED`,
`PAYMENT.CAPTURE.DENIED` and `CHECKOUT.ORDER.APPROVED`, pointing at
`https://your-domain/paypal/webhook`, and put its ID in `PAYPAL_WEBHOOK_ID`.

Each provider appears at checkout only when it is configured, so you can run
card-only, PayPal-only, or both.

#### The flow

1. `POST /checkout` writes a `pending` order tagged with the chosen provider.
2. A PayPal order is created with `intent: CAPTURE`, the mix as
   `purchase_units[].items`, and `payment_source.paypal.experience_context`
   carrying the return and cancel URLs. Supplying the payment source up front
   means no separate "confirm" call is needed.
3. The customer is redirected to the `payer-action` link.
4. `GET /checkout/paypal/return` captures the payment so the customer gets an
   immediate answer.
5. `POST /paypal/webhook` is the source of truth. Every webhook is verified
   through PayPal's `verify-webhook-signature` endpoint and rejected with a
   403 if it does not check out.

Two details worth knowing:

- **Idempotency.** Create and capture both send a `PayPal-Request-Id` derived
  from the order reference, so a double submit or a retry cannot charge twice.
- **A missed return is still fulfilled.** If the customer approves and then
  closes the tab, `CHECKOUT.ORDER.APPROVED` arrives by webhook and the capture
  happens server-side.

#### Why not PayPal through Stripe?

Checked against Stripe's docs on 2026-11-14, for a **US Stripe account**:

- **PayPal processed by Stripe** is restricted to Stripe accounts based in
  [supported European countries](https://docs.stripe.com/payments/paypal#paypal-business-locations)
  (EEA, UK, Switzerland). Customers may be anywhere; the merchant account may
  not be in the US.
- **Stripe's [PayPal custom payment method](https://docs.stripe.com/payments/payment-methods/custom-payment-methods/paypal)**
  is a gated preview requiring access from a Stripe account manager, and even
  then you "build, host, and maintain the integration between Checkout and
  PayPal" yourself — the same work as going direct, plus a gatekeeper.

If you ever move to a European Stripe entity, enabling PayPal in the Stripe
Dashboard would be enough for Stripe to offer it, and the direct integration
could be retired.

#### No SDK

PayPal's REST API is small enough for Laravel's HTTP client — token, create
order, capture, verify webhook — so `App\Support\PayPalClient` talks to it
directly and there is no PayPal SDK dependency. Access tokens are cached until
a minute before they expire.

### Why inline prices, not Stripe Prices

Every order is a bespoke mix, so there is no fixed catalogue of Stripe Prices.
`StripeCheckout` sends each line as inline `price_data`, with the ball colour
in the line description. PayPal is itemised the same way, with an `item_total`
and `shipping` breakdown that adds up to the order total exactly — PayPal
rejects the order otherwise.

## Admin area

Catalogue management lives at `/admin`, gated by an `is_admin` flag on the user.

```sh
php artisan ballpit:make-admin you@example.com
```

That grants admin access to an existing account, or creates one and prints a
generated password. Then sign in at `/admin/login` to add ball types, edit
prices, reorder colours, hide a colour from the shop, and upload photos.

### Ball photos

Each ball type has a flat `color_hex` and an optional photo. Upload a photo of
the real ball and frame it with the circular cropper: drag to pan, scroll or
drag the slider to zoom. The browser posts the crop rectangle in source-image
pixels and the server does the real crop with **GD**, which ships with PHP — no
image library dependency.

Photos are stored as 512px square WebP. The circle is applied at display time
(CSS on the storefront, a canvas clip in the bag animation) rather than baked
into the file, so one file works everywhere. A ball type with a photo uses it
for its swatch *and* as the ball texture inside the bag animation; without one
it falls back to the generated gloss gradient.

Where photos are written comes from `BALLPIT_IMAGE_DISK` (default `public`).
Run `php artisan storage:link` once locally.

> **On Laravel Cloud the filesystem is ephemeral** — anything written to local
> disk disappears on the next deploy. Attach a Cloud Object Storage bucket as a
> **public** disk named `public`, and install the S3 adapter:
> `composer require league/flysystem-aws-s3-v3 "^3.0"`.

Ball types that appear in past orders can be hidden but not deleted, so order
history stays intact.

### Seeding the catalogue

The 12 defaults live in `database/seeders/BallTypeSeeder.php`. Load them with:

```sh
php artisan ballpit:sync-ball-types
```

It only inserts colours that are missing and never overwrites an existing row,
so it is safe in a deploy command and your admin edits survive redeploys. Use
`--overwrite` to deliberately reset rows back to the defaults.

A Laravel Cloud deploy command that keeps the catalogue populated:

```sh
php artisan migrate --force && php artisan ballpit:sync-ball-types
```

## Tests

```sh
php artisan test        # 82 PHP tests: cart, checkout, both providers, admin, photos
npm run test:pit        # the ball pit animation, headless
npm run test:visual     # the same animation, rendered in a real browser
vendor/bin/pint         # formatting
```

`npm run test:pit` runs `tests/js/ball-pit.test.mjs`, which drives the real
animation module under Node with a stubbed canvas and asserts on what it
draws: that balls stay inside the pit, that colour proportions survive
sampling, that a small accent colour never disappears, that the fill level
tracks the order size, and that balls are painted back to front. It needs no
test framework — just `node`.

`npm run test:visual` renders `/order` in Chromium with Playwright, orders a
few colours, and reads pixels back off the canvas. It needs the app running:

```sh
php artisan serve &
npm run test:visual -- http://127.0.0.1:8000
```

It exists because the headless stub records `drawImage` calls and therefore
**cannot see one shape painted over another**. A fill that swallowed the
entire pit once passed all 24 headless checks while rendering an empty tub.
The pixel test catches that class of bug; the headless one catches the
physics and allocation. Keep both.

## The ball pit animation

`resources/js/ball-pit.js` is a canvas simulation with no dependencies.

Balls are simulated in **three dimensions** inside a cylinder and projected in
3/4 perspective: further-back balls sit higher on screen, are drawn smaller
and are hazed toward shadow, and the frame is painted back to front. That
depth is what makes a heap of circles read as a pit of balls rather than a
flat wall of them.

A 20,000-ball order cannot be drawn ball by ball, so the pit renders a
**sample**: at most 170 balls, allocated across the selected colours by
largest-remainder rounding so the proportions stay true and any ordered colour
gets at least one ball. Below 170 the mix is drawn one-for-one. Above it the
pit is simply full, and the caption states how many real balls each drawn ball
represents.

Other behaviour worth knowing:

- Balls pour in over ~1s regardless of batch size, shuffled so colours blend
  rather than arriving in layers.
- The pit is deliberately **shallow and wide**, and a full order heaps
  *above* the rim. In this projection you see into the pit through its mouth,
  so a deep bucket hides its own contents: anything resting well below the
  rim sits behind the near wall. `OVERFILL` controls how far it brims over.
- Ball radius is derived from the pit's dimensions so this holds at any
  canvas size, rather than being a magic number tuned for one screen.
- The rim is stroked in two halves — the far half behind the balls, the near
  half over them — or the line is drawn straight across the pile.
- Simulation stops ~3.5s after the last change. A dense pile jitters forever
  under positional correction and never reaches a true velocity rest, so the
  loop would otherwise never idle.
- `prefers-reduced-motion` skips the fall: the pile is settled before the
  first paint.
- Updates are debounced 140ms, so typing "5000" pours once, not four times.
- A ball type with an uploaded photo uses it as the ball texture, falling back
  to a generated gloss sprite while it loads or when there is none.

## Replacing the placeholder branding

| What | Where |
| --- | --- |
| Name, tagline, support email | `config/ballpit.php` or `BALLPIT_*` in `.env` |
| Logo | `resources/views/components/logo.blade.php` |
| Ball colours and photos | `/admin/ball-types` |
| Colour palette | the `--color-pit-*` tokens in `resources/css/app.css` |
| Hero illustration | `resources/views/landing.blade.php` |

## Known gaps

Deliberately out of scope for this scaffold:

- No confirmation email yet (see the `TODO` in `FulfillOrderOnPayment`)
- The admin area covers the catalogue only — no order management yet
- No stock or lead-time tracking
- No volume discounts — pricing is flat per ball, per type
- Shipping is a flat fee from config, not calculated by weight or destination
- Guest checkout only; no customer accounts or order history
