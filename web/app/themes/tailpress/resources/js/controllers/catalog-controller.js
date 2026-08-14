// Module 1: Catalog. Fetches the product list once on load and exposes
// explicit loading/error/product state for page-shop.php's template —
// deliberately no silent happy-path assumption (ProductRepository already
// folds a failed request into `failed: true` rather than throwing).
angular.module('ecommerceApp').controller('CatalogController', ['ProductRepository', function (ProductRepository) {
    var vm = this;

    vm.loading = true;
    vm.failed = false;
    vm.products = [];

    ProductRepository.getAll().then(function (result) {
        vm.products = result.products;
        vm.failed = result.failed;
        vm.loading = false;
    });
}]);
