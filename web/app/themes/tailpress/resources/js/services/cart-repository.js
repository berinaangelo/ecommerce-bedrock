// Isolates the WooCommerce Store API cart-add call (Repository pattern, same
// as ProductRepository) — POST /wc/store/v1/cart/add-item, body { id, quantity }.
// `cartAddItemUrl` is bridged in from PHP via wp_localize_script, same as
// ecommerceStoreApi.productsUrl.
angular.module('ecommerceApp').service('CartRepository', ['$http', 'StoreApiNonce', function ($http, StoreApiNonce) {
    // Never rejects, same fallback convention as ProductRepository — a failed
    // add-to-cart resolves with `failed: true` instead of an unhandled
    // rejection, so the controller can show a real error state.
    this.add = function (id, quantity) {
        return $http.post(
            window.ecommerceStoreApi.cartAddItemUrl,
            { id: id, quantity: quantity },
            { headers: { 'Nonce': StoreApiNonce.current } }
        ).then(
            function (response) {
                return { cart: response.data, failed: false };
            },
            function () {
                return { cart: null, failed: true };
            }
        );
    };
}]);
