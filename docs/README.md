# OC Wolt Drive — Documentation

A WordPress plugin that connects WooCommerce to **Wolt Drive**, Wolt's
on-demand courier API. It runs as a **side plugin** next to the OC
Advanced Shipping host plugin, replacing the shipping rate at checkout
with Wolt's live quote and dispatching couriers when orders are placed.

> **Latest version**: 1.5.0 · **PHP**: 7.4+ · **WordPress**: 5.8+ · **WooCommerce**: 6.0+
>
> Source: https://github.com/omerelias/shipping-wolt-plugin

---

## Where to start

Pick the entry point that matches what you're trying to do.

| You are… | Start here |
|---|---|
| Setting the plugin up for the first time | [Quick start](tutorials/quick-start.md) |
| Deploying to a real site / configuring credentials | [Install & configure](how-to/install-and-configure.md) |
| Hooking the plugin up from an external system | [Dispatch API guide](how-to/dispatch-api.md) |
| Adding Hebrew / translating to a new locale | [Translate the plugin](how-to/translate-the-plugin.md) |
| Something isn't working | [Troubleshooting](how-to/troubleshoot.md) |
| Understanding how the pieces fit | [Architecture](reference/architecture.md) |
| Looking up a specific option or meta key | [Settings reference](reference/settings.md) · [Order meta reference](reference/order-meta.md) |
| Hooking into the plugin from your own code | [Hooks & filters](reference/hooks-and-filters.md) |
| Wondering *why* something was designed this way | [Design decisions](explanation/design-decisions.md) |

---

## Documentation map (Diátaxis)

```
                            Practical                Theoretical
                       ──────────────────────  ──────────────────────

   Learning      │  Tutorials                  Explanation
   (study)       │  ┌─────────────────────┐    ┌────────────────────────────┐
                 │  │ Quick start          │   │ Architecture                │
                 │  │                      │   │ Host plugin contract        │
                 │  └─────────────────────┘    │ Design decisions            │
                 │                              └────────────────────────────┘
                 │
   Working       │  How-to guides              Reference
   (do a task)   │  ┌─────────────────────┐    ┌────────────────────────────┐
                 │  │ Install & configure  │   │ Settings (all 19 options)   │
                 │  │ Dispatch API guide   │   │ Order meta (all 21 keys)    │
                 │  │ Translate            │   │ Wolt API mapping            │
                 │  │ Troubleshoot         │   │ Webhook events              │
                 │  └─────────────────────┘    │ Hooks & filters             │
                                                └────────────────────────────┘
```

Each quadrant serves a different purpose; if you ever feel "I came here
to look up X and got a tutorial" or "I wanted to learn but this is just
a list", you're probably in the wrong quadrant — jump using the table
above.

---

## Tutorials — learning by doing

- [**Quick start**](tutorials/quick-start.md) — Get a live Wolt quote in
  WooCommerce checkout and dispatch your first sandbox delivery in
  under 10 minutes. Assumes you have Wolt sandbox credentials.

## How-to guides — getting a specific job done

- [**Install & configure**](how-to/install-and-configure.md) — Full
  production-grade setup: prerequisites, deployment, every settings
  field explained in order.
- [**Dispatch API guide**](how-to/dispatch-api.md) — Use the public
  REST endpoint from external systems (CRM, automations, n8n, Zapier,
  custom mobile apps).
- [**Translate the plugin**](how-to/translate-the-plugin.md) —
  Workflow for adding a new language or extending Hebrew.
- [**Troubleshoot**](how-to/troubleshoot.md) — Common failure modes
  and how to diagnose them with `debug.log`.

## Reference — facts you look up

- [**Architecture**](reference/architecture.md) — File map, class
  responsibilities, runtime flow diagrams.
- [**Settings**](reference/settings.md) — Every WordPress option the
  plugin owns. Format, defaults, sanitisation.
- [**Order meta**](reference/order-meta.md) — Every per-order meta key.
  Where it's set from, when it's populated, what it means.
- [**Wolt API mapping**](reference/wolt-api-mapping.md) — Which Wolt
  endpoints the plugin calls, what triggers each call, what we send
  and what we extract.
- [**Webhook events**](reference/webhook-events.md) — The inbound
  events Wolt send and what the plugin does with each.
- [**Hooks & filters**](reference/hooks-and-filters.md) — Extension
  points for theme / other-plugin developers.

## Explanation — understanding the *why*

- [**Host plugin contract**](explanation/host-plugin-contract.md) —
  Why this plugin coexists with the OC Advanced Shipping plugin
  instead of replacing it.
- [**Design decisions**](explanation/design-decisions.md) —
  Non-obvious choices that look weird in code (FLAT shipment-promise
  body, two settings groups, two order-status hooks…) with the
  reasoning behind each.

---

## Conventions used in these docs

- **Code blocks** with a language tag (`bash`, `php`, `json`, …) are
  copy-pasteable.
- **Tables** are reference material — scan for what you need, don't
  read top-to-bottom.
- **Inline `code`** marks WordPress identifiers (option names,
  hook names, class names, meta keys).
- **Mermaid diagrams** render on GitHub. Locally, use a Markdown
  preview that supports them, or read the raw text inside the
  `mermaid` fence (it's plain English).
- Examples target the **Delinka site** (`delinka.deliz.co.il`) and
  Wolt's **sandbox** unless noted. Swap the base URL when moving to
  production.

---

## Quick reference card

```
WP admin menu       Wolt Drive (top level)
Dispatch endpoint   POST /wp-json/ocws-wolt/v1/dispatch
Webhook endpoint    POST /wp-json/ocws-wolt/v1/webhook
Sandbox base URL    https://daas-public-api.development.dev.woltapi.com
Production URL      https://daas-public-api.wolt.com
Text domain         oc-wolt-drive
Options prefix      ocws_wolt_*
Order meta prefix   _ocws_wolt_*
Log tags            [OC Wolt SP]   shipment-promises (quote)
                    [OC Wolt CD]   create-delivery
                    [OC Wolt AV]   available-venues
                    [OC Wolt Cancel]
```
