=== OC Wolt Drive ===
Contributors: omerelias
Tags: woocommerce, shipping, wolt, delivery
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Wolt Drive courier integration for the OC Advanced Shipping plugin. Live pricing at checkout, manual + automatic dispatch, and JWT-signed status webhooks.

== Description ==

OC Wolt Drive plugs Wolt Drive into a WooCommerce store that already runs the "Original Concepts WooCommerce Advanced Shipping" plugin (the host shipping plugin). It is a side plugin: it does not duplicate or replace any of the host's shipping logic — slots, polygons, locations, local pickup, popups — and depends on it only through a small, well-defined contract (a shipping-method ID prefix and a handful of order meta keys).

= What it does =

* **Live pricing at checkout** — overrides the rate of the host shipping method with the quote returned by `POST /v1/venues/{venue_id}/shipment-promises`.
* **Auto dispatch** — when an order reaches the configured status, calls `POST /v1/venues/{venue_id}/deliveries` with recipient name + phone, parcels (with prices), the order number, scheduled dropoff time computed from the chosen slot, and dropoff comments built from floor / apartment / door code / "leave at the door" / customer note.
* **Manual dispatch button** — on each order edit screen, in the Wolt meta box.
* **JWT-signed webhook** — `POST /wp-json/ocws-wolt/v1/webhook` verifies the HS256 JWT against a shared secret you generate in the admin and provide to Wolt at registration time.
* **Admin console** — three-tab settings page under "Wolt Drive" in the WP admin menu: Settings, Webhook, Tools (live quote simulator + connection tester).

= Dependencies =

* WooCommerce.
* A shipping plugin that registers shipping methods with the configurable ID prefix (default: `oc_woo_advanced_shipping_method`). The OC Advanced Shipping plugin ships that out of the box.

If no host shipping plugin is present, OC Wolt Drive shows a soft warning in the admin but still loads. The price-override and auto-dispatch features are dormant until a matching shipping method exists.

== Installation ==

1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate "OC Wolt Drive" in WordPress → Plugins.
3. Go to "Wolt Drive" in the admin sidebar.
4. Fill in API URL, Merchant Key, Venue ID, Merchant ID, and Pickup address.
5. In the Webhook tab: click Generate, then provide the URL and secret to Wolt.

== Frequently Asked Questions ==

= Does this replace the OC Advanced Shipping plugin? =

No. It runs alongside it and reads its data through stable contracts (method ID prefix and order meta keys).

= What option keys does it use? =

All options are prefixed `ocws_wolt_*` (e.g. `ocws_wolt_api_key`, `ocws_wolt_venue_id`). The plugin uninstaller cleans them up when WordPress deletes the plugin.

== Changelog ==

= 1.0.0 =
* Extracted from the OC Advanced Shipping plugin into a standalone, dependency-free plugin.
* Standalone admin console with tabs (Settings / Webhook / Tools).
* Sanitisers + `register_setting` for every option.
* Configurable host shipping-method ID prefix.
* Native PHP `DateTime` replacing Carbon (no composer dependencies).
* HS256 JWT verification on incoming webhooks.
* Connection tester (`GET /merchants/{id}/delivery-areas`) from the admin UI.
* Manual "Create Wolt delivery" button on the order edit screen.
