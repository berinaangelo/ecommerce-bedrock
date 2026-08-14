<?php
/**
 * Module 4: Checkout — WooCommerce's auto-created Checkout page (slug
 * "checkout"). Same `page-{slug}.php` convention as page-shop.php/page-cart.php.
 *
 * This slice (items 4.0-4.2) covers the address form and getting a real
 * shipping rate back — it stops there. Payment is a Cash on Delivery
 * stand-in for now (see docs/PLAN.md's build-sequence decision) and isn't
 * wired up yet, so there's no "Place Order" action on this page until that
 * lands.
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

    <div class="two-col" ng-if="!vm.loading && !vm.failed">
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

                <button class="btn btn-cta" type="submit" ng-disabled="vm.submittingAddress || checkoutForm.$invalid">
                    <span ng-if="!vm.submittingAddress && !vm.addressConfirmed"><?php esc_html_e('Continue', 'tailpress'); ?></span>
                    <span ng-if="vm.submittingAddress"><?php esc_html_e('Saving…', 'tailpress'); ?></span>
                    <span ng-if="!vm.submittingAddress && vm.addressConfirmed"><?php esc_html_e('Saved ✓', 'tailpress'); ?></span>
                </button>
                <p class="micro-note" ng-if="vm.addressFailed"><?php esc_html_e("Couldn't save your address — please try again.", 'tailpress'); ?></p>
            </fieldset>
        </form>

        <div class="summary">
            <div class="summary-row"><span><?php esc_html_e('Subtotal', 'tailpress'); ?></span><span class="mono">{{ vm.cart.totals.total_items | wcPrice:vm.cart.totals }}</span></div>
            <div class="summary-row"><span><?php esc_html_e('Shipping', 'tailpress'); ?></span><span class="mono">{{ vm.addressConfirmed ? (vm.cart.totals.total_shipping | wcPrice:vm.cart.totals) : '—' }}</span></div>
            <div class="summary-row total"><span><?php esc_html_e('Total', 'tailpress'); ?></span><span class="mono">{{ vm.cart.totals.total_price | wcPrice:vm.cart.totals }}</span></div>
        </div>
    </div>
</section>

<?php
get_footer();
