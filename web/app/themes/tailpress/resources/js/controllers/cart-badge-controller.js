// Module 3: Cart. Drives the nav's cart badge (header.php) — runs on every
// page (TailPress enqueues all registered assets globally, not just on the
// Cart page), so the count stays accurate no matter where the shopper is.
angular.module('ecommerceApp').controller('CartBadgeController', ['CartRepository', function (CartRepository) {
    var vm = this;

    vm.count = 0;

    CartRepository.getCart().then(function (result) {
        vm.count = (!result.failed && result.cart) ? result.cart.items_count : 0;
    });
}]);
