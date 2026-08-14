// The Store API requires a `Nonce` request header on writes (verified against
// wp_create_nonce('wc_store_api') server-side) and hands one back on the
// `Nonce` response header — but only from cart routes (GET/POST /cart, /cart/*),
// confirmed against AbstractCartRoute.php; plain product routes like
// /products/{id} never set it. So Catalog/Product-detail's own GET calls
// can't seed this — the run() block below primes it with a GET to the cart
// endpoint on app bootstrap, same as the official WC Blocks client does.
//
// StoreApiNonce just holds the latest value; the interceptor keeps it fresh
// from every subsequent cart-route response too. CartRepository reads it
// when sending a write. Built now because Module 2's "Add to Cart" is the
// first write — Modules 3-4 (cart updates, checkout) reuse this same nonce,
// so it's not over-built for what Module 2 alone needs.
angular.module('ecommerceApp').value('StoreApiNonce', { current: null });

angular.module('ecommerceApp').config(['$httpProvider', function ($httpProvider) {
    $httpProvider.interceptors.push(['StoreApiNonce', function (StoreApiNonce) {
        return {
            response: function (response) {
                var nonce = response.headers('Nonce');
                if (nonce) {
                    StoreApiNonce.current = nonce;
                }
                return response;
            }
        };
    }]);
}]);

angular.module('ecommerceApp').run(['$http', function ($http) {
    // Fire-and-forget: a failed prime just leaves StoreApiNonce.current null,
    // and CartRepository.add() already resolves `failed: true` rather than
    // throwing when the Store API rejects a nonce-less write.
    $http.get(window.ecommerceStoreApi.cartUrl);
}]);
