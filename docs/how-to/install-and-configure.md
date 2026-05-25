# Install & configure

A complete, production-grade setup walkthrough. Each step is independent
— skip ones that already apply, come back to ones you skip.

> If you've never used the plugin before, the [Quick start](../tutorials/quick-start.md)
> walks you through a shorter sandbox-only happy path first.

---

## Step 0 — Prerequisites

Hard requirements:

| Requirement | Why | How to verify |
|---|---|---|
| **WordPress 5.8+** | Plugin header + REST API features | WP admin → Dashboard → "About WordPress" |
| **PHP 7.4+** | Type hints, native `DateTime` | WC → Status → System info |
| **WooCommerce 6.0+ active** | Cart, orders, shipping rates | Plugins → must show "Active" |
| **OC Advanced Shipping plugin active** | Provides the shipping method we override | Any fork that registers `oc_woo_advanced_shipping_method*` rate IDs |
| **HTTPS on the site** | Wolt only sends webhooks to https:// | Browser address bar shows the lock |
| **`WP_DEBUG_LOG` enabled** (recommended) | Lets you watch what's being sent and what comes back | `wp-config.php`: `define('WP_DEBUG_LOG', true);` |

Optional but strongly recommended:

| Item | What it gives you |
|---|---|
| Cron working (`wp-cron.php` reachable or system cron set up) | Reliable scheduled-delivery triggering |
| WooCommerce **High-Performance Order Storage** active | Faster order queries; the plugin declares compatibility |
| Hebrew or your locale set in WordPress (`he_IL`) | Plugin UI + customer-facing card render in Hebrew automatically |

---

## Step 1 — Get Wolt sandbox credentials

Email your Wolt contact and ask for:

1. **Sandbox base URL** — usually `https://daas-public-api.development.dev.woltapi.com`.
2. **Sandbox API key** ("Merchant Key") — long base64-ish string.
3. **Venue ID** — 24-char hex string (one per pickup branch).
4. **Merchant ID** — 24-char hex string (one per business).

Wolt typically issues sandbox creds within a day. Production credentials
come **only after** successful sandbox testing — don't expect both at
once.

---

## Step 2 — Deploy the plugin

You have three deploy options:

### Option A — Upload via the WP admin

1. Zip the plugin folder: `shipping-wolt-plugin.zip`.
2. WP admin → Plugins → Add New → Upload Plugin → Choose file → Install Now → Activate.

### Option B — Git checkout on the server

```bash
cd wp-content/plugins/
git clone https://github.com/omerelias/shipping-wolt-plugin.git
```

Future updates:

```bash
cd wp-content/plugins/shipping-wolt-plugin/
git pull
```

Then activate from the Plugins screen.

### Option C — PhpStorm / SFTP deploy

If you develop locally and sync via PhpStorm's Deployment tool: make
sure the deployment includes the languages/ directory (`.mo` files are
binary), and that "Delete remote files when local are deleted" is on so
removed legacy files don't linger on the server.

After any deploy, run a single hard-refresh of the WP admin to clear
PHP opcache.

---

## Step 3 — Verify the plugin loaded

WP admin → Plugins → check that "OC Wolt Drive" shows the expected
version. Then check `wp-content/debug.log` for any fatal:

```bash
tail -50 wp-content/debug.log
```

You want a clean log — no `Fatal error`, no `Class not found`. A line
like:

```
[OC Wolt Drive] Duplicate class OCWS_Wolt — first declared in …
```

is informational: a legacy copy of an old class is around. Find and
remove it (usually under another plugin's `includes/wolt/`).

---

## Step 4 — Configure API connection

WP admin → **Wolt Drive → Settings**.

Fill the **API connection** card:

| Field | Value | Notes |
|---|---|---|
| API base URL | `https://daas-public-api.development.dev.woltapi.com` | Default is sandbox. Switch to production URL only after Wolt grants prod creds. |
| API key (Merchant Key) | (from Step 1) | Stored in plain text in WP options — keep DB access locked. |
| Venue ID | (from Step 1) | Used in `/v1/venues/{venue_id}/*` paths for the quote. |
| Merchant ID | (from Step 1) | Used in `/merchants/{merchant_id}/*` paths (webhooks, delivery-areas, available-venues). |
| Currency | `ILS` | ISO 4217. Used for parcel prices in `create-delivery`. |

Click **Save Changes**.

Click **Run /delivery-areas call** — within a couple of seconds you
should see green "Connected. Wolt returned N delivery area(s)." A red
error means credentials are wrong, the URL is unreachable, or the
firewall is blocking outbound HTTPS.

---

## Step 5 — Configure general behaviour

Still in **Settings**, the **General** card:

| Field | Recommendation |
|---|---|
| Enable Wolt Drive | ☑ on |
| Pickup address (venue) | Your store's physical address in plain text. Leave blank to fall back to the WooCommerce store address. Per-delivery the venue address Wolt returns from `available-venues` may override this. |
| Auto-dispatch on status | `Processing` — fires once the gateway marks the order paid. Choose "**— Disabled (manual dispatch only) —**" if you want to dispatch only via the admin button or the dispatch API. |
| Dispatch offset (minutes) | `30` is sensible. If the customer picks a slot starting at 16:00, Wolt schedules delivery for 16:30. |
| Tracking page language | Leave empty (auto-detect from site locale). Override to `he` or `en` only if you need to force a specific language on the Wolt tracking page. |
| Require 18+ verification | Only check this if **every** product you sell is age-restricted (alcohol, tobacco). It applies `dropoff_restrictions: age_check_18` to all parcels. Legal compliance is the merchant's responsibility regardless. |

The **Pricing markup** card lets you add a fixed amount or a percentage
on top of Wolt's quote when displaying to the customer. Leave at 0 to
pass Wolt's price through unchanged.

---

## Step 6 — Webhook secret + registration

WP admin → **Wolt Drive → Webhook**.

1. Under "Shared secret (HS256)": click **Generate**.
   A 48-char string fills in. Click **Save secret**.
2. Under "Registration with Wolt": click **Register webhook with
   Wolt**. The pill switches to "Registered" with a Wolt-side webhook ID.

Behind the scenes this POSTs to
`/v1/merchants/{merchant_id}/webhooks` with our endpoint URL and the
secret. Wolt then signs every event JWT with that secret; the plugin
verifies the signature on each incoming event.

To rotate later: click Generate again → Save secret → click
**Re-register**. Old secret stops working the moment you Save.

---

## Step 7 — (Optional) Enable courier location updates

If you want a live map of the courier inside the admin:

1. WP admin → Wolt Drive → Settings → Advanced.
2. Check **"Subscribe to courier location updates"**.
3. Save.
4. Go to Webhook tab → click **Re-register** so Wolt know to include
   `order.location_updated` events.

> Heads up: `order.location_updated` fires every few seconds during a
> live delivery. The plugin de-noises it (no order notes for location
> events) but it does mean more requests hitting your server during
> transit. Skip if you don't actually need the live map.

---

## Step 8 — (Optional) Enable the dispatch API

If you want external systems to be able to trigger Wolt dispatch
programmatically:

1. WP admin → Wolt Drive → Webhook → scroll to **Dispatch API
   (internal)**.
2. Click **Generate** — a bearer token appears. Copy it into your
   external system's secrets store.
3. Hand the [Dispatch API guide](dispatch-api.md) to whoever's
   integrating.

Endpoint: `POST /wp-json/ocws-wolt/v1/dispatch`.

You can leave this disabled forever and use the plugin entirely
through the WP admin — generating the key is what activates the
endpoint.

---

## Step 9 — Smoke-test

Place a real order in a private window. Watch `debug.log` while you do:

```bash
tail -f wp-content/debug.log | grep "OC Wolt"
```

Expected log entries:

1. At checkout, every time the address changes:
   ```
   [OC Wolt SP] destination: {...}
   [OC Wolt SP] body: {...}
   [OC Wolt SP] response (HTTP 200): {"price":{"amount":4200,"currency":"ILS"}}
   ```
2. After paying / status moving to Processing:
   ```
   [OC Wolt AV] body: {...}
   [OC Wolt AV] response (HTTP 200): [{"pickup":{"venue_id":"..."}, ...}]
   [OC Wolt CD] body: {...}
   [OC Wolt CD] response (HTTP 201): {"id":"...","tracking":{"url":"..."}, ...}
   ```
3. If you have webhooks registered, more entries arrive over the
   following minutes as Wolt courier states change.

If any step doesn't fire or you see an HTTP error, jump to
[Troubleshoot](troubleshoot.md).

---

## Step 10 — Moving to production

Once Wolt grant production credentials:

1. Get a **separate** production API key + venue ID + merchant ID
   from Wolt.
2. WP admin → Settings → swap the API URL to
   `https://daas-public-api.wolt.com`.
3. Update API key, Venue ID, Merchant ID to the production values.
4. **Re-register the webhook** — Wolt's production webhook endpoint is
   separate from sandbox. Click Generate to rotate the secret, then
   click Register webhook with Wolt.
5. Place one **real** order with your own card to verify end-to-end.
6. Charge yourself a small amount via the markup, then refund — proves
   the cancel flow works too.

Keep `WP_DEBUG_LOG` on for the first week. Once stable, leave it on
behind `WP_DEBUG=false` so logs are written but not displayed.

---

## What to do next

- Hand the [Dispatch API guide](dispatch-api.md) to the team
  integrating from outside WordPress.
- If you want to localise UI strings beyond Hebrew, see
  [Translate the plugin](translate-the-plugin.md).
- For background on why the plugin makes the choices it makes
  (FLAT bodies, two settings groups, …), see
  [Design decisions](../explanation/design-decisions.md).
