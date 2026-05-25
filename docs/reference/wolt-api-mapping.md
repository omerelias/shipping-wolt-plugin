# Wolt API mapping

Every Wolt endpoint the plugin calls — when, where in the code,
exact request body, what we extract from the response. This is the
file to consult when a Wolt error mentions a specific field.

For the **inbound** direction (webhooks Wolt sends us), see
[Webhook events](webhook-events.md).

---

## Endpoint summary

| Wolt endpoint | HTTP | Plugin caller | When |
|---|---|---|---|
| `/v1/venues/{venue_id}/shipment-promises` | POST | `OCWS_Wolt_Api::get_shipment_promise` | Every checkout `package_rates` evaluation |
| `/merchants/{merchant_id}/available-venues` | POST | `OCWS_Wolt_Api::get_available_venues` | Just before each create-delivery |
| `/v1/venues/{venue_id}/deliveries` | POST | `OCWS_Wolt_Api::create_delivery` | Auto-dispatch / manual button / dispatch API |
| `/order/{wolt_order_reference_id}/status/cancel` | PATCH | `OCWS_Wolt_Api::cancel_delivery` | Admin Cancel button |
| `/merchants/{merchant_id}/delivery-areas` | GET | `OCWS_Wolt_Api::get_delivery_areas` | Settings "Test connection" button |
| `/v1/merchants/{merchant_id}/webhooks` | POST | `OCWS_Wolt_Api::register_webhook` | Webhook "Register" button |
| `/v1/merchants/{merchant_id}/webhooks/{id}` | GET | `OCWS_Wolt_Api::get_webhook` | (helper, not currently surfaced in UI) |
| `/v1/merchants/{merchant_id}/webhooks/{id}` | DELETE | `OCWS_Wolt_Api::delete_webhook` | Webhook "Unregister" button |

All requests carry `Authorization: Bearer {api_key}` and
`Content-Type: application/json`.

---

## 1. shipment-promises — live quote

`POST /v1/venues/{venue_id}/shipment-promises`

### Why we call it

When the customer is at checkout and WC evaluates shipping rates, the
plugin overrides the host method's price with Wolt's quote for the
specific dropoff address.

### Caller

`OCWS_Wolt_Price_Override::filter_package_rates()` (hooked to
`woocommerce_package_rates` at priority 20) →
`OCWS_Wolt_Api::get_shipment_promise( $destination )`.

### Request body

**FLAT** at the root — NOT nested under `address` / `location`.
This is the most subtle Wolt gotcha; the docs are misleading here.

```json
{
  "street":    "מבצע קדש 67",
  "city":      "תל אביב-יפו",
  "post_code": "",
  "lat":       32.1126798,
  "lon":       34.8227723,
  "language":  "he"
}
```

| Field | Source | Notes |
|---|---|---|
| `street` | OC plugin's `street` + `house_num`, fallback to `address_1` / `address` | Concatenated as `"<street> <house_num>"`. |
| `city` | `city_name` if set, else `city` | Both come from the OC plugin's address merger. |
| `post_code` | `postcode` | Usually empty in Israeli flows. Omitted from body when empty. |
| `lat`/`lon` | `address_coords.lat` / `address_coords.lng` | **Wolt uses `lon`, not `lng`, at this endpoint root.** Sent only when both present. |
| `language` | `OCWS_Wolt_Settings::get_language()` | Auto-detected from `determine_locale()` if the option is empty. |

Validator rule: "**`post_code` OR (`street` AND `city`)** must be at
the root". The plugin short-circuits (no API call) when neither
combination is satisfied — saves log spam on every keystroke before
the customer has typed an address.

### Response we use

```json
{ "price": { "amount": 4200, "currency": "ILS" }, ... }
```

`price.amount` is in **minor currency units** (agorot / cents). Divide
by 100. The plugin returns `{ success: true, cost: 42.0, raw: {...} }`.

`fallback` keys: legacy responses with just `amount` at root are also
accepted.

---

## 2. available-venues — pick the right branch

`POST /merchants/{merchant_id}/available-venues`

### Why we call it

Wolt's recommendation: instead of always dispatching from the configured
default venue, at delivery-creation time ask Wolt which of the
merchant's venues is best for *this* dropoff. Wolt return them ranked
best-first.

### Caller

`OCWS_Wolt_Delivery_Trigger::resolve_venue_for_payload()` — runs once
inside `create_for_order()`, just before the actual create call.

### Request body

```json
{
  "dropoff": {
    "location": {
      "formatted_address": "משה שמיר 3, תל אביב-יפו, IL",
      "coordinates":       { "lat": 32.1230118, "lon": 34.8220186 }
    }
  },
  "scheduled_dropoff_time": "2026-05-26T18:07:00+03:00"
}
```

| Field | Source |
|---|---|
| `dropoff.location.formatted_address` | Flattened from `dropoff.location.address.{street,city,post_code,country}` in the create-delivery payload we just built |
| `dropoff.location.coordinates` | Same as create-delivery's lat/lng under `dropoff.location.lat/lng` (note: `lon` not `lng` here) |
| `scheduled_dropoff_time` | Same as `dropoff.options.scheduled_time` from the create-delivery payload, if present |

### Response we use

Array of venue suggestions:

```json
[
  {
    "pickup": {
      "venue_id":      "69faed18542343b282d2887c",
      "name":          [{"lang":"en","value":"Delinka Test Venue WD"}],
      "location": {
        "formatted_address": "המלאכה 8, תל אביב",
        "coordinates": { "lat": 32.0663043, "lon": 34.7858381 }
      }
    },
    "fee":          { "amount": 4200, "currency": "ILS" },
    "pre_estimate": { "pickup_minutes": 10, "delivery_minutes": 25, "total_minutes": { "min": 60, "mean": 65, "max": 70 } }
  }
]
```

The plugin picks **`venues[0]`** (Wolt's top choice) and uses its
`venue_id` for the upcoming create-delivery. If the call fails or
returns an empty list, falls back silently to the configured default
venue.

### Failure mode

Silent. On error / empty result the dispatcher continues with the
configured default — orders are never blocked because of an
available-venues hiccup.

---

## 3. create-delivery — the actual dispatch

`POST /v1/venues/{venue_id}/deliveries`

### Why we call it

This creates the Wolt delivery for real. After the 201 response, a
courier is dispatched (in production — sandbox simulates the rest of
the flow but doesn't physically send anyone).

### Caller

`OCWS_Wolt_Delivery_Trigger::create_for_order( $order, $manual )`
called from:

- `on_status_changed` / `on_new_order` (auto path, when status matches)
- Order meta-box "Create Wolt delivery now" button (manual path)
- `OCWS_Wolt_Dispatch_Api::handle` (external REST trigger)

### Request body

```json
{
  "merchant_order_reference_id": "17873",
  "order_number":                "873",
  "pickup": {
    "location": { "formatted_address": "קהילת סלוניקי 7, תל אביב יפו" }
  },
  "dropoff": {
    "location": {
      "address": {
        "street":    "מבצע קדש 67",
        "city":      "תל אביב-יפו",
        "post_code": "",
        "country":   "IL"
      },
      "lat": 32.11,
      "lng": 34.82
    },
    "comment": "Floor: 13. Apartment: 64. Door code: 1174",
    "options": {
      "is_no_contact":  false,
      "scheduled_time": "2026-05-26T18:07:00+03:00"
    }
  },
  "recipient": {
    "name":         "ג'ורג' שופאני",
    "phone_number": "0544515151",
    "email":        "george@example.com"
  },
  "parcels": [
    {
      "description": "דנבר מינוט",
      "identifier":  "17873-349",
      "count":       2,
      "price":       { "amount": 108, "currency": "ILS" }
    }
  ]
}
```

### Key field-shape decisions

These are the ones that took the most back-and-forth with Wolt to
get right:

- `dropoff.location.address` is a **structured object**, NOT a
  formatted string (unlike shipment-promises where it's flat at root).
- `dropoff.location.lat/lng` (yes `lng`, not `lon` — Wolt's inconsistency).
- `dropoff.comment` is **singular**, not `comments`.
- `dropoff.options.scheduled_time` is inside `dropoff.options`, **NOT**
  at the body root (we used to send `scheduled_dropoff_time` at root —
  Wolt asked us to stop).
- `parcels[].count` carries the quantity (Wolt's per-item field). We
  used to duplicate the parcel N times; the new shape is one parcel
  per product line with `count: <qty>`.
- `parcels[].price.amount` stays in major units here (despite the
  response coming back in minor units for delivery cost). Verified
  working with Wolt sandbox.
- `order_number` is the **last 3 digits** of the WC order id, padded
  with leading zeros — Wolt asked for short numbers for the courier
  app.
- `language` does NOT appear in this body (Wolt asked us to move it to
  shipment-promises only).
- `dropoff.options.is_no_contact` reflects the `ocws_leave_at_the_door`
  meta from checkout (a proper boolean rather than just text in the
  comment).
- `parcels[].dropoff_restrictions: "age_check_18"` appears on every
  parcel when the global 18+ option is on.

### Response we extract

```json
{
  "id":                      "6a12d24c4901a5b3cc70b31d",
  "wolt_order_reference_id": "6a12d24dcc7053c3fad7a6a5",
  "status":                  "INFO_RECEIVED",
  "tracking":                { "id": "EGR1qpwbPABjFoh07feU", "url": "https://daas-track..." },
  "pickup":                  { "display_name": "Delinka Test Venue WD", "eta": "...", ... },
  "dropoff":                 { "eta": "...", ... },
  "price":                   { "amount": 4200, "currency": "ILS" },
  "customer_support":        { "url": null, "email": null, "phone_number": null }
}
```

→ Maps to the order-meta keys documented in
[Order meta reference](order-meta.md).

Two gotchas:
- **`tracking.url` is nested**, NOT a flat `tracking_url`. Our parser
  tries `tracking.url` first, then falls back to flat keys.
- **`id` ≠ `wolt_order_reference_id`** — both look identical (24 hex
  chars) but every webhook event references the latter. We save both
  in separate meta keys; the webhook handler looks up the order by
  `wolt_order_reference_id` first.

---

## 4. Cancel delivery

`PATCH /order/{wolt_order_reference_id}/status/cancel`

Notice: NOT scoped to a venue. Uses the `wolt_order_reference_id`
returned at create.

### Body

```json
{ "reason": "customer_request" }
```

`reason` is **required**. The admin Cancel modal offers four canned
reasons (`customer_request`, `merchant_out_of_stock`, `wrong_address`,
`other`).

### When it works

Only until Wolt accepts the pickup task — typically up to ~the time
the courier hits the venue. After that Wolt returns 4xx and the
operator has to contact Wolt support.

---

## 5. Delivery areas — sanity check

`GET /merchants/{merchant_id}/delivery-areas`

Read-only. Used solely as a "are these credentials valid?" probe. The
admin's "Test connection" button calls it; the returned polygons are
ignored beyond counting how many came back for the UI message.

---

## 6. Webhook CRUD

| HTTP | Path | Body | Use |
|---|---|---|---|
| POST | `/v1/merchants/{merchant_id}/webhooks` | `{ callback_url, client_secret, callback_config, disabled: false }` | Register / Re-register |
| GET | `/v1/merchants/{merchant_id}/webhooks/{id}` | — | Verify current state (helper, no UI yet) |
| DELETE | `/v1/merchants/{merchant_id}/webhooks/{id}` | — | Unregister |

`callback_config.exponential_retry_backoff` defaults to
`{ exponent_base: 5, max_retry_count: 3 }` — overridable per call but
no UI exposes it today.

Note the version prefix: `/v1/merchants/...` for webhooks but
`/merchants/...` (no `/v1/`) for delivery-areas and available-venues.
Wolt's choice, not ours.

---

## See also

- [Webhook events](webhook-events.md) — the inbound side.
- [Order meta reference](order-meta.md) — where the response fields
  land.
- [Settings reference](settings.md) — which option each request field
  comes from.
