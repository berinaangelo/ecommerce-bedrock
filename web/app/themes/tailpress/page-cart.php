<?php
/**
 * Module 3: Cart — WooCommerce's auto-created Cart page (slug "cart").
 *
 * A plain WP `page-{slug}.php` template, same convention as page-shop.php —
 * the theme has no `add_theme_support('woocommerce')`, so WordPress's own
 * template hierarchy applies. Line items, totals, and every mutation come
 * from the Store API via CartController; this stays a thin shell.
 *
 * Shipping row: `total_shipping` is non-null as soon as a shipping method
 * exists on a matching zone (Module 4 configured a flat-rate method on the
 * default catch-all zone, which matches every destination) — WooCommerce
 * defaults the customer to the store's base country/state before any address
 * is entered, so this is populated from the very start of a cart session,
 * not only after Module 4's address step. Falls back to '—' for the (now
 * unlikely) case it's still null. "Continue to Checkout" links to /checkout/
 * and is left inert until Module 4's "Place Order" exists.
 *
 * @package TailPress
 */

get_header();
?>

<section class="wrap" ng-controller="CartController as vm">
    <div style="padding-top:36px;">
        <h1><?php esc_html_e('Your Cart', 'tailpress'); ?></h1>
    </div>

    <p class="empty-state" ng-if="vm.loading"><?php esc_html_e('Loading cart…', 'tailpress'); ?></p>
    <p class="empty-state" ng-if="!vm.loading && vm.failed"><?php esc_html_e("Couldn't load your cart right now — please try again shortly.", 'tailpress'); ?></p>

    <div ng-if="!vm.loading && !vm.failed && !vm.cart.items.length" style="text-align:center;padding-block:70px;">
        <p class="empty-state" style="padding-block:0;"><?php esc_html_e('Your cart is empty.', 'tailpress'); ?></p>
        <a class="back-link" style="margin-top:16px;display:inline-flex;justify-content:center;" href="<?php echo esc_url(home_url('/shop/')); ?>">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
            <?php esc_html_e('Continue shopping', 'tailpress'); ?>
        </a>
    </div>

    <div class="two-col" ng-if="!vm.loading && !vm.failed && vm.cart.items.length">
        <div>
            <div class="cart-line" ng-repeat="item in vm.cart.items track by item.key">
                <div class="tile">
                    <img ng-if="item.images.length" ng-src="{{ item.images[0].thumbnail }}" alt="{{ item.images[0].alt || item.name }}">
                </div>
                <div>
                    <div class="line-name">{{ item.name }}</div>
                    <div class="line-unit mono">{{ item.prices.price | wcPrice:item.prices }} <?php esc_html_e('each', 'tailpress'); ?></div>
                </div>
                <div class="qty">
                    <button type="button" ng-click="vm.updateQuantity(item, -1)" ng-disabled="vm.isUpdating(item.key)" aria-label="<?php esc_attr_e('Decrease quantity', 'tailpress'); ?>">&minus;</button>
                    <span class="mono">{{ item.quantity }}</span>
                    <button type="button" ng-click="vm.updateQuantity(item, 1)" ng-disabled="vm.isUpdating(item.key)" aria-label="<?php esc_attr_e('Increase quantity', 'tailpress'); ?>">+</button>
                </div>
                <div style="display:flex;align-items:center;gap:14px;">
                    <span class="line-total mono">{{ item.totals.line_total | wcPrice:item.prices }}</span>
                    <button class="remove-btn" type="button" ng-click="vm.removeItem(item)" ng-disabled="vm.isUpdating(item.key)" ng-attr-aria-label="{{ 'Remove ' + item.name }}">
                        <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <a class="back-link" style="margin-top:24px;display:inline-flex;" href="<?php echo esc_url(home_url('/shop/')); ?>">
                <svg viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                <?php esc_html_e('Continue shopping', 'tailpress'); ?>
            </a>

            <p class="micro-note" ng-if="vm.actionFailed"><?php esc_html_e("Couldn't update your cart — please try again.", 'tailpress'); ?></p>
        </div>

        <div class="summary">
            <div class="summary-row"><span><?php esc_html_e('Subtotal', 'tailpress'); ?></span><span class="mono">{{ vm.cart.totals.total_items | wcPrice:vm.cart.totals }}</span></div>
            <div class="summary-row"><span><?php esc_html_e('Shipping', 'tailpress'); ?></span><span class="mono">{{ vm.cart.totals.total_shipping == null ? '—' : (vm.cart.totals.total_shipping | wcPrice:vm.cart.totals) }}</span></div>
            <div class="summary-row total"><span><?php esc_html_e('Total', 'tailpress'); ?></span><span class="mono">{{ vm.cart.totals.total_price | wcPrice:vm.cart.totals }}</span></div>
            <a class="btn btn-dark btn-block" href="<?php echo esc_url(home_url('/checkout/')); ?>" style="margin-top:20px;"><?php esc_html_e('Continue to Checkout', 'tailpress'); ?></a>
        </div>
    </div>
</section>

<?php
get_footer();
