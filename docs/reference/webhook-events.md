# Webhook events

The inbound side: events Wolt POST to
`https://YOUR-SITE/wp-json/ocws-wolt/v1/webhook`, what's in them, what
the plugin does with each.

For the outbound direction (calls *we* make to Wolt) see
[Wolt API mapping](wolt-api-mapping.md).

---

## Transport

- **Method**: `POST`
- **URL**: `/wp-json/ocws-wolt/v1/webhook` on the merchant's site
- **Body**: a JWT signed HS256 with the **`client_secret`** the
  merchant provided at registration time. The plugin's `verify_jwt_hs256()`
  validates the signature with `hash_equals()` (time-constant), then
  decodes the claims as the event payload.
- **Permission callback**: `__return_true` — the JWT signature *is*
  the auth. No additional capability check (Wolt's servers can't carry
  WP cookies).

If the verifier rejects the signature, the route returns
`{ ok: false, error: "Invalid JWT signature" }` with HTTP 401.

---

## Payload shape

Per Wolt's docs (and observed in practice):

```json
{
  "type":          "order.received",
  "dispatched_at": "2026-05-26T15:00:00.000Z",
  "details": {
    "id":                          "<event id>",
    "wolt_order_reference_id":     "<delivery's order ref>",
    "merchant_order_reference_id": "17873",
    "status":                      "INFO_RECEIVED",
    "pickup":   { "eta": "2026-05-26T14:42:00+00:00" },
    "dropoff":  {
      "eta":          { "min": "...", "max": "..." },
      "completed_at": "..."
    },
    "courier":  { "id": 12345, "vehicle_type": "car" },
    "price":    { "amount": 4200, "currency": "ILS" },
    "courier_location": { "lat": 32.07, "lon": 34.78 }
  }
}
```

`details.dropoff.eta` is `{ min, max }` here (versus a single ISO
string in the create-delivery response). The plugin handles both
shapes.

---

## Order lookup

For each incoming event the plugin tries to find the WC order it
belongs to, in this order:

1. `META_WOLT_ORDER_REF` matching `details.wolt_order_reference_id`
2. `META_DELIVERY_ID` matching `details.id` / `details.delivery_id`
3. Treat `details.merchant_order_reference_id` as a numeric WC order id

If none of those resolve, the route returns
`{ ok: true, note: "Order not found" }` with HTTP 200 (Wolt see the
2xx and don't retry; we don't want them hammering forever).

---

## Event types

The plugin is auto-subscribed to most of these when you register the
webhook. Italicised ones are opt-in.

| `type` | What it means | Meta keys updated | Side effects |
|---|---|---|---|
| `order.received` | Wolt accepted the delivery | `META_WOLT_STATUS = "INFO_RECEIVED"` | Order note |
| `order.rejected` | Wolt rejected the delivery | `META_WOLT_STATUS = "REJECTED"` | Order note |
| `order.pickup_eta_updated` | Wolt has a new estimate for when the courier reaches the venue | `META_PICKUP_ETA` (refresh) | (no extra note beyond the standard event note) |
| `order.pickup_arrival` | Courier within ~150m of the venue | (status refresh only) | Order note |
| `order.pickup_started` | Courier accepted the pickup task | `META_COURIER_INFO` populates if not already | Order note |
| `order.picked_up` | Courier left the venue with the order | (status) | Order note |
| `order.dropoff_eta_updated` | New estimate for arrival at the customer | `META_DROPOFF_ETA_MIN/MAX` (refresh) | (no extra note) |
| `order.dropoff_started` | Courier heading to dropoff | (status) | Order note |
| `order.dropoff_arrival` | Courier within ~150m of the customer | (status) | Order note |
| `order.dropoff_completed` | Order handed off | `META_DELIVERED_AT` | Order note |
| `order.delivered` | Final acknowledgement (sometimes interleaved with `dropoff_completed`) | `META_WOLT_STATUS = "DELIVERED"` | Order note |
| `order.customer_no_show` | Customer not present at the address | (status) | Order note |
| `order.handshake_delivery` | Delivery handshake event (when feature enabled) | (status) | Order note |
| _`order.location_updated`_ | Live courier GPS — fires every few seconds while in transit | `META_COURIER_LAT/LNG/AT` | **No** order note (would be hundreds per delivery) |

The "Subscribe to courier location updates" setting toggles whether
the webhook subscription requests `order.location_updated` from Wolt.

---

## What we always do, regardless of type

For every event that passes signature verification + order resolution:

- Update `_ocws_wolt_last_event_at` to the current MySQL timestamp.
- If `details.status` is present, update `META_WOLT_STATUS`.
- If `details.pickup.eta` is present, update `META_PICKUP_ETA`.
- If `details.dropoff.eta` is present (`{min,max}` or single ISO),
  update `META_DROPOFF_ETA_MIN/MAX`.
- If `details.dropoff.completed_at` is present, update `META_DELIVERED_AT`.
- If `details.price.amount` is present, update
  `META_COST_AMOUNT/CURRENCY` (Wolt occasionally re-quotes mid-flight).
- If `details.courier` is non-empty, update `META_COURIER_INFO`.
- If `details.courier_location.{lat,lon}` is present, update
  `META_COURIER_LAT/LNG/AT`.

This means a single late `order.delivered` event "catches up" all
the meta in one shot, even if earlier events were dropped.

---

## Order notes

For every non-location event we add a WC order note in the form:

```
Wolt: order.dropoff_completed. <optional message from details.message>
```

Operators can scan the order's note list to see the full courier
timeline. Location events are skipped on purpose (would spam the
notes panel).

---

## Replay safety

The plugin doesn't track event IDs, so duplicate events from Wolt
(retries after a transient error) will re-add the order note. They
will NOT re-create the delivery — the dispatcher's idempotency check
(`META_DELIVERY_ID` presence) is independent.

If duplicate notes ever become a problem, the right hook to add is
deduplication by `details.id` in `Webhook::handle_webhook`.

---

## Verifying the endpoint is reachable

```bash
curl -X POST "https://YOUR-SITE/wp-json/ocws-wolt/v1/webhook" \
  -H "Content-Type: application/json" \
  -d '{"hello":"world"}'
```

Expected: `{"ok":false,"error":"Invalid JWT signature"}` (HTTP 401).

That tells you:
- The route is alive
- Signature verification is on (because a `webhook_secret` is configured)
- A real Wolt request *would* be accepted if signed correctly

If you get a different response, see [Troubleshoot](../how-to/troubleshoot.md).

---

## See also

- [Wolt API mapping](wolt-api-mapping.md) — outbound side.
- [Order meta reference](order-meta.md) — which meta keys these
  events touch.
- [Architecture](architecture.md) — where `OCWS_Wolt_Webhook` sits in
  the bigger picture.
