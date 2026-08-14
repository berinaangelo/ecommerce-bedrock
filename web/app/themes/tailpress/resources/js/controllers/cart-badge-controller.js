// Module 3: Cart. Drives the nav's cart badge (header.php) — runs on every
// page (TailPress enqueues all registered assets globally, not just on the
// Cart page), so the count stays accurate no matter where the shopper is.
angular.module('ecommerceApp').controller('CartBadgeController', ['$rootScope', 'CartRepository', function ($rootScope, CartRepository) {
    var vm = this;

    vm.count = 0;

    CartRepository.getCart().then(function (result) {
        vm.count = (!result.failed && result.cart) ? result.cart.items_count : 0;
    });

    // laws-of-ux pass: keeps the badge accurate for the rest of this page's
    // lifetime after any add/update/remove/checkout elsewhere on the page —
    // see the broadcast in CartRepository, the one place every mutation
    // already flows through.
    $rootScope.$on('cart:changed', function (event, count) {
        vm.count = count;
    });
}]);
