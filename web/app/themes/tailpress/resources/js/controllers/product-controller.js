// Module 2: Product detail. Fetches the single product on load (id comes
// from window.ecommerceStoreApi.productId, localized per-page since there's
// no client-side router — see single-product.php) and owns the quantity
// stepper + Add to Cart action.
angular.module('ecommerceApp').controller('ProductController', ['ProductRepository', 'CartRepository', function (ProductRepository, CartRepository) {
    var vm = this;

    vm.loading = true;
    vm.failed = false;
    vm.product = null;
    vm.description = '';

    vm.quantity = 1;
    vm.adding = false;
    vm.addFailed = false;
    vm.added = false;

    ProductRepository.getById(window.ecommerceStoreApi.productId).then(function (result) {
        vm.product = result.product;
        vm.failed = result.failed;
        vm.loading = false;

        // short_description/description come back as WC-rendered HTML
        // (usually just a wrapping <p>) — this app has no ngSanitize/$sce
        // dependency, so strip tags here rather than trust raw HTML into
        // ng-bind-html for a single plain-text paragraph.
        if (vm.product) {
            var html = vm.product.short_description || vm.product.description || '';
            vm.description = html.replace(/<[^>]*>/g, '').trim();
        }
    });

    vm.decreaseQuantity = function () {
        vm.quantity = Math.max(1, vm.quantity - 1);
    };

    vm.increaseQuantity = function () {
        vm.quantity += 1;
    };

    vm.addToCart = function () {
        if (!vm.product || !vm.product.is_in_stock || vm.adding) {
            return;
        }

        vm.adding = true;
        vm.addFailed = false;
        vm.added = false;

        CartRepository.add(vm.product.id, vm.quantity).then(function (result) {
            vm.adding = false;
            vm.addFailed = result.failed;
            vm.added = !result.failed;
        });
    };
}]);
