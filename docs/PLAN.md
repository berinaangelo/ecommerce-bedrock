# Plan

_Last updated: 2026-08-14_

## One-sentence description

A shopper picks products, checks out with an address and card, and gets their order shipped.

## Stack

- **Backend**: WordPress via [Bedrock](https://roots.io/bedrock/) (Composer-managed, env-based config) + WooCommerce, added to `composer.json`. WooCommerce's REST API (`/wc/v3/...`) and Store API (`/wc/store/v1/...`) are the only backend surface the frontend talks to.
- **Theme**: [TailPress](https://tailpress.io/) — plain Underscores-style WP theme with Tailwind CSS/PostCSS, no Blade/Acorn layer. Chosen over Sage (also Roots, pairs with Bedrock) for being lighter — fewer moving parts to wire AngularJS into.
- **Frontend**: AngularJS 1.x, embedded into the TailPress theme's build (not a separately hosted SPA) — enqueued as the theme's JS/CSS bundle, same-origin, no CORS to manage.
  - Note: AngularJS is EOL (no security patches since Jan 2022). Kept deliberately per explicit choice — see "Portfolio differentiators" below for how that's framed rather than hidden.
- **Architecture**: embedded/hybrid, not fully headless. WordPress renders the page shell; Angular takes over the storefront regions and calls the WooCommerce REST/Store API.

## Scope decisions (locked in)

- **Products**: physical goods only — checkout needs a shipping address and shipping cost/method.
- **Accounts**: guest checkout by default, with an optional "create an account with this order" checkbox at checkout. No separate login flow is in scope yet — **open question**: does this also need a way to log into an *existing* account before checkout? Not yet decided.
- **Payment**: single processor — Stripe. No payment-method picker.
- **Catalog**: simple products only, no variants (size/color/etc).

## Core flow / modules

Each module is a step of the one shopper story — nothing runs in parallel to it, and each is fully demoable on its own with no "coming later" caveat.

| # | Module | What it does | Talks to |
|---|--------|--------------|----------|
| 1 | **Catalog** | Lists products (image, name, price) | `GET /wc/v3/products` |
| 2 | **Product detail** | Shows one product, "Add to cart" | `GET /wc/v3/products/{id}`, add-to-cart |
| 3 | **Cart** | View items, change qty, remove, see total | WC Store API `/wc/store/v1/cart` |
| 4 | **Checkout** | Shipping address + optional account-creation checkbox + Stripe card field | WC Store API checkout endpoint + Stripe |
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

## Next steps

1. Resolve the open account/login question above.
2. ~~Scaffold: set up `.env`/DB config.~~ Done — `bin/setup.sh` creates the MySQL database + app user, writes `.env`, and runs the WordPress install via WP-CLI (`wp-cli/wp-cli-bundle`, added as a Composer dev dependency). Run `./bin/setup.sh` from repo root. Still open: pull in TailPress as the active theme, add WooCommerce to `composer.json`.
3. Wire AngularJS into the TailPress build pipeline alongside Tailwind.
4. Build modules 1–5 in order against the WC REST/Store API.
5. Add the Pest feature tests and the README case-study section.
