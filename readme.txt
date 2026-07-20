=== Clearance Price for Products ===
Contributors: costinbotez
Tags: woocommerce, clearance, price, sale, discount
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a custom clearance price for WooCommerce products, applied during a configurable date range.

== Description ==

This plugin allows you to define a *clearance price* for each WooCommerce product. When enabled, the clearance price replaces the regular product price during a configurable date range.

= Features =

* Set a custom clearance price for simple products and for each variation of variable products
* Overwrite prices in the cart while the clearance period is active
* Show the clearance price (or a clearance-aware price range for variable products) on shop and product pages
* Optionally show the original price struck through next to the clearance price
* Per-product clearance schedules that override the global date range
* Bulk tool to apply a percentage-off clearance price to a whole product category
* Customisable badge text and colours
* Optional "Clearance ends in ..." notice on product pages
* [clearance_products] shortcode listing products with an active clearance price
* Orders record the applied clearance price on each line item
* Replace the sale badge with a "Clearance!" label
* "Clearance Price" column in the admin product list
* Compatible with WooCommerce High-Performance Order Storage

= Date range behaviour =

Dates are evaluated in the site's timezone. The "To" day is fully included (clearance ends at 23:59:59). Leaving "From" empty means the clearance is active immediately; leaving "To" empty means it never expires.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/clearance-price`, or install through the WordPress plugins screen.
2. Activate the plugin. WooCommerce must be active.
3. Go to Settings → Clearance Price for WooCommerce, enable clearance pricing and choose the product types and date interval.
4. Edit a product and fill in the "Clearance Price" field (in the General tab for simple products, on each variation for variable products).

== Frequently Asked Questions ==

= Does deleting the plugin remove my data? =

Deleting the plugin removes its settings. Per-product clearance prices are kept so they survive a reinstall; remove the `_clearance_price` product meta manually if you want a full cleanup.

== Changelog ==

= 1.4 =
* Per-product clearance schedule fields (From/To) overriding the global date range
* Bulk apply/clear percentage-off clearance prices by product category
* Customisable badge text and colours; clearance products count as on sale so the badge always shows
* Optional "Clearance ends in ..." notice on product pages
* [clearance_products] shortcode
* Applied clearance price recorded on order line items

= 1.3 =
* New display option to show the original price struck through next to the clearance price
* Refactor into separate classes with a slim bootstrap
* Add coding-standards, static-analysis and unit-test tooling with CI

= 1.2 =
* Security hardening: output escaping, real price/date sanitisation, capability checks, working uninstall cleanup
* Timezone-aware clearance window; the end date is now fully included and empty dates mean open-ended
* Variable products show a clearance-aware price range instead of a blanked price
* Detect network-activated WooCommerce; declare HPOS compatibility

= 1.1 =
* Require PHP 7.4 and update code to modern PHP standards

= 1.0 =
* Initial release
