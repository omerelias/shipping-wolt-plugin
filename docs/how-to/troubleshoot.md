# Troubleshoot

A symptom → diagnosis → fix index. Start by enabling
`WP_DEBUG_LOG` and tailing the log; almost every problem is one grep
away from being understood.

```bash
tail -f wp-content/debug.log | grep "OC Wolt"
```

The plugin tags every Wolt-related log line:

| Tag | What |
|---|---|
| `[OC Wolt SP]` | shipment-promises (quote at checkout) |
| `[OC Wolt AV]` | available-venues (pick the venue at dispatch time) |
| `[OC Wolt CD]` | create-delivery (the actual dispatch) |
| `[OC Wolt Cancel]` | cancel-delivery |
| `[OC Wolt Drive]` | bootstrap / loader-level messages |

---

## At checkout, no price comes from Wolt

The shipping line still shows the OC plugin's default price even though
the plugin is enabled.

### Diagnose

```bash
grep "OC Wolt SP" wp-content/debug.log | tail -5
```

| What the log shows | Meaning | Fix |
|---|---|---|
| No `OC Wolt SP` lines at all | The price-override filter isn't running | Check WolDrive → Settings → Enable Wolt Drive is ☑ on; check shipping method ID prefix matches (Advanced → Host shipping method ID prefix) |
| `enabled=NO` | Plugin is disabled in settings | Toggle "Enable Wolt Drive" on, Save |
| `configured=NO` | API URL, key or Venue ID missing | Fill them under Settings → API connection |
| `destination: {...empty...}` | Customer hasn't typed an address yet | Expected on the initial page render; will fire again once they fill the form |
| `shipment-promises failed: ... Requires post_code OR (street AND city)` | OC plugin's checkout didn't populate the address custom fields | Make sure customer used the Google autocomplete + that the OC plugin is active |
| `insufficient_address` (silent) | We short-circuited before calling Wolt because we lack street+city | Wait for the customer to finish entering the address |
| HTTP 401 / 403 in response | API key wrong | Re-paste the sandbox key |
| HTTP 5xx | Wolt outage | Wait and retry |

If `OC Wolt SP body` shows the address but Wolt return an error,
inspect the `response` line for Wolt's exact message.

---

## "Save Secret" wiped my other settings

This was a real v1.0–1.2 bug; **fixed in v1.3.0**.

If you're on v1.3.0+ and see this happen, the two Settings API groups
(`ocws_wolt_settings` for the main tab, `ocws_wolt_webhook_settings`
for the secret form) somehow got merged again. Open
`includes/class-ocws-wolt-settings.php` and check the `register_options()`
method.

For older versions: upgrade to 1.3.0+ and re-enter the cleared
settings.

---

## Order is paid but no Wolt delivery was created

The auto-dispatch hook didn't fire.

### Diagnose

```bash
grep "OC Wolt CD" wp-content/debug.log | tail -5
```

If nothing, the trigger never ran. If there's a `[OC Wolt CD] response`
with a non-201 status, the trigger ran but Wolt rejected.

### Possible causes

| Cause | Check | Fix |
|---|---|---|
| Auto-dispatch is set to "— Disabled —" | Wolt Drive → Settings → Auto-dispatch on status | Pick a real status |
| Trigger status doesn't match the order's actual status | Compare order status to settings | Either change the setting or move the order to that status |
| Order didn't go through the OC shipping method | Order edit → Shipping section | If shipping via another method, manual dispatch is the only path. Or change the method-ID prefix in Advanced. |
| Already created once (idempotent) | Order edit → meta box shows `Status: created` + a delivery ID | Working as intended; we don't create twice |
| `ocws_wolt_enabled` is off | Settings → Enable Wolt Drive | Turn on, save |
| Wolt rejected | `[OC Wolt CD] response (HTTP 4xx)` line | Read Wolt's `detail` array; fix the offending field |

If you need to dispatch a delivery that should have auto-fired, click
**Create Wolt delivery now** in the order's Wolt Drive meta box.

---

## Wolt rejected the create-delivery with a structured error

Most common culprits:

| `loc` path in Wolt's error | Meaning | Fix |
|---|---|---|
| `["body","dropoff","location","address"]` | Address isn't a structured object Wolt expects | Bug in our plugin — file an issue. We send `{street, city, country}`. |
| `["body","recipient","phone_number"]` | Recipient phone empty or in a format Wolt rejects | Check `_shipping_phone` and `billing_phone` on the order |
| `["body","parcels"]` | No parcels were sent | Order has no products (refund-only? gift card?) — manual case |
| "Requires post_code OR (street AND city)" | Address resolution returned nothing | OC plugin custom address fields are empty on the order |
| `"order_number must be ..."` | Wolt-side tightened a constraint we don't yet match | File an issue with the full error JSON |

The full Wolt error is logged behind `WP_DEBUG_LOG` as
`[OC Wolt CD] response (HTTP 4xx): ...`.

---

## Webhook events from Wolt don't update the order

Order is dispatched, courier is moving, but the meta box still shows
`INFO_RECEIVED` and no order notes are being added.

### Diagnose

1. Is the webhook registered at Wolt? **Wolt Drive → Webhook** →
   should show "Registered" with a webhook ID.
2. Is the secret on our side the same one we gave Wolt? You can't see
   the secret on the Wolt side; if in doubt, click **Generate**
   (rotates), **Save secret**, then **Re-register**.
3. Hit the webhook URL manually and look at the response:
   ```bash
   curl -X POST "https://YOUR-SITE/wp-json/ocws-wolt/v1/webhook" \
     -H "Content-Type: application/json" \
     -d '{"type":"order.received","details":{"id":"x","wolt_order_reference_id":"y"}}'
   ```
   Expected: `{"ok":false,"error":"Invalid JWT signature"}` — that
   means the route is alive and signature check is on. Now you know
   incoming Wolt events would be received.
4. Look at the server's access log for `POST /wp-json/ocws-wolt/v1/webhook`
   — if Wolt aren't even hitting it, the registration didn't take.

### Common causes

| Cause | Fix |
|---|---|
| Webhook not registered | Wolt Drive → Webhook → Register webhook with Wolt |
| Site has Cloudflare or similar blocking POST to `/wp-json/` | Whitelist Wolt's IPs at the WAF / disable bot protection for that endpoint |
| Secret mismatch (rotated locally, never re-registered) | Re-register from the Webhook tab |
| Site recently moved (different domain) | Re-register with the new URL |

---

## Tracking URL is empty in the meta box

Wolt returned 201, the delivery_id is there, but `Tracking` is blank.

This was a v1.0 parsing bug — we used to read `tracking_url` (flat) but
Wolt return `tracking.url` (nested). **Fixed in v1.0.0+** by the
response parser.

If you're seeing this on a fresh install: dump the raw response from
the log:

```bash
grep "OC Wolt CD] response" wp-content/debug.log | tail -1
```

The response should contain `"tracking":{"url":"..."}`. If it doesn't,
Wolt themselves didn't send a tracking URL — sandbox sometimes omits
this.

---

## Customer doesn't see the tracking card on thank-you page

The card is rendered by `OCWS_Wolt_Frontend` only on
`is_wc_endpoint_url('order-received')` and
`is_wc_endpoint_url('view-order')`.

| Symptom | Fix |
|---|---|
| Card is missing on thank-you | The order has no Wolt delivery yet. Check the meta box. |
| Card is missing on a custom thank-you page | Your theme overrides `woocommerce/checkout/thankyou.php` and dropped the action hook. Add `do_action('woocommerce_order_details_after_order_table', $order)` somewhere visible. |
| Card is unstyled / breaks the layout | `assets/css/frontend.css` not loading. Check the deploy got it. View the page source — look for `<link rel='stylesheet' ... oc-wolt-drive-frontend>`. |

---

## "Cannot declare class OCWS_Wolt" fatal

A legacy stale copy of the plugin is loading first. The new code has
defensive `class_exists` guards (v1.3.0+), so this shouldn't fatal —
but it will silently make the second copy a no-op.

```bash
grep "Duplicate class OCWS_Wolt" wp-content/debug.log
```

The log line tells you the file path of the first copy. Usually:

- Old `shipping-wolt-plugin/` folder on the server alongside the new
  one
- Legacy `oc-woo-shipping/` with an `includes/wolt/` subdirectory
- An old `oc-woo-shipping.php` file still sitting in our plugin folder
  alongside `oc-wolt-drive.php` (both have plugin headers; both get
  activated)

Fix: delete the stale source.

---

## Cron-related issues

The plugin doesn't use `wp-cron` directly. Everything is driven by
either:

- An admin click (manual dispatch / cancel / etc.)
- A WC order status transition (auto dispatch)
- An incoming Wolt webhook

So if "scheduled dispatch isn't firing", the problem is upstream — the
order status isn't transitioning, or the trigger status doesn't match.

---

## Couldn't find your symptom?

1. Search the log: `grep -i "OC Wolt" wp-content/debug.log | tail -50`
2. Look at the most recent `OC Wolt SP/CD/AV` response — it almost
   always says what went wrong in `detail` or `message`.
3. Compare against the [Wolt API mapping](../reference/wolt-api-mapping.md)
   reference to see whether the call is supposed to fire when you
   think it should.
4. Open an issue at https://github.com/omerelias/shipping-wolt-plugin/issues
   with: the symptom, the relevant log lines, and your plugin version
   (Plugins screen → "OC Wolt Drive" → version).
