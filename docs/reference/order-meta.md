# Order meta reference

Per-order meta keys the plugin writes to track Wolt state. All prefixed
with `_ocws_wolt_` (underscore prefix → hidden from the WC order edit
"Custom fields" panel).

All are read/written via `$order->get_meta()` / `update_meta_data()`,
so they work transparently under WC's High-Performance Order Storage.

> Constants live on `OCWS_Wolt_Delivery_Trigger`. Reference via the
> constant (e.g. `OCWS_Wolt_Delivery_Trigger::META_DELIVERY_ID`)
> rather than the raw string — that way IDE find-usages works.

---

## Meta keys

| Constant | Raw key | Type | Set by | When | What it is |
|---|---|---|---|---|---|
| `META_STATUS` | `_ocws_wolt_status` | string | `Delivery_Trigger::create_for_order` | After every dispatch attempt | Plugin internal state: `'created'` on success, `'failed'` otherwise. Different from Wolt's courier state. |
| `META_DELIVERY_ID` | `_ocws_wolt_delivery_id` | string (24 hex) | `create_for_order` on 201 | Wolt's primary delivery identifier. Used in admin links + as the idempotency key (presence ⇒ skip re-create). |
| `META_WOLT_ORDER_REF` | `_ocws_wolt_order_reference_id` | string (24 hex) | `create_for_order` on 201 | Wolt's `wolt_order_reference_id`. **Different value** from `META_DELIVERY_ID`. Every webhook event references this — the webhook handler looks up the order by it first. |
| `META_WOLT_STATUS` | `_ocws_wolt_wolt_status` | string | `create_for_order` + `Webhook::handle_webhook` | At create, then on every event | Wolt's courier state: `INFO_RECEIVED`, `PICKUP_STARTED`, `PICKED_UP`, `DROPOFF_STARTED`, `DROPOFF_ARRIVAL`, `DELIVERED`, `CANCELLED`, `REJECTED`, `FAILED`. |
| `META_TRACKING_URL` | `_ocws_wolt_tracking_url` | URL string | `create_for_order` | At create | Public Wolt tracking page. Safe to share with the recipient (linked in customer email + thank-you card). |
| `META_TRACKING_ID` | `_ocws_wolt_tracking_id` | string | `create_for_order` | At create | Short tracking code — suitable for inclusion in SMS body (no full URL). |
| `META_PICKUP_ETA` | `_ocws_wolt_pickup_eta` | ISO 8601 timestamp | `create_for_order` + `Webhook::handle_webhook` | At create + on every event with pickup.eta | When the courier is expected to arrive at the venue. |
| `META_DROPOFF_ETA_MIN` | `_ocws_wolt_dropoff_eta_min` | ISO 8601 timestamp | same | same | Earliest estimated dropoff at the customer. |
| `META_DROPOFF_ETA_MAX` | `_ocws_wolt_dropoff_eta_max` | ISO 8601 timestamp | same | same | Latest estimated dropoff. For point-in-time ETAs both min and max equal each other. |
| `META_COST_AMOUNT` | `_ocws_wolt_cost_amount` | float, **major units** | `create_for_order` + webhook | At create + on price refresh | Wolt's actual delivery price after the create. ₪42.00 = 42, not 4200 — we convert from minor units. |
| `META_COST_CURRENCY` | `_ocws_wolt_cost_currency` | ISO 4217 (3 letters) | same | same | Currency for `META_COST_AMOUNT`. |
| `META_DELIVERED_AT` | `_ocws_wolt_delivered_at` | ISO 8601 timestamp | `Webhook::handle_webhook` on `order.dropoff_completed` | When the recipient took possession | Lets you sort / filter on actual delivery time. |
| `META_PICKUP_DISPLAY_NAME` | `_ocws_wolt_pickup_display_name` | string | `create_for_order` | At create | Wolt's own label for the venue (e.g. `"Delinka Test Venue WD"`). Useful for multi-venue UI. |
| `META_CUSTOMER_SUPPORT` | `_ocws_wolt_customer_support` | JSON string | `create_for_order` | At create when Wolt populate it | Wolt's support contact specific to this delivery: `{ url, email, phone_number }`. Often `null` in sandbox. |
| `META_VENUE_ID` | `_ocws_wolt_venue_id` | string | `create_for_order` | At create | The venue ID actually used for THIS delivery — may differ from the configured default after the available-venues resolution kicks in. |
| `META_COURIER_INFO` | `_ocws_wolt_courier_info` | JSON string | `Webhook::handle_webhook` | First event that has `details.courier` | `{ id, vehicle_type }` of the assigned courier. Populates from `order.pickup_started` onwards. |
| `META_COURIER_LAT` | `_ocws_wolt_courier_lat` | float | `Webhook::handle_webhook` on `order.location_updated` | While the courier is moving | Latest known latitude (only when the high-volume location subscription is on). |
| `META_COURIER_LNG` | `_ocws_wolt_courier_lng` | float | same | same | Latest longitude. (Wolt's webhook payload uses `lon` not `lng` — we normalise to `lng` internally.) |
| `META_COURIER_AT` | `_ocws_wolt_courier_location_at` | MySQL timestamp | same | same | When the lat/lng pair last changed. Drives the "Last updated: HH:MM" line on the courier-location modal. |
| `META_LAST_ERROR` | `_ocws_wolt_last_error` | string | `create_for_order` (set on fail, deleted on success) | Failed dispatch attempts | Last error message from Wolt's create-delivery response. Surfaced in the meta box under "Last error" and in the Deliveries dashboard error details. |
| _unprefixed_ `_ocws_wolt_last_event_at` | (same) | MySQL timestamp | `Webhook::handle_webhook` | On every event | Lets you tell at-a-glance how recently Wolt sent us something. Useful when diagnosing "the webhook didn't fire". |

---

## Lifecycle: when each populates

```
                Create attempt ──┐
                                  │
            success (201)         │     failed
                ▼                 │       ▼
          STATUS = 'created'      │   STATUS = 'failed'
          DELIVERY_ID              │   LAST_ERROR = '…'
          WOLT_ORDER_REF           │
          WOLT_STATUS = INFO_RCV   │
          TRACKING_URL             │
          TRACKING_ID              │
          PICKUP_ETA               │
          DROPOFF_ETA_MIN/MAX      │
          COST_AMOUNT              │
          COST_CURRENCY            │
          PICKUP_DISPLAY_NAME      │
          CUSTOMER_SUPPORT         │
          VENUE_ID                 │
                                   │
        Webhook events ────────────┘
                ▼
          WOLT_STATUS (refreshed on every status-changing event)
          PICKUP_ETA / DROPOFF_ETA_MIN/MAX (refreshed on *_eta_updated)
          COURIER_INFO (from order.pickup_started)
          COURIER_LAT/LNG/AT (from order.location_updated, when enabled)
          DELIVERED_AT (from order.dropoff_completed / order.delivered)
          last_event_at (every event)
```

---

## Convenience helpers

| Helper | What it returns | Where to use |
|---|---|---|
| `OCWS_Wolt_Delivery_Trigger::format_local_time( $iso, $format = '' )` | The ISO 8601 timestamp converted to the site's timezone using WP's time format | Anywhere you want to display `META_PICKUP_ETA` etc. as `"17:00"` instead of `"2026-05-26T14:00:00+00:00"` |
| `OCWS_Wolt_Delivery_Trigger::get_dropoff_eta_display( $order )` | `"HH:MM"` or `"HH:MM – HH:MM"` from `META_DROPOFF_ETA_MIN/MAX` | Meta box, Deliveries dashboard ETA column |

---

## What gets removed on uninstall

`uninstall.php` clears only `ocws_wolt_*` **options**. **Order meta is
kept** on purpose — historical orders retain their Wolt audit trail
even after the plugin is removed.

If you genuinely want to nuke the meta too, run:

```sql
DELETE FROM wp_postmeta WHERE meta_key LIKE '_ocws_wolt_%';
-- or for HPOS:
DELETE FROM wp_wc_orders_meta WHERE meta_key LIKE '_ocws_wolt_%';
```

---

## See also

- [Settings reference](settings.md) — the per-installation options.
- [Webhook events](webhook-events.md) — which events refresh which
  meta keys.
- [Wolt API mapping](wolt-api-mapping.md) — which Wolt response field
  populates which meta key.
