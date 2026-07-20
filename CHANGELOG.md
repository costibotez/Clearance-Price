# Changelog

## 1.3
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
