// Isolates the WooCommerce Store API product list call from any controller
// that needs it (Repository pattern — see docs/PLAN.md's engineering
// guidelines). `ecommerceStoreApi.productsUrl` is bridged in from PHP via
// wp_localize_script (functions.php), pointing at rest_url('wc/store/v1/products').
angular.module('ecommerceApp').service('ProductRepository', ['$http', function ($http) {
    // Never rejects: a failed request resolves with `failed: true` and an
    // empty product list instead of leaving the caller to handle a broken
    // promise chain — the standing "always generate with a fallback" rule,
    // applied at the API boundary so callers can still tell "no products"
    // apart from "the request failed".
    this.getAll = function () {
        return $http.get(window.ecommerceStoreApi.productsUrl).then(
            function (response) {
                return { products: response.data, failed: false };
            },
            function () {
                return { products: [], failed: true };
            }
        );
    };
}]);
