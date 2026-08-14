// Module 4: Checkout. Loads the cart on init (for the summary panel, same as
// CartController) and owns the address form. Country is fixed to the store's
// own base country (US) rather than a form field — no separate UI, since
// this storefront isn't scoped for multi-country shipping.
//
// Stops at "address confirmed" — Stripe's deferred to its own step (see
// docs/PLAN.md), so there's no "Place Order" here yet.
angular.module('ecommerceApp').controller('CheckoutController', ['CartRepository', function (CartRepository) {
    var vm = this;

    vm.loading = true;
    vm.failed = false;
    vm.cart = null;

    vm.address = {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        address_1: '',
        city: '',
        state: '',
        postcode: '',
        country: 'US'
    };

    vm.submittingAddress = false;
    vm.addressFailed = false;
    vm.addressConfirmed = false;

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
            return;
        }

        CartRepository.selectShippingRate(firstPackage.package_id, firstRate.rate_id).then(function (result) {
            vm.submittingAddress = false;

            if (result.failed) {
                vm.addressFailed = true;
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
                return;
            }

            // Server is the source of truth (same convention as
            // CartController) — the response already carries the recalculated
            // shipping_rates for the address just submitted.
            vm.cart = result.cart;
            selectFirstShippingRate();
        });
    };
}]);
