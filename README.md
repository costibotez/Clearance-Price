# Clearance Price for WooCommerce

This plugin allows you to define a *clearance price* for each WooCommerce product. When enabled, the clearance price can replace the regular product price during a configurable date range. It also displays a "Clearance!" badge instead of the default sale badge and shows a clearance-aware price range on variable products.

## Features
- Set a custom clearance price for simple and variable products
- Overwrite prices in the cart when the clearance period is active
- Show the clearance price (or a clearance price range for variable products) on the shop and product pages
- Optional replacement of the sale badge with a "Clearance!" label
- Administrative settings to control the date interval and product types
- "Clearance Price" column in the admin product list

## Requirements
- WordPress 5.6+
- WooCommerce 5.0+ (High-Performance Order Storage compatible)
- PHP 7.4+

## Date range behaviour
Dates are evaluated in the site's timezone. The "To" day is fully included (clearance ends at 23:59:59). Leaving "From" empty means the clearance is active immediately; leaving "To" empty means it never expires.

## Uninstalling
Deleting the plugin removes its settings. Per-product clearance prices are kept so they survive a reinstall; remove the `_clearance_price` product meta manually if you want a full cleanup.

If you find this plugin useful, consider supporting the development:
[Buy me a coffee](https://buymeacoffee.com/costinbotez).

For more plugins and updates, visit [Nomad Developer](https://nomad-developer.co.uk/).
