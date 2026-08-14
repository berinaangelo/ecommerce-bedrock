<?php
/**
 * Module 1: Catalog — WooCommerce's auto-created Shop page (slug "shop").
 *
 * A plain WP `page-{slug}.php` template, not `woocommerce/archive-product.php`
 * — the theme never declares `add_theme_support('woocommerce')`, so
 * WooCommerce's own template loader steps aside for this page and normal WP
 * template hierarchy applies (see docs/PLAN.md's Module 1 notes). The product
 * list itself comes from the Store API via CatalogController, not this
 * template or a WP_Query — it stays a thin shell around ng-controller.
 *
 * @package TailPress
 */

get_header();
?>

<section class="thesis wrap">
    <span class="eyebrow"><?php esc_html_e('Shop', 'tailpress'); ?></span>
    <h1><?php esc_html_e('Gear that gets noticed.', 'tailpress'); ?></h1>
    <p><?php esc_html_e('Everyday carry and audio, built loud on the outside and precise on the inside.', 'tailpress'); ?></p>
</section>

<section class="wrap" ng-controller="CatalogController as vm">
    <p class="empty-state" ng-if="vm.loading"><?php esc_html_e('Loading products…', 'tailpress'); ?></p>
    <p class="empty-state" ng-if="!vm.loading && vm.failed"><?php esc_html_e("Couldn't load products right now — please try again shortly.", 'tailpress'); ?></p>
    <p class="empty-state" ng-if="!vm.loading && !vm.failed && !vm.products.length"><?php esc_html_e('No products yet — check back soon.', 'tailpress'); ?></p>

    <div class="grid" ng-if="!vm.loading && !vm.failed && vm.products.length">
        <article class="card" ng-repeat="product in vm.products" ng-class="{'is-soldout': !product.is_in_stock}">
            <a class="card-link" ng-href="{{ product.permalink }}" aria-label="{{ 'View ' + product.name }}"></a>
            <span class="badge-sale" ng-if="product.on_sale"><?php esc_html_e('Sale', 'tailpress'); ?></span>
            <span class="badge-stock" ng-if="!product.is_in_stock"><?php esc_html_e('Sold out', 'tailpress'); ?></span>

            <div class="tile">
                <img ng-if="product.images.length" ng-src="{{ product.images[0].thumbnail }}" alt="{{ product.images[0].alt || product.name }}">
            </div>

            <div class="card-body">
                <div class="card-top">
                    <div>
                        <span class="card-name">{{ product.name }}</span>
                        <span class="card-cat" ng-if="product.categories.length">{{ product.categories[0].name }}</span>
                    </div>
                    <!-- Module 2: Product detail. The click-through itself is
                         .card-link above (whole-card hit target, matching
                         docs/mockups/index.html); this arrow stays decorative. -->
                    <span class="card-arrow"><svg viewBox="0 0 24 24"><path d="M7 17 17 7M9 7h8v8"/></svg></span>
                </div>
                <div class="price-row">
                    <span class="price mono">{{ product.prices.price | wcPrice:product.prices }}</span>
                    <span class="price-was mono" ng-if="product.on_sale">{{ product.prices.regular_price | wcPrice:product.prices }}</span>
                </div>
            </div>
        </article>
    </div>
</section>

<?php
get_footer();
