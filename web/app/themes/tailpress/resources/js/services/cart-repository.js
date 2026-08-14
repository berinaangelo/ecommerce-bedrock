// Isolates the WooCommerce Store API cart-add call (Repository pattern, same
// as ProductRepository) — POST /wc/store/v1/cart/add-item, body { id, quantity }.
// `cartAddItemUrl` is bridged in from PHP via wp_localize_script, same as
// ecommerceStoreApi.productsUrl.
angular.module('ecommerceApp').service('CartRepository', ['$http', '$rootScope', 'StoreApiNonce', function ($http, $rootScope, StoreApiNonce) {
    // laws-of-ux pass: CartBadgeController (header.php's nav, a sibling
    // controller with no other link to these calls) only fetched its count
    // once on its own load — an add/update/remove elsewhere on the page
    // never reached it, so the badge could sit stale until a full page
    // reload. Every mutating call already funnels through this repository,
    // so broadcasting the fresh count from here (rather than each of
    // ProductController/CartController/CheckoutController re-implementing
    // the same notification) is the one-place fix.
    function broadcastCount(count) {
        $rootScope.$broadcast('cart:changed', count);
    }

    // The Store API's field-validation errors (invalid postcode/state/etc.,
    // from free-text address input) carry the real, specific reason one
    // level deeper than the top-level `message` — that top-level message is
    // just the generic "Invalid parameter(s): billing_address,
    // shipping_address", with the actual "why" per-field in `data.params`
    // (confirmed against WP_REST_Server's rest_invalid_param response
    // shape). Falls back to the top-level message, then a generic string,
    // for errors that aren't shaped this way (e.g. a missing/invalid nonce).
    function addressErrorMessage(response) {
        var data = response.data || {};
        var params = data.data && data.data.params;

        if (params) {
            // billing/shipping usually carry the same message (one address
            // object submitted as both) — de-duplicate rather than repeat it.
            var messages = Object.keys(params).map(function (key) { return params[key]; });
            return messages.filter(function (message, index) { return messages.indexOf(message) === index; }).join(' ');
        }

        return data.message || 'Something went wrong — please try again.';
    }

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
                broadcastCount(response.data.items_count);
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
                broadcastCount(response.data.items_count);
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
                broadcastCount(response.data.items_count);
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
            function (response) {
                return { cart: null, failed: true, message: addressErrorMessage(response) };
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
            function (response) {
                return { cart: null, failed: true, message: addressErrorMessage(response) };
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
                // A successful checkout empties the cart server-side; the
                // checkout response is order data (no items_count field),
                // so broadcast 0 explicitly rather than reading it off here.
                broadcastCount(0);
                return { order: response.data, failed: false };
            },
            function (response) {
                return { order: null, failed: true, message: addressErrorMessage(response) };
            }
        );
    };
}]);
