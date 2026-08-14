<?php
/**
 * Module 2: Product detail — WP's own template-hierarchy name for the
 * `product` CPT (checked before generic single.php).
 *
 * Unlike page-shop.php there's no WP Page to attach a page-{slug}.php
 * template to here — every product is a `product` post, so this file is
 * WordPress's own convention for it. Same shape as page-shop.php otherwise:
 * a thin shell around ng-controller, no WP_Query/the_content() — all product
 * data comes from the Store API client-side, scoped to this page's product
 * id (localized in functions.php, since there's no client-side router).
 *
 * @package TailPress
 */

get_header();
?>

<section class="wrap" ng-controller="ProductController as vm">
    <div style="padding-top:26px;">
        <a class="back-link" href="<?php echo esc_url(home_url('/shop/')); ?>">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
            <?php esc_html_e('Back to shop', 'tailpress'); ?>
        </a>
    </div>

    <p class="empty-state" ng-if="vm.loading"><?php esc_html_e('Loading product…', 'tailpress'); ?></p>
    <p class="empty-state" ng-if="!vm.loading && vm.failed"><?php esc_html_e("Couldn't load this product right now — please try again shortly.", 'tailpress'); ?></p>
    <p class="empty-state" ng-if="!vm.loading && !vm.failed && !vm.product"><?php esc_html_e("This product couldn't be found.", 'tailpress'); ?></p>

    <div class="product-grid" ng-if="!vm.loading && !vm.failed && vm.product">
        <div class="product-tile" ng-class="{'is-soldout': !vm.product.is_in_stock}">
            <span class="badge-sale" ng-if="vm.product.on_sale"><?php esc_html_e('Sale', 'tailpress'); ?></span>
            <span class="badge-stock" ng-if="!vm.product.is_in_stock"><?php esc_html_e('Sold out', 'tailpress'); ?></span>
            <img ng-if="vm.product.images.length" ng-src="{{ vm.product.images[0].src }}" alt="{{ vm.product.images[0].alt || vm.product.name }}">
        </div>

        <div>
            <span class="eyebrow" ng-if="vm.product.categories.length">{{ vm.product.categories[0].name }}</span>
            <h1>{{ vm.product.name }}</h1>

            <div class="price-row" style="margin-top:16px;">
                <span class="price mono">{{ vm.product.prices.price | wcPrice:vm.product.prices }}</span>
                <span class="price-was mono" ng-if="vm.product.on_sale">{{ vm.product.prices.regular_price | wcPrice:vm.product.prices }}</span>
            </div>

            <p style="margin-top:18px;color:var(--ink-muted);max-width:52ch;">{{ vm.description }}</p>

            <div style="display:flex;align-items:center;gap:16px;margin-top:28px;" ng-if="vm.product.is_in_stock">
                <div class="qty">
                    <button type="button" ng-click="vm.decreaseQuantity()" aria-label="<?php esc_attr_e('Decrease quantity', 'tailpress'); ?>">&minus;</button>
                    <span class="mono">{{ vm.quantity }}</span>
                    <button type="button" ng-click="vm.increaseQuantity()" aria-label="<?php esc_attr_e('Increase quantity', 'tailpress'); ?>">+</button>
                </div>
                <button class="btn btn-cta" type="button" ng-click="vm.addToCart()" ng-disabled="vm.adding">
                    <span ng-if="!vm.adding && !vm.added"><?php esc_html_e('Add to Cart', 'tailpress'); ?></span>
                    <span ng-if="vm.adding"><?php esc_html_e('Adding…', 'tailpress'); ?></span>
                    <span ng-if="!vm.adding && vm.added"><?php esc_html_e('Added ✓', 'tailpress'); ?></span>
                </button>
            </div>
            <p class="empty-state" style="padding-block:0;text-align:left;" ng-if="!vm.product.is_in_stock"><?php esc_html_e('Out of stock.', 'tailpress'); ?></p>
            <p class="micro-note" ng-if="vm.addFailed"><?php esc_html_e("Couldn't add this to your cart — please try again.", 'tailpress'); ?></p>
        </div>
    </div>
</section>

<?php
get_footer();
