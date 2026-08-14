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
   - **Module 2: Product detail** — not yet broken down.
   - **Module 3: Cart** — not yet broken down.
   - **Module 4: Checkout** — not yet broken down.
   - **Module 5: Order confirmation** — not yet broken down.
5. Add the Pest feature tests and the README case-study section.
