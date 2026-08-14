// Module 3: Cart. Drives page-cart.php — loads the cart once on load, then
// owns per-line quantity/remove mutations. No optimistic UI updates: every
// mutation re-renders vm.cart from that call's own fresh response (same
// "server is the source of truth" convention as ProductRepository), guarded
// by a per-item "updating" flag so a double-click can't race two requests
// for the same line.
angular.module('ecommerceApp').controller('CartController', ['CartRepository', function (CartRepository) {
    var vm = this;

    vm.loading = true;
    vm.failed = false;
    vm.cart = null;
    vm.actionFailed = false;

    vm.updatingKeys = {};

    CartRepository.getCart().then(function (result) {
        vm.cart = result.cart;
        vm.failed = result.failed;
        vm.loading = false;
    });

    vm.isUpdating = function (key) {
        return !!vm.updatingKeys[key];
    };

    // Shared by updateQuantity/removeItem below: guards against a second
    // click on the same line while a request is in flight, and never
    // touches vm.cart on failure — a failed call must not leave the cart
    // half-updated (external-call guardrail in docs/PLAN.md).
    function applyCartMutation(key, request) {
        if (vm.isUpdating(key)) {
            return;
        }

        vm.updatingKeys[key] = true;
        vm.actionFailed = false;

        request.then(function (result) {
            delete vm.updatingKeys[key];

            if (result.failed) {
                vm.actionFailed = true;
                return;
            }

            vm.cart = result.cart;
        });
    }

    vm.updateQuantity = function (item, delta) {
        var quantity = Math.max(1, item.quantity + delta);
        if (quantity === item.quantity) {
            return;
        }

        applyCartMutation(item.key, CartRepository.updateItem(item.key, quantity));
    };

    vm.removeItem = function (item) {
        applyCartMutation(item.key, CartRepository.removeItem(item.key));
    };
}]);
