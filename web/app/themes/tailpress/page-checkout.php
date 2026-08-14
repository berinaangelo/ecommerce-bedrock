<?php
/**
 * Module 4: Checkout — WooCommerce's auto-created Checkout page (slug
 * "checkout"). Same `page-{slug}.php` convention as page-shop.php/page-cart.php.
 *
 * Items 4.0-4.5: address form (any country, not just the store's base
 * country — see checkout-controller.js), real shipping rate, optional
 * account creation, and placing the order. Payment is still the Cash on
 * Delivery stand-in (see docs/PLAN.md's build-sequence decision) — real
 * Stripe is its own dedicated step. On success this renders a minimal
 * one-line confirmation stand-in, not the mockup's full recap view — that's
 * Module 5.
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

    <!-- 4.3: stand-in for the real confirmation — Module 5 replaces this
         with the mockup's full recap view, reading this same vm.order state. -->
    <div class="empty-state" ng-if="vm.orderPlaced">
        <?php
        /* translators: %s is the order number. */
        echo esc_html(sprintf(__('Order #%s placed!', 'tailpress'), '{{ vm.order.order_number }}'));
        ?>
        <a class="back-link" href="<?php echo esc_url(home_url('/shop/')); ?>"><?php esc_html_e('Continue shopping', 'tailpress'); ?></a>
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
