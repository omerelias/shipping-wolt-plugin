# Hooks & filters

The plugin doesn't define many custom hooks itself yet, but it
**listens** to a specific set of WordPress / WooCommerce hooks, and
the right way to extend the plugin from theme code or another plugin
is to attach to those alongside it.

This page lists every hook the plugin attaches to and shows the
extension points you'd most likely want to add to your own code.

> Future versions may add `ocws_wolt_*` custom filters for things
> like "modify the payload before sending" or "veto a dispatch". If
> you need one specifically, open an issue.

---

## WordPress / WooCommerce hooks the plugin uses

### Lifecycle

| Hook | Priority | Class::method | Why |
|---|---|---|---|
| `plugins_loaded` | 20 | `ocws_wolt_bootstrap()` | Load text domain, env-check, require classes, kick off `OCWS_Wolt::load()`. |
| `before_woocommerce_init` | default | (anonymous) | Declare HPOS compatibility. |
| `init` | default | `OCWS_Wolt::register_options` | Register settings options + sanitisers. |
| `init` | 5 | `OCWS_Wolt::init_components` | Init every runtime class (price-override, trigger, meta-box, webhook, dispatch-api, frontend). |
| `admin_menu` | default | `OCWS_Wolt_Admin::register_menu` | Top-level "Wolt Drive" menu. |
| `admin_enqueue_scripts` | default | `OCWS_Wolt_Admin::enqueue_assets` | Loads `assets/css/admin.css` + `assets/js/admin.js` ONLY on the plugin page. |
| `plugin_action_links_{basename}` | default | `OCWS_Wolt_Admin::plugin_action_links` | Adds "Settings" link on the Plugins screen. |
| `wp_enqueue_scripts` | default | `OCWS_Wolt_Frontend::enqueue_assets` | Loads `assets/css/frontend.css` ONLY on order-received / view-order. |

### Order flow

| Hook | Priority | Class::method | What we do |
|---|---|---|---|
| `woocommerce_package_rates` | 20 | `OCWS_Wolt_Price_Override::filter_package_rates` | Override the host shipping method's cost with the Wolt quote. |
| `woocommerce_order_status_changed` | 10 | `OCWS_Wolt_Delivery_Trigger::on_status_changed` | Auto-dispatch when the new status matches `ocws_wolt_trigger_status`. |
| `woocommerce_new_order` | 10 | `OCWS_Wolt_Delivery_Trigger::on_new_order` | Same, for orders created already in the trigger status. |
| `add_meta_boxes` | default | `OCWS_Wolt_Order_Meta_Box::add_meta_box` | Register the Wolt Drive meta box on the order screen (HPOS-aware). |
| `admin_post_ocws_wolt_create_delivery` | default | `OCWS_Wolt_Order_Meta_Box::handle_create_action` | "Create Wolt delivery now" button POSTs here. |
| `woocommerce_order_details_after_order_table` | 5 | `OCWS_Wolt_Frontend::render_tracking_card` | Render the customer tracking card on thank-you / view-order. |
| `woocommerce_email_order_meta_fields` | 10 | `OCWS_Wolt_Frontend::email_meta_fields` | Add a "Track your delivery" row to customer-facing WC emails. |

### REST endpoints

| Hook | Priority | Class::method | What it registers |
|---|---|---|---|
| `rest_api_init` | default | `OCWS_Wolt_Webhook::register_route` | `POST /ocws-wolt/v1/webhook` |
| `rest_api_init` | default | `OCWS_Wolt_Dispatch_Api::register_route` | `POST /ocws-wolt/v1/dispatch` |

### AJAX endpoints

All under `wp_ajax_*` (admin-side only — no `wp_ajax_nopriv_` variants).

| Action | Class::method | Surfaced by which UI |
|---|---|---|
| `ocws_wolt_test_connection` | `OCWS_Wolt_Admin::ajax_test_connection` | "Run /delivery-areas call" button on Settings tab |
| `ocws_wolt_generate_secret` | `OCWS_Wolt_Admin::ajax_generate_secret` | "Generate" on Webhook tab (HS256 secret) |
| `ocws_wolt_simulate` | `OCWS_Wolt_Admin::ajax_simulate` | Quote simulator on Tools tab |
| `ocws_wolt_register_webhook` | `OCWS_Wolt_Admin::ajax_register_webhook` | "Register webhook with Wolt" / "Re-register" |
| `ocws_wolt_unregister_webhook` | `OCWS_Wolt_Admin::ajax_unregister_webhook` | "Unregister" |
| `ocws_wolt_cancel_delivery` | `OCWS_Wolt_Admin::ajax_cancel_delivery` | Cancel button + reason modal in Deliveries tab |
| `ocws_wolt_generate_dispatch_key` | `OCWS_Wolt_Admin::ajax_generate_dispatch_key` | "Generate" on Dispatch API card |

Each handler verifies `check_ajax_referer( self::NONCE_AJAX, 'nonce' )`
and `current_user_can( 'manage_woocommerce' )`.

---

## Extension recipes

Things you'd realistically want to do from outside code.

### Add custom data to a customer order note

Hook the same WC event the plugin uses, but at a later priority so
our note has already been added:

```php
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to ) {
    if ( 'processing' === $to ) {
        // We've already triggered Wolt by now (priority 10).
        // Read our meta:
        $order = wc_get_order( $order_id );
        $tracking = $order->get_meta( '_ocws_wolt_tracking_url' );
        if ( $tracking ) {
            $order->add_order_note( "Custom: also notify our CRM about $tracking" );
        }
    }
}, 20, 3 );
```

### React to courier location updates

The plugin updates `_ocws_wolt_courier_lat/lng/at` on every
`order.location_updated` event but doesn't fire a custom hook for
extending. Easiest:

```php
add_action( 'updated_post_meta', function ( $meta_id, $post_id, $meta_key, $meta_value ) {
    if ( '_ocws_wolt_courier_lat' === $meta_key ) {
        $order = wc_get_order( $post_id );
        if ( $order ) {
            // Your code — push to a map, broadcast over WebSocket, …
        }
    }
}, 10, 4 );
```

(Or use HPOS-aware `woocommerce_order_object_updated_props` if you're
on HPOS.)

### Intercept the create-delivery payload

Today there's no filter — but you can re-read the meta we wrote and
PATCH externally on Wolt's side, or hook into our internal flow by
extending `OCWS_Wolt_Delivery_Trigger`. If you need a real filter, open
an issue — adding one is small.

### Suppress price-override for specific carts

```php
add_filter( 'woocommerce_package_rates', function ( $rates, $package ) {
    if ( WC()->cart->get_subtotal() < 50 ) {
        // Temporarily disable Wolt for tiny baskets:
        // remove our filter before WC re-resolves rates
        remove_filter( 'woocommerce_package_rates', array( 'OCWS_Wolt_Price_Override', 'filter_package_rates' ), 20 );
    }
    return $rates;
}, 5, 2 );
```

(Priority 5 — runs before our 20.)

### Customise the customer tracking card

The card is rendered by `OCWS_Wolt_Frontend::render_tracking_card`,
hooked into `woocommerce_order_details_after_order_table` at priority 5.

```php
// Remove the default card
remove_action( 'woocommerce_order_details_after_order_table', array( 'OCWS_Wolt_Frontend', 'render_tracking_card' ), 5 );

// Render your own
add_action( 'woocommerce_order_details_after_order_table', function ( $order ) {
    if ( ! $order instanceof WC_Order ) return;
    $url = $order->get_meta( '_ocws_wolt_tracking_url' );
    if ( $url ) {
        echo '<div class="my-custom-card"><a href="' . esc_url( $url ) . '">Track me</a></div>';
    }
}, 5 );
```

---

## See also

- [Architecture](architecture.md) — which class owns which hook.
- [Settings reference](settings.md) — meta keys + options you'd read in
  your own hooks.
- [Dispatch API guide](../how-to/dispatch-api.md) — if you can drive
  things via the REST endpoint, that's almost always cleaner than
  hooking PHP internals.
