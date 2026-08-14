<?php
/**
 * Module 4: Checkout — WooCommerce's auto-created Checkout page (slug
 * "checkout"). Same `page-{slug}.php` convention as page-shop.php/page-cart.php.
 *
 * Items 4.0-4.5: address form (any country, not just the store's base
 * country — see checkout-controller.js), real shipping rate, optional
 * account creation, and placing the order. Payment is still the Cash on
 * Delivery stand-in (see docs/PLAN.md's build-sequence decision) — real
 * Stripe is its own dedicated step.
 *
 * Module 5 (5.0-5.2): on a successful order, this same template swaps in
 * the confirmation view (order recap + shipping address), reading
 * vm.orderPlaced/vm.order/vm.cart from checkout-controller.js — no separate
 * page/template, no client-side router. "Continue Shopping" action + CSS are
 * 5.3/5.4, a follow-up pass — the confirmation view currently renders
 * unstyled aside from the .recap-item rules already ported for the summary
 * panel above.
 *
 * @package TailPress
 */

get_header();
?>

<section class="wrap" ng-controller="CheckoutController as vm">
    <div style="padding-top:36px;">
        <h1><?php esc_html_e('Checkout', 'tailpress'); ?></h1>
    </div>

    <p class="empty-state" ng-if="vm.loading"><?php esc_html_e('Loading checkout…', 'tailpress'); ?></p>
    <p class="empty-state" ng-if="!vm.loading && vm.failed"><?php esc_html_e("Couldn't load your cart right now — please try again shortly.", 'tailpress'); ?></p>

    <!-- Module 5 (5.0-5.2): confirmation view, reading vm.order (order
         identity + shipping_address — the checkout POST response carries no
         line-items/totals) and vm.cart (still holding the checked-out items
         since nothing clears/re-fetches it after a successful order). CSS
         for .confirm-wrap/.check-circle/.order-recap/.recap-addr and the
         "Continue Shopping" action are 5.3/5.4, a follow-up pass. -->
    <div class="confirm-wrap" ng-if="vm.orderPlaced">
        <div class="check-circle">
            <svg viewBox="0 0 24 24"><path d="M4 12.5 9.5 18 20 6"/></svg>
        </div>
        <span class="eyebrow">
            <?php
            /* translators: %s is the order number. */
            echo esc_html(sprintf(__('Order #%s', 'tailpress'), '{{ vm.order.order_number }}'));
            ?>
        </span>
        <h1><?php esc_html_e("You're all set.", 'tailpress'); ?></h1>

        <div class="order-recap">
            <div class="recap-item" ng-repeat="item in vm.cart.items track by item.key">
                <span>{{ item.name }} &times; {{ item.quantity }}</span>
                <span class="mono">{{ item.totals.line_total | wcPrice:item.prices }}</span>
            </div>
            <div class="recap-item" style="font-weight:700;border-top:1px solid var(--border);margin-top:6px;padding-top:14px;">
                <span><?php esc_html_e('Total paid', 'tailpress'); ?></span>
                <span class="mono">{{ vm.cart.totals.total_price | wcPrice:vm.cart.totals }}</span>
            </div>
            <!-- 5.2: literal text + raw Angular interpolation kept adjacent
                 rather than fused via sprintf()/esc_html() — same convention
                 as page-cart.php's "{{ ... }} <?php esc_html_e('each') ?>"
                 — the ternary below needs literal quote characters Angular
                 can parse, which esc_html() would otherwise HTML-entity-encode. -->
            <div class="recap-addr">
                <?php esc_html_e('Shipping to', 'tailpress'); ?>
                {{ vm.order.shipping_address.first_name }} {{ vm.order.shipping_address.last_name }} &middot;
                {{ vm.order.shipping_address.address_1 }}{{ vm.order.shipping_address.address_2 ? ', ' + vm.order.shipping_address.address_2 : '' }} &middot;
                {{ vm.order.shipping_address.city }}, {{ vm.order.shipping_address.state }} {{ vm.order.shipping_address.postcode }} &middot;
                {{ vm.countries[vm.order.shipping_address.country] || vm.order.shipping_address.country }}
            </div>
        </div>
    </div>

    <div class="two-col" ng-if="!vm.loading && !vm.failed && !vm.orderPlaced">
        <form name="checkoutForm" ng-submit="vm.submitAddress()">
            <fieldset>
                <legend><?php esc_html_e('Contact & shipping', 'tailpress'); ?></legend>

                <div class="field">
                    <label for="email"><?php esc_html_e('Email', 'tailpress'); ?></label>
                    <input id="email" name="email" type="email" ng-model="vm.address.email" required>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="first_name"><?php esc_html_e('First name', 'tailpress'); ?></label>
                        <input id="first_name" name="first_name" type="text" ng-model="vm.address.first_name" required>
                    </div>
                    <div class="field">
                        <label for="last_name"><?php esc_html_e('Last name', 'tailpress'); ?></label>
                        <input id="last_name" name="last_name" type="text" ng-model="vm.address.last_name" required>
                    </div>
                </div>
                <div class="field">
                    <label for="address_1"><?php esc_html_e('Address', 'tailpress'); ?></label>
                    <input id="address_1" name="address_1" type="text" ng-model="vm.address.address_1" required>
                </div>
                <div class="field">
                    <label for="country"><?php esc_html_e('Country', 'tailpress'); ?></label>
                    <select id="country" name="country" ng-model="vm.address.country" ng-options="code as name for (code, name) in vm.countries" required>
                        <option value=""><?php esc_html_e('Select a country', 'tailpress'); ?></option>
                    </select>
                </div>
                <div class="field-row three">
                    <div class="field">
                        <label for="city"><?php esc_html_e('City', 'tailpress'); ?></label>
                        <input id="city" name="city" type="text" ng-model="vm.address.city" required>
                    </div>
                    <div class="field">
                        <label for="state"><?php esc_html_e('State', 'tailpress'); ?></label>
                        <input id="state" name="state" type="text" ng-model="vm.address.state" required>
                    </div>
                    <div class="field">
                        <label for="postcode"><?php esc_html_e('ZIP', 'tailpress'); ?></label>
                        <input id="postcode" name="postcode" type="text" ng-model="vm.address.postcode" required>
                    </div>
                </div>
                <div class="field">
                    <label for="phone"><?php esc_html_e('Phone (optional)', 'tailpress'); ?></label>
                    <input id="phone" name="phone" type="tel" ng-model="vm.address.phone">
                </div>
                <div class="checkbox-row">
                    <input id="create_account" type="checkbox" ng-model="vm.createAccount">
                    <label for="create_account"><?php esc_html_e('Create an account with this order — track future orders faster. Not required.', 'tailpress'); ?></label>
                </div>

                <button class="btn btn-cta" type="submit" ng-disabled="vm.submittingAddress || checkoutForm.$invalid">
                    <span ng-if="!vm.submittingAddress && !vm.addressConfirmed"><?php esc_html_e('Continue', 'tailpress'); ?></span>
                    <span ng-if="vm.submittingAddress"><?php esc_html_e('Saving…', 'tailpress'); ?></span>
                    <span ng-if="!vm.submittingAddress && vm.addressConfirmed"><?php esc_html_e('Saved ✓', 'tailpress'); ?></span>
                </button>
                <p class="micro-note" ng-if="vm.addressFailed">{{ vm.addressFailedMessage }}</p>
            </fieldset>
        </form>

        <div class="summary">
            <div class="summary-row"><span><?php esc_html_e('Subtotal', 'tailpress'); ?></span><span class="mono">{{ vm.cart.totals.total_items | wcPrice:vm.cart.totals }}</span></div>
            <div class="summary-row"><span><?php esc_html_e('Shipping', 'tailpress'); ?></span><span class="mono">{{ vm.addressConfirmed ? (vm.cart.totals.total_shipping | wcPrice:vm.cart.totals) : '—' }}</span></div>
            <div class="summary-row total"><span><?php esc_html_e('Total', 'tailpress'); ?></span><span class="mono">{{ vm.cart.totals.total_price | wcPrice:vm.cart.totals }}</span></div>

            <!-- 4.3: only appears once a shipping rate is confirmed (4.0-4.2) -->
            <button class="btn btn-dark btn-block" type="button" ng-if="vm.addressConfirmed" ng-click="vm.placeOrder()" ng-disabled="vm.placingOrder" style="margin-top:20px;">
                <span ng-if="!vm.placingOrder"><?php esc_html_e('Place Order', 'tailpress'); ?> — {{ vm.cart.totals.total_price | wcPrice:vm.cart.totals }}</span>
                <span ng-if="vm.placingOrder"><?php esc_html_e('Placing order…', 'tailpress'); ?></span>
            </button>
            <p class="micro-note" ng-if="vm.orderFailed">{{ vm.orderFailedMessage }}</p>
        </div>
    </div>
</section>

<?php
get_footer();
