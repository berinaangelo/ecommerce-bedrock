// Module 4: Checkout. Loads the cart on init (for the summary panel, same as
// CartController) and owns the address form. Country is a real field (any
// WooCommerce-recognized country, bridged in via ecommerceStoreApi.countries
// — see functions.php) rather than fixed to the store's own base country —
// shipping is unrestricted (the default catch-all zone matches any
// destination) and WooCommerce's own address validation already adapts
// per-country (state names/codes, postcode format), so there's no extra
// validation logic needed here beyond letting the shopper pick one.
//
// Payment is still the Cash on Delivery stand-in (see docs/PLAN.md's
// build-sequence decision) — real Stripe is its own dedicated step. On a
// successful order, this controller only sets vm.orderPlaced/vm.order and
// page-checkout.php renders a minimal one-line stand-in for the confirmation
// — the real recap view (Module 5) is built against that same state next.
angular.module('ecommerceApp').controller('CheckoutController', ['CartRepository', function (CartRepository) {
    var vm = this;

    vm.loading = true;
    vm.failed = false;
    vm.cart = null;

    // Bridged in from PHP (WC()->countries->get_countries()) — code => name.
    vm.countries = window.ecommerceStoreApi.countries;

    vm.address = {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        address_1: '',
        city: '',
        state: '',
        postcode: '',
        // null, not '' — ngOptions only recognizes the literal
        // <option value=""> in page-checkout.php as the "nothing selected"
        // placeholder when the model is null/undefined (checked via ==).
        // An empty string doesn't match any option (including that literal
        // one), so ngOptions falls back to synthesizing its own blank
        // unknown-value option instead — which is what selectWoo was
        // rendering with no visible placeholder text.
        country: null
    };

    vm.submittingAddress = false;
    vm.addressFailed = false;
    vm.addressFailedMessage = '';
    vm.addressConfirmed = false;

    // 4.4: optional account creation, off the order's billing email — not
    // required, guest checkout always remains available (see docs/PLAN.md's
    // scope decisions).
    vm.createAccount = false;

    vm.placingOrder = false;
    vm.orderFailed = false;
    vm.orderFailedMessage = '';
    vm.orderPlaced = false;
    vm.order = null;

    CartRepository.getCart().then(function (result) {
        vm.cart = result.cart;
        vm.failed = result.failed;
        vm.loading = false;
    });

    // Exactly one shipping method is configured (a single flat rate) — no
    // rate-picker UI needed, same "no variant/picker" simplicity as the rest
    // of the storefront. Falls through to the failure state if a package or
    // rate is unexpectedly missing rather than assuming the happy path.
    function selectFirstShippingRate() {
        var firstPackage = vm.cart.shipping_rates[0];
        var firstRate = firstPackage && firstPackage.shipping_rates[0];

        if (!firstRate) {
            vm.submittingAddress = false;
            vm.addressFailed = true;
            vm.addressFailedMessage = 'No shipping method is available for this address.';
            return;
        }

        CartRepository.selectShippingRate(firstPackage.package_id, firstRate.rate_id).then(function (result) {
            vm.submittingAddress = false;

            if (result.failed) {
                vm.addressFailed = true;
                vm.addressFailedMessage = result.message;
                return;
            }

            vm.cart = result.cart;
            vm.addressConfirmed = true;
        });
    }

    vm.submitAddress = function () {
        if (vm.submittingAddress) {
            return;
        }

        vm.submittingAddress = true;
        vm.addressFailed = false;

        CartRepository.updateCustomer(vm.address).then(function (result) {
            if (result.failed) {
                vm.submittingAddress = false;
                vm.addressFailed = true;
                vm.addressFailedMessage = result.message;
                return;
            }

            // Server is the source of truth (same convention as
            // CartController) — the response already carries the recalculated
            // shipping_rates for the address just submitted.
            vm.cart = result.cart;
            selectFirstShippingRate();
        });
    };

    // 4.3: "cod" is the only payment method until the dedicated Stripe step
    // lands (see docs/PLAN.md). Guards against a double-click/resubmit
    // creating two orders — the Store API's own pending-draft-order reuse
    // (DraftOrderTrait) is the second layer of that same guardrail.
    vm.placeOrder = function () {
        if (vm.placingOrder) {
            return;
        }

        vm.placingOrder = true;
        vm.orderFailed = false;

        CartRepository.placeOrder(vm.address, 'cod', vm.createAccount).then(function (result) {
            vm.placingOrder = false;

            if (result.failed) {
                vm.orderFailed = true;
                vm.orderFailedMessage = result.message;
                return;
            }

            vm.order = result.order;
            vm.orderPlaced = true;
        });
    };
}]);
