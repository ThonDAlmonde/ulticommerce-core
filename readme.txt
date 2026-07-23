=== UltiCommerce ===
Contributors: ulticommerce, thondalmonde
Tags: e-commerce, products, orders, payment, cart
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A self-hosted e-commerce platform for WordPress with custom post types, state machine order management, multi-gateway payments, and SSO login.

== Description ==

UltiCommerce is a complete self-hosted e-commerce platform for WordPress. No WooCommerce required. Built with custom post types, a 13-status state machine for order management, extensible payment gateways, and SSO login.

Major features include:

* Custom `product` post type with SKU, pricing, stock, weight, dimensions, and gallery images
* Product variations with attribute-based combination generation
* Four taxonomies: Category, Brand, Collection, Tag
* PHP session-based cart with AJAX add/update/remove
* Coupon system (percent/fixed, min amount, expiry)
* Weight-based shipping rate calculation
* 13-status order state machine with allowed transitions
* PDF invoice generation (via dompdf)
* Multi-currency: USD, THB, CNY, EUR, GBP, RUB, INR
* REST API at `wpc/v1/products`
* CSV product import
* AJAX reviews with star ratings
* Favourites/wishlist
* Newsletter subscription
* Mega menu navigation
* Multi-language (7 languages: EN, TH, ZH, FR, DE, RU, HI)
* Compatible with UltiCommerce PayPal, UltiCommerce Stripe, and UltiCommerce Login plugins

== Installation ==

1. Upload the `ulticommerce` folder to `/wp-content/plugins/`
2. Run `composer install` in the plugin directory
3. Activate the plugin through the WordPress admin
4. Activate the UltiCommerce theme
5. Configure currency and product settings under Products → Settings
6. Install and activate payment gateway plugins as needed

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No. UltiCommerce is a standalone e-commerce platform with its own product and order system.

= What payment gateways are supported? =

Bank Wire is included. PayPal and Stripe are available as separate plugins.

= Can I sell in multiple currencies? =

Yes. UltiCommerce supports USD, THB, CNY, EUR, GBP, RUB, and INR. Configure your default currency under Products → Settings.

== Changelog ==

= 1.0.0 =
*Release Date - July 2026*

* Initial release
* Custom product and order post types
* Cart, checkout, and order management
* Bank Wire payment gateway
* Multi-currency support
* CSV product import
* REST API
* PDF invoice generation
* Multi-language support
