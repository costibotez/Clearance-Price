# Clearance Price for Products — Analysis & Improvement Plan

**Plugin version analysed:** 1.1 · **Files:** `clearance.php` (~390 lines), `assets/js/scripts.js`, `assets/css/style.css` (empty)

## 1. What the plugin does today

A single-file WooCommerce extension that:

- Adds a `_clearance_price` meta field to simple products and to each variation of variable products (product edit screen).
- During a globally configured date range, overrides the cart item price (`woocommerce_before_calculate_totals`) and the displayed price of simple products (`woocommerce_get_price_html`).
- Hides the price range of variable products while the clearance window is active.
- Replaces the "Sale!" badge with "Clearance!" when a clearance price exists.
- Adds a "Clearance Price" column to the admin product list and a settings page under **Settings → Clearance Price for WooCommerce** (master on/off, per-product-type toggles, from/to dates).

---

## 2. Security review

### High priority

| # | Issue | Location | Detail |
|---|-------|----------|--------|
| S1 | Unescaped output of stored options | `clearance_price_from_date_cb_date()`, `clearance_price_to_date_cb_date()` (`clearance.php:170`, `:175`) | `echo get_option(...)` straight into an HTML attribute. The options are registered with `strval` as the "sanitize" callback, which sanitises nothing — a stored XSS vector on the settings page. Fix: validate as `Y-m-d` on save, escape with `esc_attr()` on output. |
| S2 | Unescaped output of post meta | `get_clearance_price_column()` (`clearance.php:341`) | `echo $clearance_price;` in the admin product list. Meta is only `esc_attr()`-ed *on input*, and can be written by other code paths (import plugins, REST, direct DB). Escape on output: `echo esc_html( $clearance_price );`. |
| S3 | No sanitisation of price values | `woo_clearance_price_save()` (`clearance.php:248`), `woo_clearance_price_save_variation()` (`clearance.php:277`) | `$_POST['_clearance_price']` is stored after `esc_attr()`, which is an escaping function, not a sanitiser — arbitrary strings become "prices" and flow into `set_price()` / `wc_price()`. Use `wc_format_decimal( wp_unslash( $_POST[...] ) )`. |
| S4 | Missing nonce/capability checks in save handlers | same as S3 | The handlers rely entirely on WooCommerce's upstream nonce. Add an explicit `current_user_can( 'edit_product', $post_id )` check and `isset()` guards (the unguarded `$_POST['_clearance_price']` access is also a PHP 8 warning/undefined-index bug when the field is absent, e.g. quick-edit or REST saves). |

### Medium priority

- **S5 — Ineffective sanitize callbacks in `register_setting`** (`clearance.php:130-141`): `strval` for dates should be a real validator (`function( $v ) { return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : ''; }`); checkboxes are fine with a `0/1` normaliser but should use `rest_sanitize_boolean`-style handling.
- **S6 — Broken uninstall hook** (`clearance.php:69`): `register_uninstall_hook( __FILE__, array( $this, ... ) )` with an instance method is invalid — WordPress requires a static method or plain function, so options are **never** deleted on uninstall. Replace with an `uninstall.php` file.
- **S7 — Translation functions echo unescaped**: `_e()` used throughout; prefer `esc_html_e()` / `esc_html__()` so translations can't inject markup.
- **S8 — Settings writes on every admin request** (`clearance.php:135-138`): when the master checkbox is off, four `update_option()` calls run on *every* `admin_init` — needless DB writes and it silently wipes saved config.

### Verdict

No unauthenticated exploit exists (all sinks are admin-side), but the plugin fails WordPress.org security guidelines: escape-on-output is absent, sanitisation is cosmetic, and the uninstall routine is non-functional. All of the above are cheap to fix and should be Phase 1.

---

## 3. Bugs & correctness issues

| # | Issue | Detail |
|---|-------|--------|
| B1 | **Timezone / off-by-one-day window** | Date checks mix `current_time( 'timestamp' )` (site-local) with `strtotime()` (parses in server/UTC context), and `to_date` parses to **midnight at the start** of the end day — clearance effectively ends a day early. Compare `DateTimeImmutable` objects built with `wp_timezone()` and extend the end date to 23:59:59. |
| B2 | **Empty dates disable the feature silently** | `strtotime('')` returns `false`; `false <= $now && $now <= false` is never true, so with no dates set the clearance price never applies — but only one empty field produces a *different* (asymmetric) result. Dates should be optional: empty from = "since forever", empty to = "no end". |
| B3 | **Variable price range hidden unconditionally** | `clearance_price_remove_variation_price()` (`clearance.php:286`) checks only the date window — not the master toggle, not the variable-product toggle, not whether any variation actually has a clearance price. During the window it blanks the price of **every** variable product. |
| B4 | **Displayed price ignores variations** | `clearance_price_html()` handles only simple products, so variable products show a blank price (because of B3) while the cart charges the clearance price — inconsistent and confusing to shoppers. |
| B5 | **Cart override ignores per-type toggle correctly but not product type** | In `clearance_price_calculate_totals()` the simple-product branch reads meta from `product_id` even for variable products (a variation's *parent* has no `_clearance_price`, but if it ever does, it silently overrides). Branch on `$cart_item['data']->get_type()` instead. |
| B6 | **WooCommerce detection misses multisite** | The `active_plugins` option check (`clearance.php:27`) fails for network-activated WooCommerce and runs at file load, before other plugins can filter. Use `plugins_loaded` + `class_exists( 'WooCommerce' )`. |
| B7 | **`WC_Cart` / `int` type hints can fatal** | `declare(strict_types=1)` plus typed hook callbacks (`clearance.php:193`, `:246`, `:275`) means a filter passing an unexpected type fatals the whole site instead of degrading. WordPress hook callbacks should be defensive. |
| B8 | **Missing plugin headers** | No `Text Domain`, `Domain Path`, `License`, `Requires at least`, `WC requires at least` / `WC tested up to` headers; no HPOS compatibility declaration (`FeaturesUtil::declare_compatibility`), so stores on High-Performance Order Storage get an "incompatible plugin" warning. |
| B9 | **Textdomain inconsistencies** | `'clearance_price'` used at `clearance.php:184` vs `'clearance-price'` elsewhere; two strings piggyback on the `'woocommerce'` domain (`clearance.php:368`, `:380`); no `/languages` folder or `.pot` file exists although `load_plugin_textdomain()` points at one. |
| B10 | **Sale-flash cost** | `clearance_price_replace_sale_text()` calls `$product->get_available_variations()` — a heavy call that builds full variation data — for every variable product on every archive page render. Query variation meta directly or cache the answer. |
| B11 | **Assets enqueued everywhere** | Front-end enqueues an **empty** CSS file on every page; admin JS loads on every admin screen with `version=false`. Enqueue only on the settings page (`$hook_suffix` check), delete the empty stylesheet, and pass the plugin version for cache-busting. |
| B12 | **Fragile settings-page JS** | `scripts.js` toggles sections with a chain of `.next().fadeIn()` calls that depends on exact DOM sibling order of WP settings tables — one added section breaks it. Use classes/`data-` attributes, and note the page still needs a reload after first enabling (fields are conditionally *registered* in PHP at `clearance.php:121`). |
| B13 | Cosmetics | Trailing `?>` (risk of stray whitespace output), mixed tabs/spaces, misleading `$show_sale_text` logic in the sale-flash handler, commented-out code left in save handlers. |

---

## 4. Improvement plan

### Phase 1 — Security & correctness hardening (no behaviour change)

1. Fix S1–S8: escape all output, sanitise prices with `wc_format_decimal`, validate dates, add capability + `isset` guards, move option cleanup to `uninstall.php`, stop the `admin_init` option writes.
2. Fix B1/B2 with a single `is_clearance_window_active()` helper using `wp_timezone()`-aware `DateTimeImmutable` comparisons and optional open-ended dates.
3. Fix B3–B5 so display, badge, and cart logic agree (respect toggles; support variable products in `woocommerce_get_price_html`).
4. Fix B6–B9: `plugins_loaded` bootstrap with `class_exists( 'WooCommerce' )`, complete headers, HPOS compatibility declaration, one consistent `clearance-price` textdomain, generate a `.pot`.
5. Fix B10/B11: cheap variation-meta lookup for the badge; scope asset loading; remove the empty stylesheet.

### Phase 2 — Architecture & tooling

- Split the single file into `includes/` classes (`Plugin`, `Settings`, `Product_Meta`, `Price_Manager`, `Admin_Columns`) with a small bootstrap in `clearance.php`; keep it dependency-free (no Composer requirement for runtime, optional for dev).
- Add dev tooling: `phpcs` with WordPress-Extra + WooCommerce sniffs, PHPStan (level 5+ with WP stubs), and a GitHub Actions workflow running lint + PHPCS on PHP 7.4–8.3.
- Add PHPUnit + `wp-env`/`wp-pest` integration tests for the price-window helper, cart override, and save sanitisation.
- Replace the custom options page with either the Settings API done properly (fields always registered, visibility handled client-side) or a **WooCommerce Settings tab** (`woocommerce_settings_tabs_array`) — more discoverable and gives date pickers for free.
- Add a `readme.txt` in WordPress.org format alongside `README.md`, and a `CHANGELOG`.

### Phase 3 — Feature roadmap (each independently shippable)

1. **Show original price struck through** next to the clearance price (like sale pricing) instead of replacing it — with a setting to choose either behaviour.
2. **Proper variable-product display**: show a clearance price range instead of blanking the price (fixes B3/B4 as a feature).
3. **Per-product schedule override**: optional from/to dates on the product itself, falling back to the global window.
4. **Percentage/bulk mode**: apply "X% off" clearance to a category, tag, or selection from the product list (bulk edit + quick edit support for the existing field, too).
5. **Customisable badge**: badge text, colours, and an option to show a "Clearance" badge even when WooCommerce wouldn't show "Sale!".
6. **Countdown timer** on the product page while the window is active ("Clearance ends in 2 days").
7. **Clearance catalogue surface**: a `[clearance_products]` shortcode + Gutenberg block + optional `/clearance` endpoint listing all products with an active clearance price.
8. **Automation rules**: auto-enable clearance when stock ≤ N or when a product hasn't sold in X days (cron-driven, opt-in).
9. **Store API / blocks compatibility pass**: verify cart/checkout blocks and mini-cart render the overridden price correctly (the `woocommerce_before_calculate_totals` approach works, but price *display* filters need Store API equivalents).
10. **Import/export & CLI**: WP-CLI command (`wp clearance set/list/clear`) and CSV column support for the meta field.
11. **Analytics**: mark orders that contained clearance-priced items (order item meta) so revenue from clearance can be reported.

### Suggested order

Phase 1 is a single small PR and should land first — it fixes real (if admin-scoped) security issues and user-visible bugs (B1–B4 especially). Phase 2 makes the codebase safe to grow. Phase 3 items 1–3 deliver the most user value for the least effort; 8–11 are differentiators for a WordPress.org release.
