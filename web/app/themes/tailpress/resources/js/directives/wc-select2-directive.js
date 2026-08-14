// Module 4: Checkout. Enhances the country <select> with WooCommerce's own
// selectWoo (WC's bundled select2 fork — same library/handle WC's own
// classic checkout uses, registered in
// woocommerce/includes/class-wc-frontend-scripts.php) rather than pulling
// in a second select2 build via npm.
//
// Deliberately narrower than reusing WC's own wc-country-select.js wholesale
// (assets/js/frontend/country-select.js): that script's real job is
// country -> state cascading tied to WC's own #billing_state/#shipping_state
// IDs and .woocommerce-billing-fields-style wrapper classes, which this app
// doesn't use — state stays free text per docs/PLAN.md's 4.1 decision
// ("per-country matching already handles it"). Only the enhanced-dropdown
// part is wanted here, so this is a small directive instead.
//
// selectWoo operates on top of the existing native <select> (hides it,
// mirrors it visually) rather than replacing it, and already dispatches a
// native 'change' event on that same element when the user picks an option
// — the exact event Angular's own `select`/`ngModel` directives already
// listen for on this node. So there's nothing to manually bridge back into
// ng-model (no $setViewValue, no $apply): the only real job here is timing —
// vm.countries loads asynchronously (see functions.php's Store API bridge),
// so the <option>s ng-options renders don't exist yet on this directive's
// first link; selectWoo needs to wait for them.
angular.module('ecommerceApp').directive('wcSelect2', ['$timeout', function ($timeout) {
    return {
        restrict: 'A',
        link: function (scope, element) {
            // selectWoo is a jQuery plugin — reach for the real global
            // jQuery that WordPress/WooCommerce already loads (a WP script
            // dependency of this file, see functions.php), not Angular's
            // own jqLite wrapper around `element`, which doesn't carry it.
            var $el = window.jQuery(element[0]);
            var initialized = false;

            function initSelect2() {
                if (initialized) {
                    return;
                }
                initialized = true;
                // Same attribute convention WooCommerce's own
                // wc-country-select.js reads (data-placeholder, falling
                // back to a plain placeholder attribute) — see
                // page-checkout.php's <select data-placeholder="...">.
                // Pairs with the first <option value="" disabled> already
                // in the markup: selectWoo recognizes that empty value as
                // the placeholder slot and renders this text in its place
                // instead of listing it as a real, selectable option.
                $el.selectWoo({
                    width: '100%',
                    placeholder: $el.data('placeholder') || $el.attr('placeholder') || ''
                });
            }

            var unwatch = scope.$watch(function () {
                return element.find('option').length;
            }, function (count) {
                if (count > 0) {
                    $timeout(initSelect2);
                    unwatch();
                }
            });

            scope.$on('$destroy', function () {
                if (initialized) {
                    $el.selectWoo('destroy');
                }
            });
        }
    };
}]);
