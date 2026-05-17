=== OC Wolt Drive ===
Contributors: omerelias
Tags: woocommerce, shipping, wolt, delivery
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.3.0
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

== Translation ==

All translatable strings live under the `oc-wolt-drive` text domain.
Translations live in `languages/`:

  - `oc-wolt-drive.pot`            — master template (no translations)
  - `oc-wolt-drive-{locale}.po`    — per-language source with translations
  - `oc-wolt-drive-{locale}.mo`    — compiled binary that WP actually loads

When you add new strings to the code, refresh the POT and merge into
every existing translation in one step:

    bash bin/make-pot.sh        # POT + auto-merge .po files (default)
    bin\make-pot.bat

Add `--no-merge` to regenerate the POT without touching .po files.

The merge step (gettext's `msgmerge`) is non-destructive: existing
translations are preserved, new strings appear with an empty translation,
strings that changed in the source are marked "fuzzy" for review, and
strings deleted from the source are marked "obsolete" rather than removed.
Open the updated .po in Poedit and save — Poedit will recompile the .mo
binary alongside automatically.

To start a brand-new language, copy the POT:

    cp languages/oc-wolt-drive.pot languages/oc-wolt-drive-en_GB.po

…and open the new file in Poedit.

== Changelog ==

= 1.3.0 =
* **Auto-dispatch now catches brand-new orders too.** The previous single-status hook only fired on transitions, so orders created at `pending` (typical for COD / pay-on-delivery) were never picked up automatically. The trigger now listens to both `woocommerce_order_status_changed` (every transition) and `woocommerce_new_order` (initial creation), compares against the configured trigger status, and dispatches. `create_for_order()` is idempotent so the double subscription is safe.
* **HPOS (High-Performance Order Storage) compatibility declared.** All order-meta reads go through `$order->get_meta()`; admin order URLs are built via a helper that returns the correct path under either legacy `post.php` or HPOS `admin.php?page=wc-orders`; the order meta box is registered against `wc_get_page_screen_id( 'shop-order' )`; the box's render callback accepts both `WP_Post` and `WC_Order`.
* **i18n scaffolding:** `languages/` directory + a generated `oc-wolt-drive.pot` (~170 strings) + `bin/make-pot.sh` and `bin/make-pot.bat` wrappers around `wp i18n make-pot` that regenerate the POT against the current source. Added `translators:` PHPDoc comments to every `%s` / `%d` string so translators have context.

= 1.2.0 =
* Persist additional fields from Wolt: pickup ETA, dropoff ETA (range), tracking id, Wolt's cost (in major currency units), delivered_at timestamp.
* Admin meta box now shows "Courier ETA at venue", "Delivery ETA", and "Wolt cost" alongside the existing status / tracking link.
* Deliveries dashboard table gets two new columns: ETA (dropoff) and Cost.
* Webhook handler also refreshes ETAs / price / delivered_at off every event so the data stays live as the courier progresses.
* New customer-facing tracking card on the order-received (thank-you) page and the My Account "View order" page — Wolt-branded gradient, status-aware headline ("On the way", "Delivered", etc.), prominent "Track delivery" pill button. Localised + RTL-friendly + mobile-first.
* Tracking URL automatically added as a row to WooCommerce customer-facing emails (processing, completed).

= 1.1.0 =
* One-click "Register webhook with Wolt" button on the Webhook tab — calls `POST /v1/merchants/{merchant_id}/webhooks` for you and stores the returned webhook ID locally. No more manual curl per site.
* "Unregister" button (`DELETE /v1/merchants/{merchant_id}/webhooks/{id}`) plus a "Re-register" path.
* Visible registration status (registered / not registered + the Wolt-side webhook id).
* Defensive `class_exists` guards on every class declaration so legacy stale copies of the Wolt module living inside a host shipping plugin folder can no longer cause a fatal "Cannot declare class" error — the duplicate file is skipped and a diagnostic line is written to error_log identifying its source.

= 1.0.0 =
* Extracted from the OC Advanced Shipping plugin into a standalone, dependency-free plugin.
* Standalone admin console with tabs (Settings / Webhook / Tools).
* Sanitisers + `register_setting` for every option.
* Configurable host shipping-method ID prefix.
* Native PHP `DateTime` replacing Carbon (no composer dependencies).
* HS256 JWT verification on incoming webhooks.
* Connection tester (`GET /merchants/{id}/delivery-areas`) from the admin UI.
* Manual "Create Wolt delivery" button on the order edit screen.
