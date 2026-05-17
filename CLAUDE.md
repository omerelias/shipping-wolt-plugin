# OC Wolt Drive — context for Claude

This file is the briefing pack for any future Claude session. Read it
first; it explains what the plugin is, why every odd-looking decision
exists, and where the booby traps are.

---

## What this plugin is (and isn't)

**OC Wolt Drive** is a standalone WordPress plugin that adds Wolt Drive
(Wolt's courier-on-demand API) to a WooCommerce store. It is NOT a
shipping plugin on its own — it is a **side plugin** that sits next to
the host shipping plugin "Original Concepts WooCommerce Advanced
Shipping" (the OC plugin, internal text-domain `ocws`).

| | OC Advanced Shipping plugin | OC Wolt Drive (this repo) |
|---|---|---|
| Role | Registers a shipping method, slots, polygons, address fields, popups | Replaces that method's price with Wolt's quote and dispatches the order to a Wolt courier |
| Required? | Yes for any of this to work | Yes if you want courier integration |
| Relationship | Owns the data | Reads/overrides it |

The site `delinka.deliz.co.il` runs both. The repo on GitHub
(`omerelias/shipping-wolt-plugin`) used to contain the OC plugin too,
but it was extracted in May 2026 (see "Extraction history" below) and
now contains **only** the Wolt code.

---

## Architecture in 30 seconds

```
Customer at checkout
       │
       │  WooCommerce computes shipping rates
       ▼
woocommerce_package_rates filter (priority 20)
       │
       │  OCWS_Wolt_Price_Override::filter_package_rates()
       │  • Skips unless plugin enabled + API configured
       │  • Calls Wolt /v1/venues/{venue_id}/shipment-promises
       │  • Overrides the host method's rate cost (NEVER 0s it
       │    on failure — leaves host price alone)
       ▼
Customer sees Wolt's price in checkout, pays
       │
       │  Order moves to "Auto-dispatch on status" (default: processing)
       ▼
woocommerce_order_status_{slug} action  (NOTE: slug WITHOUT the wc- prefix)
       │
       │  OCWS_Wolt_Delivery_Trigger::on_trigger_status()
       │  • Idempotent (returns early if META_DELIVERY_ID already set)
       │  • Builds payload (recipient, parcels, scheduled_dropoff_time,
       │    structured dropoff.location.address object)
       │  • POSTs to /v1/venues/{venue_id}/deliveries
       │  • Saves: META_DELIVERY_ID, META_WOLT_ORDER_REF,
       │    META_WOLT_STATUS, META_TRACKING_URL
       ▼
Wolt accepts (HTTP 201) → courier dispatched
       │
       │  Wolt POSTs events to https://delinka.deliz.co.il/wp-json/ocws-wolt/v1/webhook
       │  Body is a JWT signed HS256 with the merchant-provided secret
       ▼
OCWS_Wolt_Webhook::handle_webhook()
       │  • Verifies signature with hash_equals
       │  • Decodes claims, flattens common shapes
       │  • Looks up order by wolt_order_reference_id → fallback
       │    to delivery id → fallback to merchant_order_reference_id
       │  • Updates META_WOLT_STATUS, writes order note
```

There is also a **manual "Create Wolt delivery now"** button on the
order edit screen meta box (`OCWS_Wolt_Order_Meta_Box`) that hits the
same `create_for_order($order, $manual = true)` code path with the
shipping-method-ID-prefix gate bypassed.

---

## The host shipping plugin contract

This plugin reads from the host but does NOT depend on its classes —
only on string conventions, so it can serve any fork of the OC plugin
(the site has multiple). The contract:

### Shipping method ID prefix
- Default: `oc_woo_advanced_shipping_method`
- Configurable in Settings → Advanced (`ocws_wolt_method_id_prefix`)
- The host plugin's rate IDs look like `oc_woo_advanced_shipping_method5`
- Price-override matches by `strpos($rate_id, $prefix) === 0`
- Auto-dispatch only fires for orders that used a matching method

### Order meta keys the host populates at checkout
These are written by the OC plugin's Google-autocomplete checkout flow.
We read them for delivery dispatch:

| Meta key | What it is |
|---|---|
| `_shipping_street` / `_billing_street` | Street name (no house number) |
| `_shipping_house_num` / `_billing_house_num` | House number |
| `_shipping_city_name` / `_billing_city_name` | Full city name |
| `_shipping_floor` / `_billing_floor` | Floor (Israeli convention) |
| `_shipping_apartment` / `_billing_apartment` | Apartment number |
| `_shipping_enter_code` / `_billing_enter_code` | Door code |
| `_shipping_phone` | Recipient phone (the OC plugin sets this explicitly) |
| `_shipping_address_coords` | Lat/lng JSON (sometimes string-encoded) |
| `ocws_shipping_info_date` | Chosen delivery date in `d/m/Y` |
| `ocws_shipping_info_slot_start` | Slot start in `HH:MM` |
| `ocws_leave_at_the_door` | "1" if customer ticked the box |

### WC's standard destination keys are usually EMPTY in this setup
The OC plugin populates its own keys on the `$package['destination']`
array via filters. WC's standard `address`, `address_1`, `city`,
`postcode` are typically blank. The `resolve_street()` / `resolve_city()`
helpers in `OCWS_Wolt_Api` try the OC keys first, fall back to WC's.

---

## Wolt API gotchas (every one learned the hard way)

### shipment-promises body is FLAT
```json
{ "street": "...", "city": "...", "post_code": "...", "lat": 0, "lon": 0 }
```
**NOT** nested under `address: {...}` or `location: {...}`. Wolt's
validator looks for `post_code` OR (`street` AND `city`) at the body
root and rejects everything else with "Input should be a valid
dictionary".

### Coordinates are `lon`, not `lng`
Only matters at the body root of shipment-promises. Inside
`dropoff.location` for create-delivery it's `lng` — yes, inconsistent,
but that's how Wolt's API is.

### create-delivery dropoff.location.address is a STRUCTURED OBJECT
```json
{
  "dropoff": {
    "location": {
      "address": {
        "street": "מבצע קדש 67",
        "city": "תל אביב יפו",
        "post_code": "",
        "country": "IL"
      },
      "lat": 32.11,
      "lng": 34.82
    }
  }
}
```
Not a formatted string. Not nested deeper. Coords are siblings of
`address` inside `location`.

### Amounts are in MINOR currency units
`{"price":{"amount":4200,"currency":"ILS"}}` means ₪42.00, not ₪4200.
Divide by 100 when reading (`get_shipment_promise`). When sending parcel
prices, we currently send major units (e.g. `12.5`) — TODO to verify if
Wolt expect minor here too.

### Webhooks use HS256 JWT with a merchant-provided secret
- WE generate the secret (in the admin UI, "Generate" button)
- WE give Wolt the secret at webhook registration
- Wolt signs every event payload with that secret
- Our webhook handler verifies with `hash_equals` (time-constant)
- Our minimal HS256 verifier lives in `OCWS_Wolt_Webhook::verify_jwt_hs256()`
  to avoid pulling in `firebase/php-jwt` for ~40 lines of code

### Webhook URL is at the merchant level, but events reference venues
- One webhook per merchant — covers all venues
- Events arrive with `wolt_order_reference_id` (NOT the `id` we got back
  from create-delivery — they're different values)
- We store both as separate meta keys and try `wolt_order_reference_id`
  first when matching events to orders

### Auto-dispatch must listen to TWO events, not one
WC fires `woocommerce_order_status_{slug}` only on a status TRANSITION.
Brand-new orders that are CREATED already in the trigger status
(typical for `pending` / COD) never see a transition. As of v1.3.0
the trigger subscribes to both `woocommerce_order_status_changed`
(every transition) and `woocommerce_new_order` (initial creation),
filtering by the configured trigger status. `create_for_order()` is
idempotent (META_DELIVERY_ID short-circuit) so the double subscription
is safe. Don't go back to a single-slug hook.

### Trigger-status comparisons must strip `wc-`
`wc_get_order_statuses()` returns `wc-pending`/`wc-processing`. WC's
status-changed callbacks pass the slug WITHOUT the prefix. Use
`status_matches_trigger()` on the trigger class — it strips `wc-` from
both sides before comparing.

### Endpoint URL patterns
| Purpose | Path | Notes |
|---|---|---|
| Quote | `POST /v1/venues/{venue_id}/shipment-promises` | Venue-scoped |
| Create delivery | `POST /v1/venues/{venue_id}/deliveries` | Venue-scoped |
| Get delivery areas | `GET /merchants/{merchant_id}/delivery-areas` | Merchant-scoped (sanity check) |
| Cancel | `PATCH /order/{wolt_order_reference_id}/status/cancel` | NOT scoped, uses ref id |
| Register webhook | `POST /v1/merchants/{merchant_id}/webhooks` | Merchant-scoped |
| List webhooks | `GET /v1/merchants/{merchant_id}/webhooks` | |

Sandbox base: `https://daas-public-api.development.dev.woltapi.com`
Production base: `https://daas-public-api.wolt.com` (only after Wolt
grants prod credentials after staging tests pass).

---

## Merchant vs Venue

- **Merchant** = the business account (Delinka, one of these)
- **Venue** = a specific physical pickup location (one venue per branch)
- One merchant can own many venues; one webhook per merchant receives
  events from all of them
- Path parameter chosen based on what scope the endpoint operates at:
  - "for the business" → `{merchant_id}` (webhook, delivery-areas)
  - "for this branch" → `{venue_id}` (promises, deliveries)

Delinka has venue `קהילת סלוניקי 7, תל אביב יפו` (id stored in WP option).
There's a second branch coming — contact Wolt's Tohar (per the original
rep's note) for its venue id when needed. The plugin currently supports
ONE venue id per site; multi-venue support is a future enhancement.

---

## i18n / translations

- Text domain: `oc-wolt-drive`
- Domain path: `/languages` (loaded in `ocws_wolt_bootstrap()` via
  `load_plugin_textdomain()`)
- POT file: `languages/oc-wolt-drive.pot` (~170 strings)
- Hebrew starter template: `languages/oc-wolt-drive-he_IL.po`
- **Regenerate the POT + auto-merge into existing .po** after touching
  any `__()` / `_e()` / `_n()`:
  ```
  bash bin/make-pot.sh        # default: regenerate POT + msgmerge .po files
  bash bin/make-pot.sh --no-merge   # POT only
  bin\make-pot.bat            # Windows cmd equivalent
  ```
  Both wrappers shell out to `wp i18n make-pot` (WP-CLI) and then run
  `msgmerge --update --backup=none` for every `languages/*.po`. They
  auto-find wp-cli.phar at `/c/wp-cli/wp-cli.phar` if `wp` isn't in
  PATH, and warn cleanly if msgmerge (gettext) isn't installed.
- **msgmerge is non-destructive**: existing translations preserved,
  new strings appear with empty msgstr, changed strings flagged
  `#, fuzzy` for review, deleted strings marked `#~ ` obsolete.
- After merging, open the `.po` in Poedit → save → `.mo` is recompiled
  automatically. Upload both `.po` and `.mo` (WP only reads `.mo`).
- **Every** sprintf-style translatable string must carry a
  `/* translators: %s: … */` comment immediately above it. WP-CLI's
  make-pot warns about missing comments — keep that list empty.

## HPOS (High-Performance Order Storage)

The plugin declares compatibility in the bootstrap via
`FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true )`.
Rules of thumb so this stays true:

- **Never use `get_post_meta` / `update_post_meta` on an order ID.** Use
  `$order->get_meta()` / `$order->update_meta_data()` / `$order->save()`.
- **Edit links**: use `ocws_wolt_order_edit_url( $order_id )` (defined in
  the bootstrap) — returns the HPOS URL when HPOS is on, the legacy
  `post.php` URL otherwise.
- **Meta boxes**: register against `wc_get_page_screen_id( 'shop-order' )`,
  not the literal `'shop_order'` post type. Render callbacks must accept
  either a `WP_Post` (legacy) or a `WC_Order` (HPOS).
- **Order queries**: `wc_get_orders()` works under HPOS transparently;
  don't drop down to `WP_Query` / `$wpdb` for orders.

## File structure

```
shipping-wolt-plugin/
├── CLAUDE.md                     ← you are here
├── oc-wolt-drive.php             ← Plugin header, bootstrap, env guards,
│                                   activation defaults
├── readme.txt                    ← WP-style plugin readme
├── uninstall.php                 ← Deletes every ocws_wolt_* option
├── .gitignore                    ← Excludes .claude/, .idea/, etc.
├── LICENSE.txt                   ← GPL-2.0+
├── assets/
│   ├── css/admin.css             ← Wolt-style design tokens + components
│   └── js/admin.js               ← AJAX for test/generate/cancel/register
└── includes/
    ├── class-ocws-wolt.php       ← Runtime loader (init() chain)
    ├── class-ocws-wolt-admin.php ← Standalone admin page (4 tabs)
    ├── class-ocws-wolt-settings.php
    ├── class-ocws-wolt-api.php   ← All HTTP calls to Wolt
    ├── class-ocws-wolt-price-override.php
    ├── class-ocws-wolt-delivery-trigger.php
    ├── class-ocws-wolt-order-meta-box.php
    └── class-ocws-wolt-webhook.php
```

**Every class is wrapped in `if ( ! class_exists(...) ) : … endif;`** so
that a stale legacy copy living inside a host plugin folder cannot
trigger a "Cannot declare class" fatal. When that happens, the duplicate
file is skipped and a structured `error_log` line identifies the source.

---

## Settings, stored as WP options

All keys are `ocws_wolt_*` (kept stable across the extraction so old
installations migrate automatically).

| Option | Purpose |
|---|---|
| `ocws_wolt_enabled` | Master on/off |
| `ocws_wolt_api_url` | Sandbox / production base URL |
| `ocws_wolt_api_key` | Bearer token (Merchant Key) |
| `ocws_wolt_venue_id` | Path param for venue-scoped endpoints |
| `ocws_wolt_merchant_id` | Path param for merchant-scoped endpoints |
| `ocws_wolt_webhook_secret` | HS256 signing secret we share with Wolt |
| `ocws_wolt_webhook_id` | The id Wolt returns when we register (used to delete) |
| `ocws_wolt_pickup_address` | Pickup formatted address; falls back to WC store address |
| `ocws_wolt_trigger_status` | Order status for auto-dispatch (`wc-processing` etc.) |
| `ocws_wolt_dispatch_offset_minutes` | Minutes added to slot start for scheduled_dropoff_time |
| `ocws_wolt_markup_type` | `fixed` or `percentage` |
| `ocws_wolt_markup_value` | Number applied per `markup_type` |
| `ocws_wolt_currency` | ISO 4217, default ILS, used for parcel prices |
| `ocws_wolt_method_id_prefix` | Host shipping-method ID prefix |

Settings are split into **two register_setting groups** because of a
real bug: both forms (Settings tab + Webhook tab) used to POST to
`options.php` with the same group, and WP's loop sets options not in
`$_POST` to null — so saving Webhook tab wiped the Settings tab and
vice versa. Today:
- `ocws_wolt_settings` group → main settings tab
- `ocws_wolt_webhook_settings` group → webhook secret only
- `ocws_wolt_webhook_id` is registered in **no group** because it's
  only ever written via AJAX handlers; registering it would let either
  form clobber it.

Order meta (per-order, prefixed `_ocws_wolt_*`):

| Meta key | Set when |
|---|---|
| `_ocws_wolt_status` | Our internal state: `created` / `failed` |
| `_ocws_wolt_delivery_id` | Wolt's `id` (24 hex chars) |
| `_ocws_wolt_order_reference_id` | Wolt's `wolt_order_reference_id` (used to match webhook events) |
| `_ocws_wolt_wolt_status` | Wolt's courier flow state: `INFO_RECEIVED`, `PICKED_UP`, `DELIVERED`, … |
| `_ocws_wolt_tracking_url` | Customer-facing tracking page |
| `_ocws_wolt_last_error` | Last error string from Wolt if create failed |
| `_ocws_wolt_last_event_at` | mysql timestamp of latest webhook |

Order meta is intentionally NOT removed on uninstall — historical
orders keep their audit trail.

---

## Admin UI

Top-level menu "Wolt Drive" in WP admin, four tabs (Deliveries is the
default landing tab):

1. **Deliveries** — paginated dashboard of every order with a Wolt
   delivery. Status pills colour-coded by Wolt's courier flow. Per-row
   actions: Track (Wolt-cyan primary), Order (WC edit), Cancel
   (only while status is cancellable; opens a modal with reason
   dropdown).
2. **Settings** — General + API connection + Pricing markup + Advanced.
   Test Connection button hits `/delivery-areas` to verify creds.
3. **Webhook** — URL + secret + Generate button + one-click Register /
   Re-register / Unregister against Wolt's webhook API. Status pill
   shows whether currently registered.
4. **Tools** — Quote simulator (manual address + slot → live Wolt price
   + the ISO 8601 scheduled_dropoff_time the trigger would send).

UI uses Wolt's brand cyan `#00C2E8` as primary, with pill-shaped tabs,
12px rounded cards, soft 2-layer shadows, and a 3px cyan focus ring
across every interactive element for keyboard accessibility.

---

## Local dev / deployment

- Local path: `C:\Users\User\PhpstormProjects\delinka\wp-content\plugins\shipping-wolt-plugin`
- Remote: `https://github.com/omerelias/shipping-wolt-plugin` (branch `main`)
- Production: `https://delinka.deliz.co.il` at
  `/home/ranfprhcpmpk/public_html/wp-content/plugins/shipping-wolt-plugin`
- **Deployment is manual** — git push only sends to GitHub. The user
  syncs to production via PhpStorm's deployment tool / FTP / git pull
  on the server. **`gh` CLI is not installed** locally, so PRs are
  opened in the browser via the URL git push prints.
- WP installation also has `wp-content/plugins/oc-woo-shipping/` — a
  separate legacy copy of the host shipping plugin. Both forks share
  the same method-ID prefix and meta keys, so this plugin works with
  either.

For diagnostics, the production site has `WP_DEBUG_LOG` enabled. All
HTTP calls to Wolt are logged behind that flag with tags like
`[OC Wolt SP]` (shipment-promise), `[OC Wolt CD]` (create delivery),
`[OC Wolt Cancel]`.

---

## Open items / known unknowns

Not blockers, but worth being aware of:

- **Parcel price unit** — we send `parcels[i].price.amount` as major
  units (e.g. `12.5`). Unverified whether Wolt expect minor units for
  parcels too (they definitely do for the response `price.amount`).
  If a future create-delivery rejects on parcel price, multiply by 100.
- **Production credentials** — sandbox works end-to-end as of May 2026.
  Wolt's rep said production keys come only after successful staging
  testing — needs to be requested separately.
- **Multi-venue support** — current UI assumes one venue per site.
  Delinka plans a second branch; supporting it cleanly would mean
  per-zone / per-method venue mapping.
- **Cancellation deadline** — Wolt docs say cancel is only valid until
  `order.pickup_started` arrives. We don't yet hide/disable the Cancel
  button based on that event — should we? Currently we hide it once
  status is `DELIVERED` / `CANCELLED` / `FAILED`.
- **ETA delay** — Wolt recommend waiting 90 s after first
  `order.pickup_eta_updated` before showing the ETA to customers as
  "truthful". Not implemented; we'd need an outbound flow to surface
  ETAs to customers, which currently we don't.
- **Address format question to Wolt** — was originally asked, never
  answered, eventually resolved empirically by the error messages.

---

## Extraction history (May 2026)

This repo used to contain the entire "Original Concepts WooCommerce
Advanced Shipping" plugin with the Wolt module nested under
`includes/wolt/`. In May 2026 it was extracted:

- All non-Wolt code deleted from the repo (admin/, public/, templates/,
  includes/* non-wolt, vendor/, composer.json, etc. — 1,400+ files,
  -376k LOC).
- `includes/wolt/*` moved up to `includes/*`.
- New bootstrap `oc-wolt-drive.php` with its own plugin header.
- Carbon replaced with native `DateTime` so the plugin needs no vendor.
- Standalone admin UI built so it doesn't depend on the OC admin page.
- `class_exists` guards added on every class for coexistence with a
  legacy stale copy.

The OC plugin still ships separately (in its own folder, its own
repo) and must remain active for this plugin's price override + slot
scheduling to do anything useful.

---

## When in doubt

1. **Read the file matching the area you're touching.** Headers are
   verbose, including exact Wolt error messages we hit and how we
   resolved them.
2. **Read `debug.log` on the server.** Every Wolt request and response
   is logged with `[OC Wolt …]` tags.
3. **Verify field names against actual Wolt errors**, not their docs.
   The docs were wrong about `street_address`/`postal_code` for
   shipment-promises; the live error said `street`/`post_code` and
   that's what works.
4. **Never trust empty-form behaviour with the WP Settings API.** Group
   forms by which fields they own, or POST will silently nuke fields
   absent from the form.
