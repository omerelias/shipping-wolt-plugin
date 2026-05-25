# Design decisions

Non-obvious choices in the codebase that look weird at first glance,
with the reasoning behind each. Read this when you're about to "fix"
something and want to confirm it actually is broken.

For *what* the code does see [Architecture](../reference/architecture.md);
this file is the *why*.

---

## Why two WP settings groups

`OCWS_Wolt_Settings::register_options` splits options across **two**
groups (`ocws_wolt_settings` for the main tab, `ocws_wolt_webhook_settings`
for the webhook secret).

Reason: WP's `options.php` POST handler walks **every option in the
submitted group** and writes `null` for any not in `$_POST`. The
Webhook tab's form contains only the secret field; if both forms
posted to the same group, clicking "Save secret" would nuke every API
key, venue ID, and other setting absent from that form.

Lesson: never trust WP Settings API forms to leave options they don't
own alone. Group by which fields each form actually owns.

Two more options (`ocws_wolt_webhook_id`, `ocws_wolt_dispatch_api_key`)
are intentionally in **no** group — they're written only via AJAX
handlers, so being in a group would let either form clobber them.

---

## Why two order-lifecycle hooks instead of one

`OCWS_Wolt_Delivery_Trigger::init` subscribes to **both**
`woocommerce_order_status_changed` and `woocommerce_new_order`. Looks
redundant; isn't.

- `woocommerce_order_status_{slug}` (the obvious choice) fires only on
  a status **transition**. An order created already in the trigger
  status (typical with pay-on-delivery / COD that lands directly at
  `pending`) doesn't transition — the hook never fires.
- `woocommerce_order_status_changed` catches every transition,
  including ones from `""` / `"auto-draft"` to a real status.
- `woocommerce_new_order` is the only hook guaranteed to fire for every
  freshly created order regardless of its initial status.

Listening to both gives "any order that ENDS UP in the trigger status"
coverage. `create_for_order()` is idempotent (it short-circuits when
`META_DELIVERY_ID` is already set), so the double subscription is
safe even when both hooks fire for the same order.

---

## Why shipment-promises has a flat body but create-delivery is nested

Wolt's API is inconsistent on this. Empirically:

- `POST /v1/venues/{venue_id}/shipment-promises` requires `street`,
  `city`, `post_code`, `lat`, `lon` **at the root** of the body. Their
  validator rejects nested address objects with "Input should be a
  valid dictionary".
- `POST /v1/venues/{venue_id}/deliveries` requires
  `dropoff.location.address` to be a **structured object** containing
  `street`, `city`, `post_code`, `country`. Sending a string instead
  triggers the same "should be a valid dictionary" error.

Their own docs were misleading about field names (claimed
`street_address`/`postal_code`; the live API wants `street`/`post_code`).
We learned the right shape from error messages and confirmed with
Wolt support. The naming inconsistencies are now hard-coded in the
relevant builders with comments explaining each.

While we're on coords:

- shipment-promises uses **`lon`** at root
- create-delivery uses **`lng`** inside `dropoff.location`
- webhook events on `order.location_updated` use **`lon`** under
  `details.courier_location`

The normaliser handles both naming variants. Don't try to "fix" any of
these — they reflect Wolt's actual contract.

---

## Why amounts come back in minor units but parcel prices go out in major units

Wolt's response price (`price.amount` in delivery responses and webhook
events) is in **minor currency units** — agorot, cents, etc. The
plugin divides by 100 when reading.

But for **parcels[]** sent in create-delivery, Wolt accept the price
in **major units** (e.g. `12.5` ILS, not `1250`). Sandbox returns 201
for major-unit parcel prices and the parcels show correctly in their
admin. This contradicts the read direction.

Decision: send major units (matches what the merchant sees) and
convert on read. If a future API change rejects major units for
parcels, multiply by 100 in `build_parcels()` — small fix.

This is logged because every payment/shipping API has subtle
unit-handling rules and getting them wrong is the most common
integration bug.

---

## Why we auto-resolve the venue at dispatch time but not at quote time

`/available-venues` is the right way to pick a venue: Wolt knows their
own availability, traffic, and ranking. But it's a network round-trip,
and `shipment-promises` fires on **every keystroke** at checkout.

Calling available-venues at quote time would mean:
- 2 calls per keystroke (available-venues + shipment-promises)
- Worse latency on every typed character
- No real benefit — the price doesn't differ meaningfully between
  candidate venues for quote purposes

So:
- **Quote time** uses the configured venue ID (per-group → global
  fallback). One call per address.
- **Dispatch time** runs available-venues once to pick the venue, then
  POSTs create-delivery to that venue. Two calls per order.

If available-venues fails, we silently fall back to the configured
venue — orders are never blocked because of a network hiccup against
this endpoint.

---

## Why `order_number` is the last 3 digits of the WC order ID

Wolt's rep asked specifically for 3-digit numbers — their courier app
displays this as the user-facing identifier. WC order IDs are
typically 4-6 digits; the rep didn't want them visible to couriers
who'd see them as "magic numbers".

`short_order_number()` takes `substr($id, -3)` and left-pads with
zeros so order #7 becomes `"007"` and order #17867 becomes `"867"`.

The full WC order ID still ships as `merchant_order_reference_id` for
exact deduplication on Wolt's side.

---

## Why we keep `_ocws_wolt_*` order meta on uninstall

Conventional advice is "uninstall should remove all your data".
We deliberately don't.

Reasoning:

1. Historical orders carry a Wolt delivery_id, tracking URL, ETAs,
   etc. — useful audit data even after the plugin is gone.
2. Re-installing later (or installing a successor) lets the new code
   read the legacy meta and restore state.
3. WC's standard `_shipping_*` meta isn't touched on uninstall either
   — we follow the same precedent.

What IS removed: `ocws_wolt_*` rows in `wp_options` (configuration,
not history).

---

## Why every class is wrapped in `if ( ! class_exists ) : … endif;`

Defensive coexistence with legacy copies. Some sites still have an old
`shipping-wolt-plugin/` folder somewhere or an `oc-woo-shipping/`
plugin with an `includes/wolt/` subdirectory that declares the same
classes. Without guards, the second `class OCWS_Wolt` fatal-errors
during a request.

With guards, the second copy is silently skipped. We log a line:

```
[OC Wolt Drive] Duplicate class OCWS_Wolt — first declared in …
```

so operators can find and remove the stale copy on their schedule.
Better to be functional + warn than to white-screen.

---

## Why the bearer token for the dispatch API is mandatory by default

`POST /wp-json/ocws-wolt/v1/dispatch` requires a configured bearer
token; without one it returns `503 ocws_wolt_dispatch_disabled`.

The temptation is "make it open by default so it Just Works".
Rejected because:

- Every WP REST endpoint is discoverable via `wp-json` introspection.
- Open-by-default + production credentials = anyone who guesses an
  order id can trigger real Wolt deliveries (real money).
- One click to Generate in the admin is essentially zero friction
  compared with the cost of leaving it open.

If a future use case needs key-less access from inside the same site
(e.g. PHP-to-PHP), the right fix is a separate filter that checks
`is_admin()` / `current_user_can` — not removing the bearer entirely.

---

## Why we use native `DateTime` instead of Carbon

The legacy code (inside the OC plugin) used `nesbot/carbon` via
composer. The standalone plugin avoids vendor/ entirely:

- Carbon adds ~3 MB to the install.
- Everything we need is in stock PHP `DateTime` / `DateTimeZone` /
  `DateInterval`.
- No composer means no build step on the server — `git pull` is the
  whole deploy.

`format_local_time()` and `get_scheduled_dropoff_time_iso8601()` are
the only date-heavy spots; both are 5-line `DateTime` usages.

---

## Why we hand-roll HS256 verification instead of using `firebase/php-jwt`

For the same reason — no composer:

- The JWT verifier is ~40 lines of native PHP (`hash_hmac` + base64url
  + `hash_equals`).
- We don't need claim libraries, algorithm switching, key rotation
  machinery — Wolt always sign HS256 with the secret we provided at
  registration time.

`OCWS_Wolt_Webhook::verify_jwt_hs256()` rejects anything that isn't
strictly `alg: HS256`, validates `exp` / `nbf` when present, and uses
`hash_equals` for timing-attack resistance.

If we ever need RS256, that's the point we'd add the library.

---

## Why the customer tracking card lives in its own class

`OCWS_Wolt_Frontend` is the only class that touches the storefront
side. Everything else is admin or background. Keeping the split
explicit means:

- Bug in a customer-facing string can't accidentally affect admin
  flows (and vice versa).
- The class is the only place that ever calls `wp_enqueue_style`
  unconditionally for non-admin pages — easy to audit.
- Future "admin-only mode" (disable customer card entirely) is a
  one-line config rather than a tangle.

The class also wires up the WC email row in the same place — both
surfaces are "things the customer sees about their Wolt delivery", so
they belong together.

---

## See also

- [Architecture](../reference/architecture.md) — the *what*, complementary
  to the *why* here.
- [Host plugin contract](host-plugin-contract.md) — why the plugin is
  a side plugin and not a replacement.
