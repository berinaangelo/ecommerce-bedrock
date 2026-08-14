# Plan

_Last updated: 2026-08-14_

## One-sentence description

A shopper picks products, checks out with an address and card, and gets their order shipped.

## Stack

- **Backend**: WordPress via [Bedrock](https://roots.io/bedrock/) (Composer-managed, env-based config) + WooCommerce, added to `composer.json`. The frontend talks only to the Store API (`/wc/store/v1/...`) — it's public/no-key by design, unlike the admin REST API (`/wc/v3/...`), which needs a Consumer Key/Secret and is never called from the browser. Login/account-creation instead use WordPress's own cookie/nonce auth (same-origin, no CORS), fitting the hybrid architecture rather than adding a separate token/JWT layer.
- **Theme**: [TailPress](https://tailpress.io/) — plain Underscores-style WP theme with Tailwind CSS/PostCSS, no Blade/Acorn layer. Chosen over Sage (also Roots, pairs with Bedrock) for being lighter — fewer moving parts to wire AngularJS into.
- **Frontend**: AngularJS 1.x, embedded into the TailPress theme's build (not a separately hosted SPA) — enqueued as the theme's JS/CSS bundle, same-origin, no CORS to manage.
  - Note: AngularJS is EOL (no security patches since Jan 2022). Kept deliberately per explicit choice — see "Portfolio differentiators" below for how that's framed rather than hidden.
- **Architecture**: embedded/hybrid, not fully headless. WordPress renders the page shell; Angular takes over the storefront regions and calls the WooCommerce REST/Store API.

## Scope decisions (locked in)

- **Products**: physical goods only — checkout needs a shipping address and shipping cost/method.
- **Accounts**: guest checkout by default, with an optional "create an account with this order" checkbox at checkout. Login for an *existing* account is also supported, but optional — a shopper can always check out as a guest. The trade-off: guest orders don't get real-time purchase history/delivery-status visibility the way an account holder's do (that visibility comes from WooCommerce's own account dashboard, not a custom build — see "Cut" below). Auth mechanism: WordPress cookie/nonce auth (same-origin), not a custom token scheme — see Stack.
- **Payment**: single processor — Stripe. No payment-method picker.
- **Catalog**: simple products only, no variants (size/color/etc).

## Core flow / modules

Each module is a step of the one shopper story — nothing runs in parallel to it, and each is fully demoable on its own with no "coming later" caveat.

| # | Module | What it does | Talks to |
|---|--------|--------------|----------|
| 1 | **Catalog** | Lists products (image, name, price) | WC Store API `GET /wc/store/v1/products` |
| 2 | **Product detail** | Shows one product, "Add to cart" | WC Store API `GET /wc/store/v1/products/{id}`, add-to-cart |
| 3 | **Cart** | View items, change qty, remove, see total | WC Store API `/wc/store/v1/cart` |
| 4 | **Checkout** | Shipping address + optional login/account-creation + Stripe card field | WC Store API checkout endpoint + Stripe |
| 5 | **Order confirmation** | Shows what was ordered and that it's paid | Response from checkout call |

## Cut (explicitly out, not deferred)

Each failed the "why does this exist" test — the core flow doesn't break without it:

- **Search / category filters** — WordPress/WooCommerce render these by default on archive pages; not a custom module.
- **Wishlist** — not on the path to buying.
- **Reviews/ratings** — doesn't affect whether an order can be placed.
- **Coupons/discounts** — not requested; WooCommerce has a free-text coupon field if ever needed.
- **Order history / "my account" dashboard** — WooCommerce/WordPress provide this for free once accounts exist; not custom-built.
- **Admin/catalog management UI** — wp-admin + WooCommerce admin, untouched.
- **Tax, multi-currency, product variants, multiple payment methods** — ruled out by the scope decisions above.
- **Docker/CI pipelines, microservices, multi-env deploy automation** — infra theater for this scope; a simple working app matters more.
- **Extra shopper-facing "wow factor" features** (animations, recommendations) — padding the flow to look impressive is the opposite of the goal.

## Five-gate check

- **One-sentence** ✅ — no "and" stacking two separate ideas, just one flow.
- **Grandma** ✅ — shopper never sees a setting or config screen; guest checkout is the default path.
- **Demo** ✅ — every module is walkable start to finish live; nothing needs a "coming later" caveat.
- **Why-does-this-exist** ✅ — all five modules are steps the order literally cannot complete without.
- **One-narrative** ✅ — reads as one shopper's path, not five bolted-on features.

## Portfolio differentiators

This is a portfolio/learning project — "unique" doesn't mean extra shopper-facing features (that would fail the gates above). It means the build reads as skill, not a theme-and-plugins install, to anyone looking at the repo afterward.

**In:**
- Decoupled Angular SPA over the WC Store API instead of WooCommerce's default templates — the real engineering work; don't quietly fall back to default templates in any of the 5 modules.
- Bedrock's Composer-managed, env-based config, git-tracked from the start — already the stack, costs nothing extra.
- A handful of real Pest feature tests (cart totals, checkout validation) — `pestphp/pest` is already in `composer.json` but unused.
- A README "case study" section: architecture diagram + why AngularJS/Bedrock/WooCommerce/TailPress were chosen over the obvious defaults, so deliberate choices (like AngularJS) read as intentional, not dated.

**Cut:** Docker/CI/microservices theater, extra shopper-facing features for "wow factor," rewriting in a trendier framework just to look modern.

## Engineering guidelines

Ties into "Portfolio differentiators" above — the code itself is part of what's being demonstrated, not just the working flow. Applies to both the PHP (theme/WooCommerce integration) and the AngularJS side as the 5 modules get built:

- **OOP over procedural WP-style spaghetti** — classes with clear single responsibilities (e.g. a `CartService`, `CheckoutService`, `StripePaymentGateway`) instead of loose functions in `functions.php`.
- **Reach for a design pattern where it solves a real problem the code already has** — e.g. Strategy for swappable behavior (payment gateway could be a `PaymentStrategy` interface even with only Stripe implementing it today), Builder for assembling multi-step objects (e.g. an order/checkout payload built up across the checkout form's steps), Repository for isolating WC REST/Store API calls from the Angular controllers/components that use them.
- **Guardrail**: a pattern must earn its place the same way a module does — if it's there because "it's good practice" rather than because the code without it is genuinely harder to read or change, it's over-engineering and fails the plan's own "why does this exist" gate. Simple code stays simple; patterns show up where real complexity (swappable strategies, multi-step construction, decoupling from an external API) already exists.

### Payment & external-API guardrails

Constraints on how Cart and Checkout (modules 3–4) get built — not new scope, but non-negotiable given real money is involved:

- **Stripe key split, enforced not just assumed**: the publishable key is the *only* Stripe key that may ever appear in the Angular bundle/theme JS. The secret key lives in `.env` server-side only, used exclusively by PHP. Concrete, project-specific application of the standing "never expose sensitive keys" rule.
- **Raw card data never touches the WP server** — use Stripe's client-side Elements/Payment Element for card capture, so the server only ever sees a token/payment intent ID, never a card number. Keeps the project out of PCI-DSS scope entirely, not just "more secure."
- **External calls (WC REST/Store API, Stripe) fail without corrupting state** — a timed-out or failed call must never leave the cart half-updated or silently retry into a double charge. Project-specific application of the standing "always generate with a fallback" rule.
- **Checkout is idempotent** — guard against a double-click or retried request creating two orders/two charges for the same cart (disable-on-submit, plus checking for an existing pending order before creating a new one).
- **Order finalization doesn't depend on the client staying connected** — a Stripe webhook (`payment_intent.succeeded`) marks the order paid server-side, so a browser tab dying/losing connection after Stripe confirms payment can't leave a charge with no matching WooCommerce order.

### Readability checklist ("grandma test" for code)

This isn't something `composer lint` can check mechanically — Pint stays in place in `composer.json` (`pint --test` / `pint`) for what it's actually good at: enforcing consistent style. This checklist is a separate, manual pass applied during self-review/PR, on top of Pint, not instead of it:

- Could someone unfamiliar with this codebase read the function/class and understand what it does without asking you first? If not, it needs a rename, a split, or a comment explaining *why* (not *what*).
- Names are descriptive, not abbreviated or clever (`$cartTotal`, not `$ct`; `CalculateShippingCost()`, not a one-liner buried in a ternary).
- No unexplained magic numbers/strings — pull them into a named constant if their meaning isn't obvious from context.
- Each function/method does one thing — if you need "and" to describe what it does, split it.
- No loop nesting 3+ deep (already a standing rule) — flatten with early returns/helpers/collection methods instead.

## Next steps

1. ~~Resolve the open account/login question above.~~ Done — login for an existing account is supported and optional; guest checkout always remains available.
2. ~~Scaffold: set up `.env`/DB config; pull in TailPress as the active theme; add WooCommerce to `composer.json`.~~ Done — `bin/setup.sh` creates the MySQL database + app user, writes `.env`, and runs the WordPress install via WP-CLI (`wp-cli/wp-cli-bundle`, added as a Composer dev dependency). WooCommerce (`wp-plugin/woocommerce`, currently 11.0.1) is now a Composer dependency, resolved via the `wp-packages.org` repository already configured in `composer.json`. TailPress was scaffolded via its own installer (`composer global require tailpress/installer`, then `tailpress new`) into `web/app/themes/tailpress/` and committed to git — it isn't a normal Composer dependency of the root project, since its Packagist package is typed `"project"`, not `"wordpress-theme"`. `bin/setup.sh` now also builds the theme's assets and activates both the theme and WooCommerce on every run. Run `./bin/setup.sh` from repo root.
3. ~~Wire AngularJS into the TailPress build pipeline alongside Tailwind.~~ Done — AngularJS 1.8.3 is loaded from the jsDelivr CDN (deliberately not an npm/pnpm dependency for now), pinned with a matching SRI hash, enqueued in `functions.php`. The app's own code lives in `resources/js/angular-app.js` (currently just the `ecommerceApp` module shell) and is built by Vite like the theme's other JS/CSS, declaring `angularjs` as its WP script dependency so load order is correct. `ng-app="ecommerceApp"` is set on the `<main>` element in `header.php` — the boundary between WordPress's page shell and the Angular-controlled storefront region, per the architecture above.
4. Build modules 1–5 in order against the WC REST/Store API. Each module gets its own `page-{slug}.php` template bootstrapping the shared `ecommerceApp` Angular module with a page-specific controller — WooCommerce already auto-creates a dedicated page per module (Shop/Cart/Checkout), so WordPress's own page routing is reused instead of adding an Angular router.
   - **Module 1: Catalog** — lists products (image, name, price) via `GET /wc/store/v1/products`. Done — the storefront shell (`header.php`/`footer.php`) was also restyled to match `docs/mockups/` (fonts, nav, footer; see `resources/css/storefront.css`), a deliberate one-time cost taken now rather than deferred. Two environment gaps surfaced and were fixed in `bin/setup.sh`: WooCommerce requires pretty permalinks (plain `?p=` URLs break the Store API and `page-{slug}.php` routing) — now set via `wp rewrite structure`; and a fresh WooCommerce install defaults to "Coming soon" mode for the store, which intercepts every store page (Shop/Cart/Checkout/product) regardless of template — now disabled via the `woocommerce_coming_soon` option.
     - [x] 1.0 Seed 2–3 test products with images via wp-admin/WP-CLI (prerequisite, not app code — DB currently has zero products).
     - [x] 1.1 `page-shop.php` template (WP's `page-{slug}.php` convention, attaches to the existing Shop page) + PHP→JS Store API endpoint bridge via `rest_url('wc/store/v1/')`.
     - [x] 1.2 `ProductRepository` Angular service wrapping the Store API GET, with a fallback on failure per the standing guardrail.
     - [x] 1.3 Price formatting helper (`prices.price` is a minor-unit string, not display-ready — reused by modules 2–4 later).
     - [x] 1.4 `CatalogController` + grid view (matching `docs/mockups/index.html`), with explicit loading/empty/error states.
     - [x] 1.5 Wire into `page-shop.php` + end-to-end verification against seeded data.
   - **Module 2: Product detail** — done. Themed single-product page matching `docs/mockups/product.html`: image, name, price (sale/out-of-stock states), description, quantity stepper, working Add to Cart. Replaces WooCommerce's unstyled fallback markup that rendered before this (the theme has no `add_theme_support('woocommerce')`, so WP fell through to `single.php`'s blog-post chrome).
     - Routing: a theme-level `single-product.php` — WP's own template-hierarchy name for the `product` CPT, not a `page-{slug}.php` like Module 1's Shop page, since no WP Page exists per product. The product id is localized directly per-page (`ecommerceStoreApi.productId`) rather than parsed from the URL, since the app has no client-side router.
     - `ProductRepository.getById(id)` added alongside `getAll()`, same shape/fallback convention.
     - New `CartRepository` (`POST /wc/store/v1/cart/add-item`) — the app's first Store API *write*. Writes need a `Nonce` header, which (confirmed against the plugin source) only cart routes return on their response — a plain `GET /products/{id}` never sets it. `store-api-nonce.js` primes it with a `GET /wc/store/v1/cart` on Angular bootstrap (same approach the official WC Blocks client uses) and an `$http` interceptor keeps it fresh from later cart-route responses; `CartRepository` reads it when POSTing. Built now since Modules 3–4 (cart updates, checkout) need this same nonce.
     - Catalog grid's decorative arrow (`page-shop.php`) is now a real click-through: a `.card-link` whole-card overlay anchor to `product.permalink`, matching the mockup's own pattern.
     - Cut: the mockup's spec-list (Battery/Connectivity/etc.) — no source in the Store API `ProductSchema` and no seeded product meta to back it.
     - Vite's `rollupOptions.input` in `vite.config.mjs` is a separate, manually-maintained list from `functions.php`'s `registerAsset()` calls — a new JS file needs adding to both, or `pnpm build` silently omits it from `dist/`.
   - **Module 3: Cart** — view items, change qty, remove, see total, via the Store API's `/wc/store/v1/cart` routes. The cart already has real items in it from Module 2's working "Add to Cart" — this module is the page (and nav badge) to view/manage it.
     - Decisions locked in: no shipping line/free-shipping progress bar like `docs/mockups/cart.html` shows — `total_shipping` is `null` in the Store API response until a shipping address exists (confirmed in `CartSchema.php`'s `has_calculated_shipping()` check), which is Module 4's job, not Module 3's; showing one now would be the same kind of fake/hardcoded block Module 2 already cut the spec-list for. No optimistic UI updates — every mutation re-renders from that call's own fresh response (same "server is the source of truth" convention as `ProductRepository`), with a per-line "updating" flag guarding against double-click races. "Continue to Checkout" links to `/checkout/` and is left inert (hits WooCommerce's default unstyled fallback) until Module 4 exists.
     - [x] 3.0 Widen the Angular boundary: move `ng-app="ecommerceApp"` from `<main>` to `<div id="page">` in `header.php`. Prerequisite for 3.3 — the nav (with the cart badge) is currently a sibling of `<main>`, outside Angular's reach.
     - [x] 3.1 Extend `CartRepository` with `getCart()` (GET), `updateItem(key, quantity)`, and `removeItem(key)` (POST + `Nonce` header via `StoreApiNonce`, same as the existing `add()`).
     - [x] 3.2 `functions.php`: localize `cartUpdateItemUrl`/`cartRemoveItemUrl`; `registerAsset()` entries for the new JS files below (and add them to `vite.config.mjs`'s `rollupOptions.input` too — confirmed in Module 2 that `pnpm build` silently drops anything missing from that list).
     - [x] 3.3 `CartBadgeController` + wire `header.php`'s nav: replace the hardcoded `$cart_count = 0` stub with a real `items_count` from `CartRepository.getCart()`. Runs on every page (TailPress enqueues all registered assets globally — confirmed via `AssetManager::enqueueAssets()`).
     - [ ] 3.4 `page-cart.php` (WP's `page-{slug}.php` convention, like Module 1's Shop page) + `CartController` with loading/failed/empty states, mirroring `CatalogController`/`ProductController`.
     - [ ] 3.5 Cart line items view: per-item qty stepper (→ `updateItem`) and remove button (→ `removeItem`), reusing `item.images[0].thumbnail`, `item.name`, and the existing `wcPrice` filter on `item.totals.line_total`/`item.prices`.
     - [ ] 3.6 Empty-cart state (reuse the Catalog/Product-detail `.empty-state` pattern) + "Continue shopping" link back to `/shop/`.
     - [ ] 3.7 Summary card: Subtotal + Total from `cart.totals.total_items`/`total_price` (no shipping row — see decisions above) + "Continue to Checkout" link to `/checkout/`.
     - [ ] 3.8 CSS: port `.cart-line`, `.line-name`, `.line-unit`, `.line-total`, `.remove-btn`, `.summary`, `.summary-row`, `.two-col`, `.btn-dark` from the mockups' shared stylesheet into `storefront.css`.
   - **Module 4: Checkout** — shipping address + optional login/account-creation + Stripe card field, via the Store API's checkout flow (`/cart/update-customer` → `/cart/select-shipping-rate` → `/checkout`) + Stripe. Real money is involved, so this module is built directly against the "Payment & external-API guardrails" section above, not just the module table.
     - Decisions locked in: the payment gateway is **custom-built** (`StripePaymentGateway` + a Store API payment-method-type integration), not the official WooCommerce Stripe Gateway plugin — matches the Engineering guidelines above, which already name `StripePaymentGateway` as a class this project builds, and the Portfolio differentiators' rule against quietly falling back to an off-the-shelf install for the real engineering work. This backend logic (gateway, checkout/webhook handling) lives in a **new custom mu-plugin** (`web/app/mu-plugins/ecommerce/`), not the theme — payment logic isn't presentation code, and nothing else in this project puts business logic in `functions.php` beyond thin Store API URL bridging. Card capture uses **Stripe Elements/Payment Element**, replacing the mockup's literal card-number/expiry/CVC `<input>` fields with a single Stripe-mounted element — those raw inputs would violate the guardrail that card data never touches the WP server. Idempotency is two-layered: the Store API's own `Checkout` route already reuses a pending draft order tied to the session (`DraftOrderTrait`) rather than creating a new order per request, plus a client-side disable-on-submit on "Place Order" as a second guard.
     - [ ] 4.0 Scaffold: add `stripe/stripe-php` to `composer.json` (server-side SDK); create the `web/app/mu-plugins/ecommerce/` mu-plugin as the home for all checkout/payment PHP classes.
     - [ ] 4.1 `StripePaymentGateway` (`WC_Payment_Gateway` subclass) — registers as a payment method WooCommerce orders recognize; `process_payment()` verifies/captures the confirmed Stripe PaymentIntent and returns success/failure per WC's gateway contract.
     - [ ] 4.2 Store API payment-method-type integration (`AbstractPaymentMethodType` subclass, registered via `woocommerce_blocks_payment_method_type_registration`) so the Checkout route's Store API validation accepts this gateway's `payment_method` slug.
     - [ ] 4.3 Server-side PaymentIntent-creation endpoint (small custom REST route) using the cart total already available from Module 3's `CartRepository.getCart()`, giving the client a `client_secret` to mount Stripe Elements against.
     - [ ] 4.4 `functions.php`: load Stripe.js from Stripe's CDN, pinned/SRI'd the same way `ANGULARJS_VERSION`/`ANGULARJS_SRI_HASH` are — publishable key only; the secret key stays server-side in `.env`, used only by the mu-plugin's PHP (guardrail).
     - [ ] 4.5 `page-checkout.php` (WP's `page-{slug}.php` convention, WooCommerce's auto-created Checkout page) + `CheckoutController` — contact/shipping address fields matching the mockup's fieldset.
     - [ ] 4.6 `CartRepository.updateCustomer(address)` (new, `POST /cart/update-customer`) to submit the address and recalculate `shipping_rates`; `CartRepository.selectShippingRate(packageId, rateId)` (new) to pick one — this is also where the real shipping total Module 3 deliberately left out finally appears.
     - [ ] 4.7 Stripe Payment Element wiring: mount against the `client_secret` from 4.3, replacing the mockup's raw card inputs — reuse the mockup's `.card-icons` row and "processed securely by Stripe" note as static chrome around the mounted element.
     - [ ] 4.8 "Place Order": `stripe.confirmPayment()` client-side, then `CheckoutController.placeOrder()` → `POST /checkout` with billing/shipping address + `payment_method`/`payment_data` (the confirmed PaymentIntent id). Button disables on submit.
     - [ ] 4.9 Stripe webhook endpoint (custom REST route) verifying the Stripe signature against a webhook secret in `.env`, handling `payment_intent.succeeded` → `$order->payment_complete()` — satisfies the guardrail that order finalization can't depend on the shopper's browser staying connected.
     - [ ] 4.10 "Create an account with this order" checkbox — optional account creation off the order's billing email, reusing WP's cookie/nonce auth (no custom token layer, per the Stack section) — confirm exactly how the Store API checkout exposes this during implementation.
     - [ ] 4.11 CSS: port `.field`, `.field-row`, `.checkbox-row`, `.card-icons`, `.recap-item` from the mockups' shared stylesheet.
   - **Module 5: Order confirmation** — shows what was ordered and that it's paid, using only the response from Module 4's checkout call (per the module table — no separate Store API call).
     - Decisions locked in: rendered as a **state switch within `page-checkout.php`/`CheckoutController`** (`vm.orderComplete`), not a separate WP page/template — the simplest way to satisfy "talks to: response from checkout call" literally, since there's no client-side router to hand a full page navigation the same response object. Trade-off, stated rather than hidden: refreshing the confirmation view loses it and returns to an empty checkout form — the order itself isn't lost (WooCommerce has it, plus the confirmation email), only the on-screen recap is transient; accepted for this project's scope rather than adding session storage or a dedicated order-lookup endpoint to work around it. Cut: the mockup's confetti burst animation — falls squarely under the plan's own "extra shopper-facing wow factor" cut category above. Also cut: the mockup's estimated delivery date range — WooCommerce doesn't compute one and there's no real source for it, same reasoning as Module 2's spec-list cut. The order-confirmation email is WooCommerce's own built-in behavior — nothing to build, just confirm it's not disabled.
     - [ ] 5.0 Extend `CheckoutController`: on a successful `POST /checkout` response, set `vm.orderComplete = true`/`vm.order = response` instead of navigating away; `page-checkout.php` conditionally renders the form or the confirmation view from that same state.
     - [ ] 5.1 Confirmation view: order recap (line items + total paid) from `vm.order`.
     - [ ] 5.2 Shipping address recap line from `vm.order.shipping_address`.
     - [ ] 5.3 "Continue Shopping" link back to `/shop/`.
     - [ ] 5.4 CSS: port `.confirm-wrap`, `.check-circle`, `.order-recap`, `.confirm-actions` from the mockups' shared stylesheet (skip `.confetti` — cut above).
     - [ ] 5.5 Live verification: confirm WooCommerce's order-confirmation email actually fires on a completed order.
5. Add the Pest feature tests and the README case-study section.
