# Settings reference

Every WordPress option the plugin owns, plus the per-OC-group overrides
it can read.

All options live in the standard `wp_options` table (or its multisite
equivalent). They're cleared by `uninstall.php` when the plugin is
deleted; **per-order meta keys are kept** so historical orders retain
their audit trail.

> Settings UI is at **WP admin → Wolt Drive**. Most options are
> editable on the Settings or Webhook tab; a couple are written only
> via AJAX (rotation flows).

---

## How settings are organised

The plugin uses **two** WP settings groups, by design:

| Group | What's in it | Owned by which admin form |
|---|---|---|
| `ocws_wolt_settings` | Every option below **except** the webhook secret and webhook ID | Settings tab `<form>` |
| `ocws_wolt_webhook_settings` | The webhook secret only | Webhook tab `<form>` |

Reason: WP's `options.php` POST handler iterates every option in the
submitted group and writes `null` for any not present in `$_POST`.
Submitting the webhook secret used to clear every other setting. Two
groups → each form only touches its own options.

`ocws_wolt_webhook_id` and `ocws_wolt_dispatch_api_key` aren't in any
group — they're saved via AJAX only, so they survive both forms.

---

## Plugin-wide options

All in the `ocws_wolt_*` namespace.

| Option key | Type / format | Default | Sanitisation | Description |
|---|---|---|---|---|
| `ocws_wolt_enabled` | `'1'` or `''` | `''` (off) | `sanitize_bool` | Master switch. When off, the price-override filter and auto-dispatch hooks short-circuit. |
| `ocws_wolt_api_url` | URL string | sandbox URL | `esc_url_raw` | Wolt API base URL. Sandbox `https://daas-public-api.development.dev.woltapi.com`; production `https://daas-public-api.wolt.com`. No trailing slash needed. |
| `ocws_wolt_api_key` | string | `''` | `sanitize_text_field` | Bearer token from Wolt. Sent as `Authorization: Bearer …` on every outbound call. Stored plain in WP options — protect DB access. |
| `ocws_wolt_venue_id` | 24-char hex | `''` | `sanitize_text_field` | Wolt venue identifier (path param for `/v1/venues/{venue_id}/*`). Used as the **default** for the quote; auto-resolved per-order via available-venues at dispatch time. |
| `ocws_wolt_merchant_id` | 24-char hex | `''` | `sanitize_text_field` | Wolt merchant identifier (path param for `/merchants/{merchant_id}/*` — webhook CRUD, delivery-areas, available-venues). |
| `ocws_wolt_pickup_address` | string | `''` (falls back to WC store address) | `sanitize_text_field` | Default pickup address used in `pickup.location.formatted_address`. Per-delivery, available-venues may override this. |
| `ocws_wolt_trigger_status` | WC status key (`wc-processing` etc.) or `''` | `'wc-processing'` | `sanitize_key` | Order status that auto-creates a Wolt delivery. Empty string → disabled (manual dispatch only). |
| `ocws_wolt_dispatch_offset_minutes` | integer ≥ 0 | `'30'` | `absint` | Minutes added to slot start to form `scheduled_dropoff_time` (slot 16:00–19:00 + offset 30 = 16:30). |
| `ocws_wolt_markup_type` | `'fixed'` or `'percentage'` | `'fixed'` | `sanitize_markup_type` | How `markup_value` is applied to the Wolt quote shown to the customer. |
| `ocws_wolt_markup_value` | numeric string | `'0'` | `sanitize_float` | Fixed amount in store currency, or percentage (e.g. `10` = +10%). |
| `ocws_wolt_currency` | ISO 4217 (3 letters) | `'ILS'` | `sanitize_currency` | Currency used for `parcels[].price.currency` in create-delivery. |
| `ocws_wolt_method_id_prefix` | string | `oc_woo_advanced_shipping_method` | `sanitize_text_field` | Shipping method ID prefix that the plugin recognises as eligible for Wolt dispatch. Match by `strpos($rate_id, $prefix) === 0`. |
| `ocws_wolt_webhook_secret` | string | `''` | `sanitize_text_field` | Shared HS256 secret for inbound webhook verification. Provided to Wolt when registering. Rotate by Generate → Save → Re-register. |
| `ocws_wolt_webhook_id` | string | `''` | (none — set via AJAX) | The id Wolt return when we register the webhook. Used to PATCH/DELETE later. |
| `ocws_wolt_language` | 2-letter ISO 639-1 or `''` | `''` (auto-detect) | `sanitize_language` | Language hint sent in the **shipment-promises** body (`language` field). Empty = auto-detect from `determine_locale()`. |
| `ocws_wolt_age_check_18` | `'1'` or `''` | `''` (off) | `sanitize_bool` | When on, every parcel sent to create-delivery gets `dropoff_restrictions: "age_check_18"` — Wolt courier must verify ID before handoff. |
| `ocws_wolt_subscribe_location` | `'1'` or `''` | `''` (off) | `sanitize_bool` | Opt-in to the noisy `order.location_updated` webhook event. Re-register the webhook after toggling so Wolt know. |
| `ocws_wolt_dispatch_api_key` | string | `''` | (none — set via AJAX) | Bearer token for the public `POST /ocws-wolt/v1/dispatch` endpoint. Empty = endpoint returns 503. Rotate by Generate. |

---

## Per-OC-group overrides

The OC Advanced Shipping plugin organises deliveries into "shipping
groups" (zones). The Wolt plugin can optionally use different venue /
pickup / behaviour per group, falling back to the global option when
the per-group value is empty.

| Option pattern | Equivalent global | Effect |
|---|---|---|
| `ocws_group{N}_wolt_venue_id` | `ocws_wolt_venue_id` | Overrides the venue ID used in the quote when the customer's group resolves to N. |
| `ocws_group{N}_wolt_pickup_address` | `ocws_wolt_pickup_address` | Overrides the formatted pickup address sent to Wolt for orders in group N. |
| `ocws_group{N}_wolt_disable_price_override` | (none) | When `'1'`, the price-override filter skips calling Wolt entirely for group N — the host plugin's default rate stays. Useful for groups where you want to keep flat shipping. |

Read via:

```php
OCWS_Wolt_Settings::get_effective_venue_id_for_group( $group_id );
OCWS_Wolt_Settings::get_effective_pickup_address_for_group( $group_id );
OCWS_Wolt_Settings::is_wolt_price_override_disabled_for_group( $group_id );
```

Group ID is resolved by:

```php
OCWS_Wolt_Settings::resolve_group_id_from_destination( $destination );   // in price-override
OCWS_Wolt_Settings::resolve_group_id_from_order( $order );               // in delivery-trigger
```

Both lean on the host plugin's `ocws_get_group_id_by_city()` helper
and tolerate the OC tables being absent.

---

## Settings classes & methods reference

Quick map from option key to the getter you'd call from PHP:

| Option | Getter |
|---|---|
| `ocws_wolt_enabled` | `OCWS_Wolt_Settings::is_enabled()` |
| `ocws_wolt_trigger_status` | `get_trigger_status()` |
| `ocws_wolt_dispatch_offset_minutes` | `get_dispatch_offset_minutes()` |
| `ocws_wolt_markup_type` | `get_markup_type()` |
| `ocws_wolt_markup_value` | `get_markup_value()` |
| `ocws_wolt_api_url` | (via `OCWS_Wolt_Api::get_api_url()`) |
| `ocws_wolt_api_key` | (via `OCWS_Wolt_Api::get_api_key()`) |
| `ocws_wolt_venue_id` | `get_venue_id()` |
| `ocws_wolt_merchant_id` | `get_merchant_id()` |
| `ocws_wolt_pickup_address` | `get_pickup_address()` (with WC store fallback) |
| `ocws_wolt_webhook_secret` | `get_webhook_secret()` |
| `ocws_wolt_webhook_id` | `get_webhook_id()` / `set_webhook_id()` |
| `ocws_wolt_currency` | `get_currency()` (with WC fallback) |
| `ocws_wolt_method_id_prefix` | `get_method_id_prefix()` |
| `ocws_wolt_language` | `get_language()` (with `determine_locale` fallback) |
| `ocws_wolt_age_check_18` | `is_age_check_18_enabled()` |
| `ocws_wolt_subscribe_location` | `is_location_subscription_enabled()` |
| `ocws_wolt_dispatch_api_key` | `get_dispatch_api_key()` / `set_dispatch_api_key()` |

`apply_markup( $cost )` applies the markup to a quote returned from
Wolt.

---

## What gets removed on uninstall

`uninstall.php` deletes every `ocws_wolt_*` option listed above.

**It does NOT delete:**

- Per-order meta keys (`_ocws_wolt_*`) — kept so historical orders
  retain a Wolt audit trail
- Per-OC-group overrides (`ocws_group{N}_wolt_*`) — owned by the host
  shipping plugin's options table, not ours
- Webhooks registered at Wolt's side — you have to unregister via the
  admin or Wolt's API before deleting the plugin if you want them gone

---

## See also

- [Order meta reference](order-meta.md) — the per-order side.
- [Wolt API mapping](wolt-api-mapping.md) — which option flows into
  which outbound HTTP field.
- [Install & configure](../how-to/install-and-configure.md) — narrative
  walk-through of these options in setup order.
