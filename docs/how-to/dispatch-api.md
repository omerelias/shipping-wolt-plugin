# OC Wolt Drive — Dispatch API

A single REST endpoint that lets you trigger Wolt Drive dispatch for a
WooCommerce order programmatically and read back every field the plugin
persists about the resulting delivery.

This is the same flow the WP admin "Create Wolt delivery now" button uses —
including the automatic venue selection via Wolt's `/available-venues`
endpoint — just exposed over HTTP so external systems (CRMs, automations,
internal tools, n8n / Zapier / Make scenarios, mobile clients) can drive it.

---

## Base URL

```
https://YOUR-SITE/wp-json/ocws-wolt/v1/dispatch
```

For the Delinka production site that is:

```
https://delinka.deliz.co.il/wp-json/ocws-wolt/v1/dispatch
```

Method: **`POST`**
Content-Type: **`application/json`**

---

## Authentication

A long-lived bearer token, generated once in the WP admin and reused
forever (until rotated).

1. Log in to the WordPress admin.
2. Go to **Wolt Drive → Webhook** → scroll to **Dispatch API (internal)**.
3. Click **Generate**.
4. Copy the token. Store it as a secret in your client (env var, secrets
   manager, password manager — never commit it to git).

Send it on every request:

```
Authorization: Bearer <YOUR_TOKEN>
```

Clicking **Generate** again rotates the token. The previous token stops
working the moment the new one is saved.

---

## Request

### Body

JSON. Identify the order by **either** of these fields (use `orderId` when
you have it — it's the WC primary key and is unambiguous):

| Field | Type | Required | Notes |
|---|---|---|---|
| `orderId` | integer | one of | WooCommerce internal order ID. |
| `orderNumber` | string | one of | WooCommerce order number. Often the same as `orderId`, but differs when a sequential-numbering plugin is in use. |

```json
{ "orderId": 17875 }
```

or

```json
{ "orderNumber": "17875" }
```

### Headers

```
Authorization: Bearer <YOUR_TOKEN>
Content-Type: application/json
```

---

## Response

### 200 — success

The response is **idempotent**: if the order already has a Wolt delivery,
the endpoint skips the create call and returns the current state with
`"created": false`. Otherwise it dispatches the delivery and returns
`"created": true` once Wolt have accepted (HTTP 201 from Wolt's side).

```json
{
  "success": true,
  "created": true,
  "order": {
    "orderId": 17875,
    "orderNumber": "17875",
    "internalStatus": "created",
    "woltStatus": "INFO_RECEIVED",
    "deliveryId": "6a1301dd31d37106234f271a",
    "woltOrderReferenceId": "6a1301ddea3a5a7a06902c20",
    "tracking": {
      "url": "https://daas-track.development.dev.woltapi.com/s/OFyY99u2KeVKzwWrDL3B",
      "id":  "OFyY99u2KeVKzwWrDL3B"
    },
    "venue": {
      "id": "69faed18542343b282d2887c",
      "displayName": "Delinka Test Venue WD"
    },
    "etas": {
      "pickup":       "2026-05-25T10:42:00+00:00",
      "dropoffMin":  "",
      "dropoffMax":  "",
      "deliveredAt": ""
    },
    "cost": {
      "amount":   42,
      "currency": "ILS"
    },
    "courier":          null,
    "customerSupport": { "url": null, "email": null, "phoneNumber": null },
    "lastError":       ""
  }
}
```

### Response fields

| Path | Type | Description |
|---|---|---|
| `success` | boolean | Always `true` for 2xx responses. |
| `created` | boolean | `true` if a new Wolt delivery was created on this call. `false` if one already existed (idempotent return). |
| `order.orderId` | integer | WooCommerce order id. |
| `order.orderNumber` | string | WC order number (may differ from id with sequential-numbering plugins). |
| `order.internalStatus` | string | Plugin state: `"created"` or `"failed"`. |
| `order.woltStatus` | string | Wolt's courier state. Progresses over time via webhooks: `INFO_RECEIVED` → `PICKUP_STARTED` → `PICKED_UP` → `DROPOFF_STARTED` → `DROPOFF_ARRIVAL` → `DELIVERED`. May also be `CANCELLED` / `REJECTED`. |
| `order.deliveryId` | string | Wolt's internal delivery id (24 hex chars). |
| `order.woltOrderReferenceId` | string | Wolt order reference id — what webhook events reference. Different value from `deliveryId`. |
| `order.tracking.url` | string | Public customer-facing tracking page. Safe to share with the recipient. |
| `order.tracking.id` | string | Short tracking code (suitable for SMS). |
| `order.venue.id` | string | Wolt venue id the delivery was dispatched from (auto-selected by Wolt's `/available-venues`). |
| `order.venue.displayName` | string | Wolt's own label for the venue (e.g. `"Delinka Test Venue WD"`). |
| `order.etas.pickup` | ISO 8601 string | Estimated arrival of the courier at the venue. |
| `order.etas.dropoffMin` | ISO 8601 string | Earliest estimated arrival at the customer. Empty until Wolt produce a dropoff ETA. |
| `order.etas.dropoffMax` | ISO 8601 string | Latest estimated arrival at the customer. |
| `order.etas.deliveredAt` | ISO 8601 string | Set after the `order.dropoff_completed` webhook fires. Empty before that. |
| `order.cost.amount` | number | Wolt's delivery price in **major currency units** (e.g. `42` = ₪42.00). Already converted from the agorot Wolt return on the wire. |
| `order.cost.currency` | string | ISO 4217 (`ILS`, `USD`, etc.). |
| `order.courier` | object \| null | `{ id, vehicleType }` once a courier has been assigned (populated by webhook events from `order.pickup_started` onwards). |
| `order.customerSupport` | object | `{ url, email, phoneNumber }` — Wolt's support contact for this specific delivery, if they echo it back. Often null in sandbox. |
| `order.lastError` | string | Last error message from Wolt, when applicable. Empty on success. |

ETAs are **live** — they get refreshed on every Wolt webhook event. Calling
this endpoint again later for the same order returns the freshest values
(`"created": false`).

---

## Error responses

| HTTP | `error` code | Meaning | What to do |
|---|---|---|---|
| `401` | `ocws_wolt_dispatch_unauthorized` | Missing or invalid `Authorization` header. | Check the bearer token. If it was rotated, fetch the new one from WP admin. |
| `404` | `order_not_found` | No WC order matches the id / number you sent. | Verify the value exists. |
| `502` | `wolt_create_failed` | Wolt themselves rejected the create. The `message` field carries Wolt's exact error (often a JSON string with a `detail` array). | Inspect the message. Common causes: address outside delivery area, missing pickup, invalid recipient phone. |
| `503` | `ocws_wolt_dispatch_disabled` | No token configured at all — the API is effectively off. | Click **Generate** in WP admin → Wolt Drive → Webhook. |

Error body shape:

```json
{
  "code": "ocws_wolt_dispatch_unauthorized",
  "message": "Invalid bearer token.",
  "data": { "status": 401 }
}
```

(Or, for 502:)

```json
{
  "success": false,
  "error":   "wolt_create_failed",
  "message": "{\"detail\":[{\"type\":\"value_error\",\"msg\":\"...\"}]}"
}
```

---

## Examples

### curl

```bash
curl -X POST "https://delinka.deliz.co.il/wp-json/ocws-wolt/v1/dispatch" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"orderId": 17875}'
```

Just want to read state without creating? Call the same endpoint — if the
delivery exists you'll get `"created": false` plus the current data. (No
separate GET endpoint exists today; if you need one, ask the plugin
maintainer.)

### JavaScript (fetch)

```javascript
async function dispatchWoltDelivery(orderId) {
  const res = await fetch('https://delinka.deliz.co.il/wp-json/ocws-wolt/v1/dispatch', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${process.env.OCWS_WOLT_TOKEN}`,
      'Content-Type':  'application/json',
    },
    body: JSON.stringify({ orderId }),
  });

  const data = await res.json();
  if (!res.ok) {
    throw new Error(`Wolt dispatch failed (${res.status}): ${data.message || data.error}`);
  }
  return data.order; // { deliveryId, tracking, venue, etas, cost, ... }
}

// Usage:
const order = await dispatchWoltDelivery(17875);
console.log('Track this delivery:', order.tracking.url);
console.log('ETA range:', order.etas.dropoffMin, '–', order.etas.dropoffMax);
```

### PHP (Guzzle)

```php
use GuzzleHttp\Client;

$client = new Client(['base_uri' => 'https://delinka.deliz.co.il']);

$response = $client->post('/wp-json/ocws-wolt/v1/dispatch', [
    'headers' => [
        'Authorization' => 'Bearer ' . getenv('OCWS_WOLT_TOKEN'),
    ],
    'json' => [
        'orderId' => 17875,
    ],
]);

$data  = json_decode((string) $response->getBody(), true);
$order = $data['order'];
echo "Tracking: {$order['tracking']['url']}\n";
```

### Python (requests)

```python
import os, requests

def dispatch_wolt(orderId: int) -> dict:
    r = requests.post(
        "https://delinka.deliz.co.il/wp-json/ocws-wolt/v1/dispatch",
        headers={"Authorization": f"Bearer {os.environ['OCWS_WOLT_TOKEN']}"},
        json={"orderId": orderId},
        timeout=30,
    )
    r.raise_for_status()
    return r.json()["order"]

order = dispatch_wolt(17875)
print("Tracking:", order["tracking"]["url"])
```

---

## Behaviour notes

- **Synchronous.** The HTTP response only comes back after Wolt's
  `create-delivery` call returns (typically 1–2 seconds). No polling
  needed.
- **Idempotent.** Calling the endpoint repeatedly for the same order is
  safe. After the first call creates the delivery, subsequent calls
  return the latest persisted state without re-creating.
- **No auto-cancel.** Cancelling a delivery requires the separate Cancel
  flow (admin UI → Deliveries tab → Cancel button → reason). There is no
  `DELETE` on this endpoint today.
- **Wolt status evolves over time.** The first response after creation
  will be `woltStatus: "INFO_RECEIVED"`. Wolt's webhooks then update
  `woltStatus`, ETAs and `deliveredAt` as the courier progresses.
  Re-calling the endpoint reads the freshest values.
- **Sandbox vs production.** The endpoint shape is identical. The plugin
  decides which Wolt environment to talk to based on the API URL configured
  in WP admin. Use sandbox tokens in dev, production tokens in production.

---

## Rate-limiting

There is no rate limit on the WordPress side. Wolt's own API has rate
limits — if you hammer this endpoint hundreds of times per second you'll
eventually trip those and start seeing `502 wolt_create_failed` with a
`429` message inside. In practice you call this once per order, so it's
not a concern.

---

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| `401` on every call | Token wasn't sent, or it was rotated in the admin since you grabbed it. |
| `502` with "Requires post_code OR (street AND city)" | The order's shipping address doesn't have a street + city the plugin can resolve. Check that the OC checkout fields (`_shipping_street`, `_shipping_city_name`) are populated on the order. |
| `502` with "Wolt API URL or Venue ID not configured" | The plugin isn't fully configured yet — open WP admin → Wolt Drive → Settings. |
| `woltStatus` stays `INFO_RECEIVED` for a long time | Expected in sandbox — no real courier is dispatched. In production this transitions automatically as the courier progresses. |
| `tracking.url` is empty in the response | Wolt didn't return a tracking object. Open WP debug.log and look for `[OC Wolt CD] response` for the raw Wolt reply. |

---

## Contact

For changes to the API contract (extra fields, GET endpoint, batch
dispatch, etc.) → contact the WordPress plugin maintainer.
