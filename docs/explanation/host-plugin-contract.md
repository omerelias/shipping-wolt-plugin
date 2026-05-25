# The host plugin contract

This is an **explanation** doc — it discusses *why* the Wolt plugin is
a side plugin instead of a complete shipping replacement, and how it
talks to the OC Advanced Shipping host plugin without depending on its
classes.

If you're looking for the operational details, see
[Architecture](../reference/architecture.md) and
[Settings reference](../reference/settings.md).

---

## The fork problem

Original Concepts ship a WooCommerce shipping plugin called
**"Original Concepts WooCommerce Advanced Shipping"** (internal text
domain `ocws`, also called the "OC plugin" in our code). It handles
the heavy logistics: shipping methods, delivery zones, polygons, time
slots, the address autocomplete, the popup at checkout, local pickup.

In practice there are **multiple slightly-different forks** of that
plugin floating around different sites (it pre-dates the Wolt
integration). Their internals diverge — class names rename, hooks
move, table structure varies between versions. Hard-coupling to the
host plugin's classes would mean breaking on every fork.

So instead the Wolt plugin couples to a **stable string contract**
that all forks happen to share, never to PHP classes.

---

## The contract

Three things, all strings or simple meta keys:

### 1. Shipping method ID prefix

The host plugin registers WC shipping methods whose rate IDs start
with `oc_woo_advanced_shipping_method` (e.g.
`oc_woo_advanced_shipping_method5`).

Our price-override matches with:

```php
strpos( $rate_id, $prefix ) === 0
```

The prefix is **configurable** in our Advanced settings, defaulting to
`oc_woo_advanced_shipping_method`. So if you use a hard fork that
renamed its method, you just change one string and we follow.

### 2. Order meta keys

The host plugin's Google-autocomplete checkout flow populates these
meta keys on every order. We *read* them but never write them:

| Meta key | Source | What we read it for |
|---|---|---|
| `_shipping_street` / `_billing_street` | Google autocomplete | `dropoff.location.address.street` |
| `_shipping_house_num` / `_billing_house_num` | same | concatenated into street |
| `_shipping_city_name` / `_billing_city_name` | same | `dropoff.location.address.city` |
| `_shipping_floor` / `_billing_floor` | OC checkout form | `dropoff.comment` |
| `_shipping_apartment` / `_billing_apartment` | same | same |
| `_shipping_enter_code` / `_billing_enter_code` | same | same |
| `_shipping_phone` | OC explicit set on order | `recipient.phone_number` (fallback: WC billing_phone) |
| `_shipping_address_coords` | Google autocomplete (lat/lng JSON, sometimes string-encoded) | `dropoff.location.lat/lng` |
| `ocws_shipping_info_date` | OC slot picker (`d/m/Y` string) | `dropoff.options.scheduled_time` (date part) |
| `ocws_shipping_info_slot_start` | OC slot picker (`HH:MM` string) | `dropoff.options.scheduled_time` (time part) |
| `ocws_leave_at_the_door` | OC "leave at the door" checkbox (`'1'`/`''`) | `dropoff.options.is_no_contact` + the comment |

If a site uses a fork that doesn't populate these — say, vanilla WC
shipping with no OC plugin — the Wolt plugin still loads, but with
limited information. The address falls back to WC's standard
`shipping_address_1` / `shipping_city` / `shipping_postcode`. Slots
just don't exist (delivery dispatches as ASAP). The "leave at the
door" boolean defaults to `false`.

### 3. Group resolution helpers (optional)

When the host plugin uses shipping *groups* (zones), we look the group
up via:

```php
ocws_get_group_id_by_city( $city_code_or_hash );
OC_Woo_Shipping_Polygon::find_matching_polygon( $coords );
OC_Woo_Shipping_Polygon::find_matching_gm_city( $code );
```

Plus the `oc_woo_shipping_locations` table.

**These are optional**. The Wolt plugin tolerates all three being
missing — `OCWS_Wolt_Settings::is_oc_shipping_locations_table_present()`
gates the queries. When the host plugin isn't present or its tables
weren't installed, group resolution simply returns 0 and we use the
global venue / pickup settings.

---

## What the contract is NOT

- **Not class names.** We never `new OC_Woo_Shipping`. We never
  `class_exists` to *require* it — the soft check in
  `ocws_wolt_host_shipping_active()` is purely for a warning notice.
- **Not actions/filters defined by the host.** Beyond standard WC
  hooks (which the host also uses, but we don't piggyback through it).
- **Not shared database tables we write to.** The OC plugin owns its
  tables; we own our `wp_options` rows and our `_ocws_wolt_*` order
  meta. Two clean halves.
- **Not config inheritance.** Our settings UI is standalone — the
  Wolt admin page is a top-level menu item, not a submenu of the OC
  admin.

This separation is what lets us:

- Ship the Wolt plugin in its own repo / its own update cycle.
- Activate or deactivate it independently of the host.
- Run it next to *any* OC fork without per-fork code paths.
- Replace the host plugin in a future migration (e.g. to WC Blocks)
  without rewriting the Wolt integration — we only need the new host
  to populate the same meta keys.

---

## Why not just merge into the OC plugin?

History: this plugin used to live inside the OC plugin's folder, under
`includes/wolt/`. In May 2026 it was **extracted** into a standalone
repository because:

1. Every OC fork needed manual back-porting of the Wolt code.
2. Updates to Wolt's API needed a Wolt-only release; piggybacking on
   the OC plugin's release cycle was slow.
3. The OC admin UI is dense — the Wolt screens are easier to find
   when they have their own top-level menu.
4. WP best practice: one plugin, one concern. The host plugin handles
   shipping logistics; the Wolt plugin handles one specific courier
   integration.

The extraction kept all option keys (`ocws_wolt_*`) and order-meta
keys (`_ocws_wolt_*`) **identical**, so legacy installs that already
had Wolt configured inside the host plugin migrate without losing
state.

---

## When the contract might break

Practical risks for the future:

| Risk | What would change | What we'd do |
|---|---|---|
| The OC plugin renames the meta key `_shipping_street` | Our address resolver returns empty street → Wolt rejects with "post_code OR (street AND city)" | Add a new fallback key name to `OCWS_Wolt_Api::resolve_street`. Done in 30 seconds. |
| The OC plugin's shipping method ID changes | Our price-override no longer matches any rate | The user changes the Advanced setting. Done in 30 seconds. |
| A non-OC plugin is used for shipping | Same as above | Same — change the prefix. (Or open an issue if a richer mapping is needed.) |
| WC removes a hook we rely on (e.g. `woocommerce_package_rates`) | Quote stops firing | Move to whatever WC introduced as replacement. We'd see this in CI before customers do. |

Resilience is built in: every contract point has a default and a
fallback. A new host plugin doesn't need to expose *anything special*
for the Wolt plugin to function — at minimum it just needs to register
WC shipping methods with a recognisable prefix and populate WC's
standard order shipping fields.

---

## See also

- [Architecture](../reference/architecture.md) — the file structure
  the contract sits on top of.
- [Design decisions](design-decisions.md) — other non-obvious choices
  in the plugin.
- [Settings reference](../reference/settings.md#per-oc-group-overrides)
  — the per-group setting layer that uses the host plugin's group IDs.
