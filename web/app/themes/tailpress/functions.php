<?php

if (is_file(__DIR__ . '/vendor/autoload_packages.php')) {
    require_once __DIR__ . '/vendor/autoload_packages.php';
}

// AngularJS 1.x, CDN-loaded (deliberately not an npm/pnpm dependency for now —
// see docs/PLAN.md). Pinned to the last stable 1.x release with a matching SRI
// hash, since the version won't move.
const ANGULARJS_VERSION = '1.8.3';
const ANGULARJS_SRI_HASH = 'sha384-quGekMf1ic6tIOn1GgLl0Pzra4ZkFyTcaDW3hZRjORqPQe3HnTKGl+lNPpuh7Lwv';

// PayMongo public key (Module 4 step 5 — see docs/PLAN.md 4.6-4.13). Same
// "pin it once at the top" spirit as ANGULARJS_VERSION above, but this one
// is environment-dependent so it's define()'d from .env rather than a
// literal const. Public key only — the secret key (PAYMONGO_SECRET_KEY) is
// never read in this file and never reaches the client; it's only used by
// PayMongoClient inside the mu-plugin.
define('PAYMONGO_PUBLIC_KEY', (string) \Env\env('PAYMONGO_PUBLIC_KEY'));

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'angularjs',
        "https://cdn.jsdelivr.net/npm/angular@" . ANGULARJS_VERSION . "/angular.min.js",
        [],
        ANGULARJS_VERSION,
        true, // footer, matches how the theme's own Vite assets load
    );
});

add_filter('script_loader_tag', function ($tag, $handle) {
    if ($handle !== 'angularjs') {
        return $tag;
    }

    return str_replace(' src=', ' integrity="' . ANGULARJS_SRI_HASH . '" crossorigin="anonymous" src=', $tag);
}, 10, 2);

// Storefront design system fonts (Anton/Manrope/JetBrains Mono), CDN-loaded
// like AngularJS above rather than vendored — see docs/mockups for the
// design these back.
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'storefront-fonts',
        'https://fonts.googleapis.com/css2?family=Anton&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap',
        [],
        null,
    );
});

// Checkout's country <select> (wc-select2-directive.js) reuses WooCommerce's
// own selectWoo library instead of a second select2 copy — WC registers
// both unconditionally, so depending on the handles is reliable, but only
// conditionally enqueues them (is_cart()/is_checkout()/is_account_page()) —
// enqueued explicitly here rather than relying on that condition to line up
// with this page.
add_action('wp_enqueue_scripts', function () {
    if (is_checkout()) {
        wp_enqueue_style('select2');
    }
});

// Bridges the WooCommerce Store API base URL to the Angular app — the
// frontend never hardcodes it, and the Store API is public/no-key by
// design (see docs/PLAN.md), so nothing sensitive is being exposed here.
//
// Priority 20 (not the default 10): wp_localize_script() silently no-ops if
// its target handle isn't registered yet, and TailPress's own asset
// registration (tailpress() below, which creates the
// 'tailpress-product-repository' handle) runs on this same hook at the
// default priority 10 — this has to run after it.
add_action('wp_enqueue_scripts', function () {
    wp_localize_script('tailpress-product-repository', 'ecommerceStoreApi', [
        'productsUrl' => rest_url('wc/store/v1/products'),
        'cartUrl' => rest_url('wc/store/v1/cart'),
        'cartAddItemUrl' => rest_url('wc/store/v1/cart/add-item'),
        'cartUpdateItemUrl' => rest_url('wc/store/v1/cart/update-item'),
        'cartRemoveItemUrl' => rest_url('wc/store/v1/cart/remove-item'),
        // Module 4: Checkout.
        'cartUpdateCustomerUrl' => rest_url('wc/store/v1/cart/update-customer'),
        'cartSelectShippingRateUrl' => rest_url('wc/store/v1/cart/select-shipping-rate'),
        'checkoutUrl' => rest_url('wc/store/v1/checkout'),
        // Module 2: Product detail. Only set on a single-product page — the
        // id Angular fetches, since there's no client-side router to derive
        // it from the URL (see single-product.php).
        'productId' => is_singular('product') ? get_the_ID() : null,
        // Module 4: Checkout. The Store API has no countries endpoint, so
        // this is bridged the same way as everything else here — code =>
        // name, straight from WooCommerce's own list (WC()->countries),
        // rather than hardcoding a single country in the Angular app.
        'countries' => WC()->countries->get_countries(),
        // Module 4 step 5: PayMongo (4.9/4.10). Public key only.
        'paymongoIntentUrl' => rest_url('ecommerce/v1/paymongo/intent'),
        'paymongoPublicKey' => PAYMONGO_PUBLIC_KEY,
    ]);
}, 20);

function tailpress(): TailPress\Framework\Theme
{
    return TailPress\Framework\Theme::instance()
        ->assets(
            fn($manager) => $manager
            ->withCompiler(
                new TailPress\Framework\Assets\ViteCompiler(),
                fn($compiler) => $compiler
                ->registerAsset('resources/css/app.css')
                ->registerAsset('resources/js/app.js')
                ->registerAsset('resources/js/angular-app.js', ['angularjs'])
                ->registerAsset('resources/js/services/product-repository.js', ['angularjs', 'tailpress-angular-app'])
                ->registerAsset('resources/js/services/price-formatter.js', ['angularjs', 'tailpress-angular-app'])
                ->registerAsset('resources/js/services/store-api-nonce.js', ['angularjs', 'tailpress-angular-app'])
                ->registerAsset('resources/js/services/cart-repository.js', ['angularjs', 'tailpress-angular-app', 'tailpress-store-api-nonce'])
                ->registerAsset('resources/js/controllers/catalog-controller.js', ['angularjs', 'tailpress-angular-app', 'tailpress-product-repository', 'tailpress-price-formatter'])
                ->registerAsset('resources/js/controllers/product-controller.js', ['angularjs', 'tailpress-angular-app', 'tailpress-product-repository', 'tailpress-cart-repository', 'tailpress-price-formatter'])
                ->registerAsset('resources/js/controllers/cart-badge-controller.js', ['angularjs', 'tailpress-angular-app', 'tailpress-cart-repository'])
                ->registerAsset('resources/js/controllers/cart-controller.js', ['angularjs', 'tailpress-angular-app', 'tailpress-cart-repository', 'tailpress-price-formatter'])
                ->registerAsset('resources/js/controllers/checkout-controller.js', ['angularjs', 'tailpress-angular-app', 'tailpress-cart-repository', 'tailpress-price-formatter'])
                ->registerAsset('resources/js/directives/wc-select2-directive.js', ['jquery', 'selectWoo', 'angularjs', 'tailpress-angular-app'])
                ->editorStyleFile('resources/css/editor-style.css'),
            )
            ->enqueueAssets(),
        )
        ->features(fn($manager) => $manager->add(TailPress\Framework\Features\MenuOptions::class))
        ->menus(fn($manager) => $manager->add('primary', 'Primary Menu'))
        ->themeSupport(fn($manager) => $manager->add([
            'title-tag',
            'custom-logo',
            'post-thumbnails',
            'align-wide',
            'wp-block-styles',
            'responsive-embeds',
            'html5' => [
                'search-form',
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
            ],
        ]));
}

tailpress();
