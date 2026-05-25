# Quick start

Goal: end-to-end, see the plugin **quote a Wolt price at checkout** and
**create a real Wolt delivery** (in sandbox) within 10 minutes.

This is a **tutorial** — follow the steps in order. By the end you'll
have a working integration on one site, and you'll understand the
moving parts well enough to move on to the [How-to guides](../README.md).

---

## Prerequisites

You'll need all six before you start:

| Item | Why | Notes |
|---|---|---|
| WordPress 5.8+ site | Host | HPOS supported but not required. |
| WooCommerce 6.0+ active | Cart / orders | The plugin overrides a WC shipping rate. |
| The OC Advanced Shipping plugin active | Provides the shipping method to override | Any fork that registers `oc_woo_advanced_shipping_method*` rate IDs works. |
| `WP_DEBUG_LOG` on | So you can watch what's being sent | In `wp-config.php`: `define('WP_DEBUG_LOG', true);`. |
| Wolt sandbox credentials | Sandbox API key, venue ID, merchant ID | Ask your Wolt contact. |
| A test WC order | We'll trigger Wolt dispatch on it | Any order with a complete shipping address in Israel. |

Don't have Wolt sandbox credentials yet? See
[Install & configure → Step 1](../how-to/install-and-configure.md#step-1--get-wolt-sandbox-credentials).

---

## Step 1 — Install the plugin

1. Upload the `shipping-wolt-plugin` folder to `wp-content/plugins/`.
2. Go to **Plugins** in WordPress admin.
3. Activate "**OC Wolt Drive**".

You should now see a new top-level menu item **"Wolt Drive"** in the
admin sidebar (with a car icon). If you don't, double-check that
WooCommerce is active and that the upload was complete.

> Already see a warning about "OC Advanced Shipping not detected"?
> Activate that plugin first — the Wolt plugin won't price-override
> anything without it. The warning is non-fatal, you can keep going.

---

## Step 2 — Enter your sandbox credentials

Go to **Wolt Drive → Settings**.

Fill in these fields (everything else can stay default):

| Field | Value (sandbox) |
|---|---|
| Enable Wolt Drive | ☑ on |
| Wolt API URL | `https://daas-public-api.development.dev.woltapi.com` *(default)* |
| API key (Merchant Key) | (paste your sandbox API key) |
| Venue ID | (paste your sandbox venue ID) |
| Merchant ID | (paste your sandbox merchant ID) |
| Pickup address (venue) | (your store's physical address in plain text) |
| Auto-dispatch on status | `Processing` for now |
| Currency | `ILS` *(default)* |

Click **Save Changes**.

Verify the credentials are good by clicking **Run /delivery-areas call**
on the same page — within a couple of seconds you should see a green
"Connected. Wolt returned N delivery area(s)." If you get red, the
credentials are wrong or the API URL is unreachable.

---

## Step 3 — See a live quote at checkout

This step doesn't even need a webhook — the price-override is purely
local.

1. Open the site in a private window (so the cart is empty).
2. Add any product to the cart.
3. Go to checkout.
4. Enter a real shipping address inside Wolt's delivery area
   (Tel Aviv works for sandbox). Use Google autocomplete in the
   address field if your theme provides it — that fills the OC plugin's
   custom address fields the Wolt plugin reads.
5. Watch the shipping line update.

Expected: the shipping cost is no longer the OC plugin's static price
— it's whatever Wolt returned (typically ₪25-₪50 in sandbox).

While you do this, watch the log in another terminal:

```bash
tail -f wp-content/debug.log | grep "OC Wolt"
```

You should see lines like:

```
[OC Wolt SP] destination: {"country":"IL","street":"...", ...}
[OC Wolt SP] body: {"street":"...","city":"...","lat":...,"lon":...,"language":"he"}
[OC Wolt SP] response (HTTP 200): {"price":{"amount":4200,"currency":"ILS"}}
```

`SP` = "shipment-promises", the live quote call. If you see an empty
destination or a non-200 response, jump to
[Troubleshoot](../how-to/troubleshoot.md).

---

## Step 4 — Generate a webhook secret and register

Wolt sends webhooks (events about courier progress) signed with a
shared secret you generate.

1. Stay on **Wolt Drive**, switch to the **Webhook** tab.
2. Under "Shared secret (HS256)", click **Generate**. A 48-char string
   appears. Click **Save secret**.
3. Verify the URL listed at the top: it'll be something like
   `https://YOUR-SITE/wp-json/ocws-wolt/v1/webhook`.
4. Under "Registration with Wolt", click **Register webhook with
   Wolt**. The status pill should flip from "Not registered" to
   "Registered" with a webhook ID displayed.

That's all the webhook plumbing — Wolt now know where to send events
and how to sign them.

---

## Step 5 — Place an order and dispatch it

1. In the private window, complete the test order.
2. Switch back to the admin. Go to **WooCommerce → Orders**, open
   the new one.
3. The order's status is "Pending payment" or "Processing" (depending
   on the gateway). If it's not "Processing" yet, transition it
   manually — the plugin's auto-dispatch fires on the configured
   status.
4. After a couple of seconds, refresh the order page.

In the right sidebar you should now see a **Wolt Drive** meta box:

```
Internal status:    created
Wolt status:        INFO_RECEIVED
Courier ETA at venue: 17:00
Delivery ETA:       17:14 – 17:35
Wolt cost:          ₪42.00
Delivery ID:        6a044234f9e37ebe168f8448
Tracking:           View tracking
```

The **View tracking** link opens Wolt's live tracking page. In sandbox
you'll see "Order received" — no real courier is dispatched, but every
field is populated as if it were.

> If the meta box says "No Wolt shipment for this order yet", click
> **Create Wolt delivery now** in the same box. The auto-dispatch
> probably didn't fire (most likely cause: the status didn't transition
> to the value you configured).

---

## Step 6 — See the deliveries dashboard

Go to **Wolt Drive → Deliveries**.

You'll see a paginated table of every order with a Wolt delivery
attached, the latest at the top:

```
ORDER   CUSTOMER          DROPOFF ADDRESS      STATUS         ETA      COST    ACTIONS
#17873  ג'ורג' שופאני     מבצע קדש 67, ...     ●INFO_RECEIVED 17:14    ₪42.00  [Track] [Order]
```

Click **Track** to open the tracking page. Click **Order** to jump
to the WC order edit screen. Once the delivery progresses, the
**Cancel** button and the **Location** button (map of the courier)
appear.

---

## You're done

A WordPress site now:

- Quotes Wolt's price at checkout (`/shipment-promises`)
- Resolves the right Wolt venue per delivery (`/available-venues`)
- Dispatches a delivery on order paid (`/deliveries`)
- Receives status updates via a JWT-signed webhook
- Shows the customer a Wolt-branded tracking card on the thank-you page
- Lets you cancel from the admin

That's the full happy path. From here:

- Want to drive dispatch from outside WP? → [Dispatch API guide](../how-to/dispatch-api.md)
- Going live? Get production credentials from Wolt → [Install & configure](../how-to/install-and-configure.md#step-10--moving-to-production)
- Want to understand what each meta key means? → [Order meta reference](../reference/order-meta.md)
- Something didn't work? → [Troubleshoot](../how-to/troubleshoot.md)
