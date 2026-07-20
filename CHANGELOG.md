# Changelog

## 1.5
- **New:** developer filters `clearance_price_window_active` (override whether the window is active for a product) and `clearance_price_badge_html` (customise the badge markup), documented alongside the existing `clearance_price_settings_after_form` action.
- **Docs:** rebuilt `README.md` with a banner, tagline, badges, feature list, screenshots, shortcode/hooks/data reference, and added plugin banner + screenshot assets under `.wordpress-org/`.

## 1.4
- **New:** per-product clearance schedule — optional From/To date fields on simple and variable products override the global date range (variations follow their parent's schedule).
- **New:** bulk tool on the settings page — apply a percentage-off clearance price to every product and variation in a category, or clear a category's clearance prices (requires `manage_woocommerce`, nonce-protected).
- **New:** customisable badge — badge text and background/text colours in the Display settings; products with an active clearance price are treated as on sale (`woocommerce_product_is_on_sale`) so the badge shows even without a WooCommerce sale price.
- **New:** optional "Clearance ends in ..." notice on single product pages when the applicable window has an end date.
- **New:** `[clearance_products]` shortcode rendering products with an active clearance price through WooCommerce's `[products]` shortcode (`limit`, `columns`, `orderby`, `order` attributes).
- **New:** the applied clearance price is recorded on order line items as `_clearance_price_applied` for later reporting.

## 1.3
- **New:** optional "Show original price struck through?" display setting — when enabled, simple products and variations show the regular price crossed out next to the clearance price (sale-style) instead of only the clearance price.
- Refactor the single-file plugin into `includes/` classes (`Options`, `Settings`, `Product_Meta`, `Price_Manager`, `Admin_Columns`) wired by a slim `clearance.php` bootstrap.
- Add dev tooling: PHPCS (WordPress-Extra + PHPCompatibilityWP), PHPStan (level 5 with WordPress/WooCommerce stubs), PHPUnit unit tests for the date window, price validation and settings sanitizers, and a GitHub Actions CI workflow (lint on PHP 7.4–8.3, analyse, test).
- Add `readme.txt` (WordPress.org format) and this changelog.

## 1.2
- **Security:** escape all output (settings date fields, admin column); sanitise prices with `wc_format_decimal()` and reject non-numeric/negative values; add capability checks and input guards to save handlers; real sanitize callbacks for all settings; replace the broken uninstall hook with `uninstall.php`; stop option writes on every `admin_init`.
- **Fixed:** timezone-aware clearance window — the end date is now fully included (it previously ended a day early) and empty dates mean open-ended; cart override branches correctly between simple products and variations and respects the product-type toggles; variable products show a clearance-aware price range instead of a blanked price; the sale badge check respects toggles/window and no longer calls the heavy `get_available_variations()`.
- **Changed:** bootstrap on `plugins_loaded` with `class_exists( 'WooCommerce' )` (works with network-activated WooCommerce); declare HPOS compatibility; complete plugin headers; consistent `clearance-price` textdomain with a `.pot` file; settings JS loads only on the settings page and applies section visibility on load; removed the empty site-wide stylesheet.

## 1.1
- Require PHP 7.4 and update code to modern PHP standards.

## 1.0
- Initial release.
