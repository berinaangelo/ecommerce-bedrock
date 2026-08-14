// Root module for the storefront Angular SPA, bootstrapped via ng-app on <main>
// in header.php. AngularJS itself is loaded from a CDN (see functions.php) —
// this file only holds the app's own code, built by Vite alongside Tailwind.
//
// Modules 1-5 (Catalog, Product detail, Cart, Checkout, Confirmation) register
// their controllers/services on this module as they're built.
angular.module('ecommerceApp', []);
