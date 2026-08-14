// The Store API returns prices as minor-unit integer strings (e.g. "8900"
// for $89.00, given currency_minor_unit: 2) rather than display-ready
// values. This filter does that conversion once, reused by every module
// that shows a price (Catalog now; Product detail/Cart/Checkout later).
//
// Usage: {{ product.prices.price | wcPrice:product.prices }}
angular.module('ecommerceApp').filter('wcPrice', function () {
    return function (minorUnitAmount, prices) {
        if (minorUnitAmount == null || !prices) {
            return '';
        }

        var minorUnit = Number(prices.currency_minor_unit) || 0;
        var amount = Number(minorUnitAmount) / Math.pow(10, minorUnit);
        var prefix = prices.currency_prefix || '';
        var suffix = prices.currency_suffix || '';

        return prefix + amount.toFixed(minorUnit) + suffix;
    };
});
