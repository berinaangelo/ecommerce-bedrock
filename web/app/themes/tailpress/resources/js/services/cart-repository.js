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

    // Module 3: Cart. Read-only — no Nonce header needed (only writes require
    // one), same shape/fallback as every other repository call.
    this.getCart = function () {
        return $http.get(window.ecommerceStoreApi.cartUrl).then(
            function (response) {
                return { cart: response.data, failed: false };
            },
            function () {
                return { cart: null, failed: true };
            }
        );
    };

    this.updateItem = function (key, quantity) {
        return $http.post(
            window.ecommerceStoreApi.cartUpdateItemUrl,
            { key: key, quantity: quantity },
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

    this.removeItem = function (key) {
        return $http.post(
            window.ecommerceStoreApi.cartRemoveItemUrl,
            { key: key },
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

    // Module 4: Checkout. One address for both billing and shipping — the
    // mockup's "Contact & shipping" fieldset is a single section, no
    // separate billing UI. Recalculates shipping_rates as a side effect.
    this.updateCustomer = function (address) {
        return $http.post(
            window.ecommerceStoreApi.cartUpdateCustomerUrl,
            { billing_address: address, shipping_address: address },
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

    this.selectShippingRate = function (packageId, rateId) {
        return $http.post(
            window.ecommerceStoreApi.cartSelectShippingRateUrl,
            { package_id: packageId, rate_id: rateId },
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

    // Module 4: Checkout (4.3). Same address object as updateCustomer, reused
    // as both billing and shipping — POST /wc/store/v1/checkout creates the
    // real order. `createAccount` maps straight to the Store API's own
    // `create_account` flag; no password is sent (see checkout-controller.js),
    // so WooCommerce generates one and emails the new user.
    this.placeOrder = function (address, paymentMethod, createAccount) {
        return $http.post(
            window.ecommerceStoreApi.checkoutUrl,
            {
                billing_address: address,
                shipping_address: address,
                payment_method: paymentMethod,
                create_account: createAccount
            },
            { headers: { 'Nonce': StoreApiNonce.current } }
        ).then(
            function (response) {
                return { order: response.data, failed: false };
            },
            function () {
                return { order: null, failed: true };
            }
        );
    };
}]);
