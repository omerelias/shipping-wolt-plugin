<?php
/**
 * Standalone admin console for OC Wolt Drive.
 *
 * - Top-level "Wolt Drive" menu in WP Admin
 * - Three tabs: Settings, Webhook, Tools
 * - AJAX endpoints for connection test, secret generation, and the quote simulator
 *
 * @package OC_Wolt_Drive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OCWS_Wolt_Admin
 */
class OCWS_Wolt_Admin {

	const MENU_SLUG  = 'oc-wolt-drive';
	const CAPABILITY = 'manage_woocommerce';

	const NONCE_AJAX = 'ocws_wolt_admin';

	/**
	 * Register menu, assets, and AJAX handlers.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . OCWS_WOLT_BASENAME, array( __CLASS__, 'plugin_action_links' ) );

		add_action( 'wp_ajax_ocws_wolt_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ocws_wolt_generate_secret', array( __CLASS__, 'ajax_generate_secret' ) );
		add_action( 'wp_ajax_ocws_wolt_simulate', array( __CLASS__, 'ajax_simulate' ) );
	}

	/**
	 * Register the top-level menu entry.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Wolt Drive', 'oc-wolt-drive' ),
			__( 'Wolt Drive', 'oc-wolt-drive' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-car',
			56
		);
	}

	/**
	 * Add a "Settings" shortcut on the Plugins screen row.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'oc-wolt-drive' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Enqueue admin CSS + JS, only on the plugin page.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( strpos( (string) $hook_suffix, self::MENU_SLUG ) === false ) {
			return;
		}
		wp_enqueue_style(
			'ocws-wolt-admin',
			OCWS_WOLT_URL . 'assets/css/admin.css',
			array(),
			OCWS_WOLT_VERSION
		);
		wp_enqueue_script(
			'ocws-wolt-admin',
			OCWS_WOLT_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			OCWS_WOLT_VERSION,
			true
		);
		wp_localize_script(
			'ocws-wolt-admin',
			'OCWSWolt',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( self::NONCE_AJAX ),
				'webhookUrl' => esc_url_raw( rest_url( 'ocws-wolt/v1/webhook' ) ),
				'i18n'       => array(
					'testing'    => __( 'Testing…', 'oc-wolt-drive' ),
					'connOk'     => __( 'Connected. Wolt returned %d delivery area(s).', 'oc-wolt-drive' ),
					'connFail'   => __( 'Connection failed.', 'oc-wolt-drive' ),
					'simRunning' => __( 'Running simulation…', 'oc-wolt-drive' ),
					'copied'     => __( 'Copied to clipboard.', 'oc-wolt-drive' ),
				),
			)
		);
	}

	/**
	 * Resolve the current tab from the query string.
	 */
	protected static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $tab, array( 'settings', 'webhook', 'tools' ), true ) ? $tab : 'settings';
	}

	/* ─── Page rendering ─────────────────────────────────────────── */

	/**
	 * Render the whole admin page.
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'oc-wolt-drive' ) );
		}
		$tab = self::current_tab();
		$host_active = ocws_wolt_host_shipping_active();
		?>
		<div class="wrap ocws-wolt-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Wolt Drive', 'oc-wolt-drive' ); ?></h1>
			<p class="ocws-wolt-subtitle"><?php esc_html_e( 'Wolt Drive courier integration for WooCommerce.', 'oc-wolt-drive' ); ?></p>

			<?php self::render_status_strip( $host_active ); ?>

			<nav class="nav-tab-wrapper ocws-wolt-tabs">
				<?php
				$tabs = array(
					'settings' => __( 'Settings', 'oc-wolt-drive' ),
					'webhook'  => __( 'Webhook', 'oc-wolt-drive' ),
					'tools'    => __( 'Tools', 'oc-wolt-drive' ),
				);
				foreach ( $tabs as $slug => $label ) {
					$url   = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) );
					$class = 'nav-tab' . ( $tab === $slug ? ' nav-tab-active' : '' );
					echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
				}
				?>
			</nav>

			<?php
			switch ( $tab ) {
				case 'webhook':
					self::render_webhook_tab();
					break;
				case 'tools':
					self::render_tools_tab();
					break;
				default:
					self::render_settings_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Status indicators at the top of every tab.
	 *
	 * @param bool $host_active Host shipping plugin detected?
	 */
	protected static function render_status_strip( $host_active ) {
		$enabled    = OCWS_Wolt_Settings::is_enabled();
		$configured = '' !== OCWS_Wolt_Settings::get_venue_id() && '' !== get_option( OCWS_Wolt_Settings::OPTION_API_KEY, '' );
		$secret     = '' !== OCWS_Wolt_Settings::get_webhook_secret();
		?>
		<div class="ocws-wolt-status">
			<div class="ocws-wolt-status-item <?php echo $enabled ? 'is-ok' : 'is-warn'; ?>">
				<span class="dot"></span>
				<?php esc_html_e( 'Plugin enabled', 'oc-wolt-drive' ); ?>
			</div>
			<div class="ocws-wolt-status-item <?php echo $configured ? 'is-ok' : 'is-bad'; ?>">
				<span class="dot"></span>
				<?php esc_html_e( 'API credentials', 'oc-wolt-drive' ); ?>
			</div>
			<div class="ocws-wolt-status-item <?php echo $secret ? 'is-ok' : 'is-warn'; ?>">
				<span class="dot"></span>
				<?php esc_html_e( 'Webhook signing', 'oc-wolt-drive' ); ?>
			</div>
			<div class="ocws-wolt-status-item <?php echo $host_active ? 'is-ok' : 'is-warn'; ?>">
				<span class="dot"></span>
				<?php esc_html_e( 'Host shipping plugin', 'oc-wolt-drive' ); ?>
			</div>
		</div>
		<?php
	}

	/* ─── Tab: Settings ───────────────────────────────────────────── */

	/**
	 * Render the main settings form.
	 */
	protected static function render_settings_tab() {
		$statuses = wc_get_order_statuses();
		?>
		<form method="post" action="options.php" class="ocws-wolt-form">
			<?php settings_fields( OCWS_Wolt_Settings::SETTINGS_GROUP ); ?>

			<div class="ocws-wolt-card">
				<h2><?php esc_html_e( 'General', 'oc-wolt-drive' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::render_checkbox(
						OCWS_Wolt_Settings::OPTION_ENABLED,
						__( 'Enable Wolt Drive', 'oc-wolt-drive' ),
						__( 'Override shipping price with Wolt quote at checkout (falls back to default if Wolt is unavailable).', 'oc-wolt-drive' )
					);
					self::render_text(
						OCWS_Wolt_Settings::OPTION_PICKUP_ADDRESS,
						__( 'Pickup address (venue)', 'oc-wolt-drive' ),
						__( 'Where Wolt picks up the order. Leave empty to fall back to the WooCommerce store address.', 'oc-wolt-drive' ),
						OCWS_Wolt_Settings::get_woocommerce_store_address_formatted()
					);
					self::render_select(
						OCWS_Wolt_Settings::OPTION_TRIGGER_STATUS,
						__( 'Auto-dispatch on status', 'oc-wolt-drive' ),
						$statuses,
						__( 'Order status that auto-creates the Wolt delivery. Manual dispatch is always available from the order screen.', 'oc-wolt-drive' )
					);
					self::render_number(
						OCWS_Wolt_Settings::OPTION_DISPATCH_OFFSET,
						__( 'Dispatch offset (minutes)', 'oc-wolt-drive' ),
						__( 'Minutes after the chosen slot start at which Wolt should arrive (e.g. 30 = 16:30 if the slot is 16:00–19:00).', 'oc-wolt-drive' )
					);
					?>
				</table>
			</div>

			<div class="ocws-wolt-card">
				<h2><?php esc_html_e( 'API connection', 'oc-wolt-drive' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::render_text(
						OCWS_Wolt_Settings::OPTION_API_URL,
						__( 'API base URL', 'oc-wolt-drive' ),
						sprintf(
							/* translators: 1: sandbox URL, 2: production URL */
							__( 'Sandbox: %1$s · Production: %2$s', 'oc-wolt-drive' ),
							'<code>' . esc_html( OCWS_Wolt_Settings::DEFAULT_SANDBOX_URL ) . '</code>',
							'<code>' . esc_html( OCWS_Wolt_Settings::DEFAULT_PRODUCTION_URL ) . '</code>'
						),
						'',
						array( 'allow_html_desc' => true, 'type' => 'url' )
					);
					self::render_text(
						OCWS_Wolt_Settings::OPTION_API_KEY,
						__( 'API key (Merchant Key)', 'oc-wolt-drive' ),
						__( 'Bearer token from Wolt. Stored as plaintext in WP options — protect database access.', 'oc-wolt-drive' ),
						'',
						array( 'type' => 'password' )
					);
					self::render_text(
						OCWS_Wolt_Settings::OPTION_VENUE_ID,
						__( 'Venue ID', 'oc-wolt-drive' ),
						__( 'Wolt venue identifier (path parameter for /v1/venues/{venue_id}/*).', 'oc-wolt-drive' )
					);
					self::render_text(
						OCWS_Wolt_Settings::OPTION_MERCHANT_ID,
						__( 'Merchant ID', 'oc-wolt-drive' ),
						__( 'Used for /merchants/{merchant_id}/delivery-areas and similar venueless endpoints.', 'oc-wolt-drive' )
					);
					self::render_text(
						OCWS_Wolt_Settings::OPTION_CURRENCY,
						__( 'Currency', 'oc-wolt-drive' ),
						__( 'ISO 4217 code, e.g. ILS, USD. Used when sending parcel prices to Wolt.', 'oc-wolt-drive' ),
						'',
						array( 'attrs' => 'maxlength="3" size="5"' )
					);
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test connection', 'oc-wolt-drive' ); ?></th>
						<td>
							<button type="button" class="button" id="ocws-wolt-test-connection"><?php esc_html_e( 'Run /delivery-areas call', 'oc-wolt-drive' ); ?></button>
							<span id="ocws-wolt-test-result" class="ocws-wolt-inline-msg"></span>
							<p class="description"><?php esc_html_e( 'Performs a read-only GET against the configured API URL to confirm credentials work.', 'oc-wolt-drive' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="ocws-wolt-card">
				<h2><?php esc_html_e( 'Pricing markup', 'oc-wolt-drive' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$markup_type = OCWS_Wolt_Settings::get_markup_type();
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Markup type', 'oc-wolt-drive' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( OCWS_Wolt_Settings::OPTION_MARKUP_TYPE ); ?>">
								<option value="fixed" <?php selected( $markup_type, 'fixed' ); ?>><?php esc_html_e( 'Fixed amount', 'oc-wolt-drive' ); ?></option>
								<option value="percentage" <?php selected( $markup_type, 'percentage' ); ?>><?php esc_html_e( 'Percentage', 'oc-wolt-drive' ); ?></option>
							</select>
						</td>
					</tr>
					<?php
					self::render_number(
						OCWS_Wolt_Settings::OPTION_MARKUP_VALUE,
						__( 'Markup value', 'oc-wolt-drive' ),
						__( 'Fixed amount (in store currency) or percentage (e.g. 10 = +10%).', 'oc-wolt-drive' ),
						array( 'min' => 0, 'step' => 0.01 )
					);
					?>
				</table>
			</div>

			<div class="ocws-wolt-card">
				<h2><?php esc_html_e( 'Advanced', 'oc-wolt-drive' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::render_text(
						OCWS_Wolt_Settings::OPTION_METHOD_ID_PREFIX,
						__( 'Host shipping method ID prefix', 'oc-wolt-drive' ),
						sprintf(
							/* translators: %s: default method id */
							__( 'Shipping method ID prefix to recognise as eligible for Wolt dispatch. Default: %s', 'oc-wolt-drive' ),
							'<code>' . esc_html( OCWS_Wolt_Settings::DEFAULT_METHOD_ID_PREFIX ) . '</code>'
						),
						'',
						array( 'allow_html_desc' => true )
					);
					?>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/* ─── Tab: Webhook ────────────────────────────────────────────── */

	/**
	 * Render webhook tab: URL, secret, registration instructions.
	 */
	protected static function render_webhook_tab() {
		$webhook_url = rest_url( 'ocws-wolt/v1/webhook' );
		$secret      = OCWS_Wolt_Settings::get_webhook_secret();
		?>
		<div class="ocws-wolt-card">
			<h2><?php esc_html_e( 'Webhook endpoint', 'oc-wolt-drive' ); ?></h2>
			<p><?php esc_html_e( 'Provide this URL and the shared secret below to Wolt when registering the webhook (see the Wolt Drive webhook docs).', 'oc-wolt-drive' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'URL', 'oc-wolt-drive' ); ?></th>
					<td>
						<input type="text" class="regular-text code" readonly value="<?php echo esc_attr( $webhook_url ); ?>" id="ocws-wolt-webhook-url" />
						<button type="button" class="button ocws-wolt-copy" data-copy-target="#ocws-wolt-webhook-url"><?php esc_html_e( 'Copy', 'oc-wolt-drive' ); ?></button>
					</td>
				</tr>
			</table>
		</div>

		<form method="post" action="options.php" class="ocws-wolt-form">
			<?php settings_fields( OCWS_Wolt_Settings::SETTINGS_GROUP ); ?>
			<div class="ocws-wolt-card">
				<h2><?php esc_html_e( 'Shared secret (HS256)', 'oc-wolt-drive' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'You generate this secret here, then give it to Wolt at webhook registration. Wolt signs every event JWT with it; this plugin verifies the signature on every incoming request and rejects anything that does not match.', 'oc-wolt-drive' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Secret', 'oc-wolt-drive' ); ?></th>
						<td>
							<input type="text"
								name="<?php echo esc_attr( OCWS_Wolt_Settings::OPTION_WEBHOOK_SECRET ); ?>"
								id="ocws-wolt-webhook-secret"
								class="regular-text code"
								value="<?php echo esc_attr( $secret ); ?>"
								autocomplete="off"
								placeholder="<?php esc_attr_e( 'Click "Generate" to create a new secret', 'oc-wolt-drive' ); ?>" />
							<button type="button" class="button" id="ocws-wolt-generate-secret"><?php esc_html_e( 'Generate', 'oc-wolt-drive' ); ?></button>
							<button type="button" class="button ocws-wolt-copy" data-copy-target="#ocws-wolt-webhook-secret"><?php esc_html_e( 'Copy', 'oc-wolt-drive' ); ?></button>
							<p class="description"><?php esc_html_e( 'Hash-equals verification, time-constant. Store securely; rotate by generating a new value and re-registering at Wolt.', 'oc-wolt-drive' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
			<?php submit_button( __( 'Save secret', 'oc-wolt-drive' ) ); ?>
		</form>

		<div class="ocws-wolt-card">
			<h2><?php esc_html_e( 'How to register at Wolt', 'oc-wolt-drive' ); ?></h2>
			<p><?php
				printf(
					wp_kses_post(
						/* translators: %s: link */
						__( 'See the official %s for the create-webhook endpoint and accepted event types.', 'oc-wolt-drive' )
					),
					'<a href="https://developer.wolt.com/docs/wolt-drive/webhooks#create-a-webhook" target="_blank" rel="noopener">' . esc_html__( 'Wolt webhook docs', 'oc-wolt-drive' ) . '</a>'
				);
			?></p>
			<p class="description"><?php esc_html_e( 'Wolt currently expects merchants to register webhooks via their merchant tooling rather than a public REST call. Send Wolt the URL and the secret above.', 'oc-wolt-drive' ); ?></p>
		</div>
		<?php
	}

	/* ─── Tab: Tools ──────────────────────────────────────────────── */

	/**
	 * Render tools tab: quote simulator + scheduled time preview.
	 */
	protected static function render_tools_tab() {
		?>
		<div class="ocws-wolt-card">
			<h2><?php esc_html_e( 'Quote simulator', 'oc-wolt-drive' ); ?></h2>
			<p><?php esc_html_e( 'Send a test shipment-promise to Wolt and preview the scheduled dropoff time the plugin would calculate for a given slot.', 'oc-wolt-drive' ); ?></p>

			<form id="ocws-wolt-sim-form" class="ocws-wolt-form">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sim_address"><?php esc_html_e( 'Address', 'oc-wolt-drive' ); ?></label></th>
						<td><input id="sim_address" type="text" name="address" class="regular-text" placeholder="<?php esc_attr_e( 'Street, City', 'oc-wolt-drive' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="sim_lat"><?php esc_html_e( 'Latitude', 'oc-wolt-drive' ); ?></label></th>
						<td><input id="sim_lat" type="text" name="lat" placeholder="32.0853" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="sim_lng"><?php esc_html_e( 'Longitude', 'oc-wolt-drive' ); ?></label></th>
						<td><input id="sim_lng" type="text" name="lng" placeholder="34.7818" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="sim_slot_date"><?php esc_html_e( 'Slot date (d/m/Y)', 'oc-wolt-drive' ); ?></label></th>
						<td><input id="sim_slot_date" type="text" name="slot_date" placeholder="25/02/2026" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="sim_slot_start"><?php esc_html_e( 'Slot start (HH:MM)', 'oc-wolt-drive' ); ?></label></th>
						<td><input id="sim_slot_start" type="text" name="slot_start" placeholder="16:00" class="regular-text" /></td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Run simulation', 'oc-wolt-drive' ); ?></button></p>
			</form>

			<div id="ocws-wolt-sim-result" class="ocws-wolt-result"></div>
		</div>
		<?php
	}

	/* ─── Field helpers ─────────────────────────────────────────── */

	protected static function render_checkbox( $option, $label, $desc ) {
		$value = get_option( $option, '' );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label class="ocws-wolt-switch">
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1" <?php checked( '1', $value ); ?> />
					<span class="ocws-wolt-switch-slider"></span>
				</label>
				<p class="description"><?php echo esc_html( $desc ); ?></p>
			</td>
		</tr>
		<?php
	}

	protected static function render_text( $option, $label, $desc, $placeholder = '', $opts = array() ) {
		$value = get_option( $option, '' );
		$type  = isset( $opts['type'] ) ? $opts['type'] : 'text';
		$attrs = isset( $opts['attrs'] ) ? $opts['attrs'] : '';
		$allow_html = ! empty( $opts['allow_html_desc'] );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<input type="<?php echo esc_attr( $type ); ?>"
					name="<?php echo esc_attr( $option ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>"
					class="regular-text"
					autocomplete="off"
					<?php echo $attrs; // already controlled string, no user input ?> />
				<p class="description">
					<?php echo $allow_html ? wp_kses_post( $desc ) : esc_html( $desc ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	protected static function render_number( $option, $label, $desc, $opts = array() ) {
		$value = get_option( $option, '' );
		$min   = isset( $opts['min'] ) ? $opts['min'] : 0;
		$step  = isset( $opts['step'] ) ? $opts['step'] : 1;
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<input type="number"
					name="<?php echo esc_attr( $option ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					min="<?php echo esc_attr( $min ); ?>"
					step="<?php echo esc_attr( $step ); ?>" />
				<p class="description"><?php echo esc_html( $desc ); ?></p>
			</td>
		</tr>
		<?php
	}

	protected static function render_select( $option, $label, $choices, $desc ) {
		$value = get_option( $option, '' );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<select name="<?php echo esc_attr( $option ); ?>">
					<?php foreach ( $choices as $val => $text ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $value, $val ); ?>><?php echo esc_html( $text ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php echo esc_html( $desc ); ?></p>
			</td>
		</tr>
		<?php
	}

	/* ─── AJAX endpoints ──────────────────────────────────────── */

	/**
	 * Helper: verify nonce + capability for every AJAX hit.
	 */
	protected static function verify_ajax() {
		check_ajax_referer( self::NONCE_AJAX, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'oc-wolt-drive' ) ), 403 );
		}
	}

	/**
	 * AJAX: GET /merchants/{id}/delivery-areas to verify credentials.
	 */
	public static function ajax_test_connection() {
		self::verify_ajax();
		$result = OCWS_Wolt_Api::get_delivery_areas();
		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => isset( $result['error'] ) ? $result['error'] : __( 'Unknown error.', 'oc-wolt-drive' ) ) );
		}
		$areas = isset( $result['areas'] ) ? $result['areas'] : array();
		$count = is_array( $areas )
			? ( isset( $areas['delivery_areas'] ) && is_array( $areas['delivery_areas'] ) ? count( $areas['delivery_areas'] ) : count( $areas ) )
			: 0;
		wp_send_json_success( array( 'count' => $count ) );
	}

	/**
	 * AJAX: return a freshly generated secret. Caller writes it into the
	 * form field and submits — we never auto-save.
	 */
	public static function ajax_generate_secret() {
		self::verify_ajax();
		// 48-char URL-safe random secret (~286 bits of entropy).
		$secret = function_exists( 'wp_generate_password' )
			? wp_generate_password( 48, false, false )
			: bin2hex( random_bytes( 24 ) );
		wp_send_json_success( array( 'secret' => $secret ) );
	}

	/**
	 * AJAX: run the quote simulator with a manual address + slot.
	 */
	public static function ajax_simulate() {
		self::verify_ajax();

		$address    = isset( $_POST['address'] )    ? sanitize_text_field( wp_unslash( $_POST['address'] ) )    : '';
		$lat        = isset( $_POST['lat'] )        ? sanitize_text_field( wp_unslash( $_POST['lat'] ) )        : '';
		$lng        = isset( $_POST['lng'] )        ? sanitize_text_field( wp_unslash( $_POST['lng'] ) )        : '';
		$slot_date  = isset( $_POST['slot_date'] )  ? sanitize_text_field( wp_unslash( $_POST['slot_date'] ) )  : '';
		$slot_start = isset( $_POST['slot_start'] ) ? sanitize_text_field( wp_unslash( $_POST['slot_start'] ) ) : '';

		$destination = array(
			'address'  => $address,
			'city'     => '',
			'postcode' => '',
		);
		if ( '' !== $lat && '' !== $lng && is_numeric( $lat ) && is_numeric( $lng ) ) {
			$destination['address_coords'] = array( 'lat' => (float) $lat, 'lng' => (float) $lng );
		}

		$price_result = OCWS_Wolt_Api::get_shipment_promise( $destination );

		$scheduled = '';
		if ( '' !== $slot_date && '' !== $slot_start ) {
			$scheduled = self::preview_scheduled_time( $slot_date, $slot_start );
		}

		$html  = '<h3>' . esc_html__( 'Shipment promise', 'oc-wolt-drive' ) . '</h3>';
		if ( ! empty( $price_result['success'] ) ) {
			$with_markup = OCWS_Wolt_Settings::apply_markup( (float) $price_result['cost'] );
			$html .= '<p><strong>' . esc_html__( 'Quote', 'oc-wolt-drive' ) . ':</strong> ' . esc_html( number_format_i18n( $price_result['cost'], 2 ) ) . '</p>';
			$html .= '<p><strong>' . esc_html__( 'With markup', 'oc-wolt-drive' ) . ':</strong> ' . esc_html( number_format_i18n( $with_markup, 2 ) ) . '</p>';
		} else {
			$html .= '<p class="ocws-wolt-error">' . esc_html( isset( $price_result['error'] ) ? $price_result['error'] : 'Error' ) . '</p>';
		}
		$html .= '<h3>' . esc_html__( 'Scheduled dropoff time', 'oc-wolt-drive' ) . '</h3>';
		if ( '' !== $scheduled ) {
			$html .= '<p><code>' . esc_html( $scheduled ) . '</code></p>';
		} else {
			$html .= '<p>' . esc_html__( 'No slot provided or invalid format — delivery would be sent as ASAP.', 'oc-wolt-drive' ) . '</p>';
		}
		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Compute the same ISO 8601 string the trigger would send, given manual inputs.
	 *
	 * @param string $slot_date  d/m/Y.
	 * @param string $slot_start HH:MM.
	 * @return string Empty on parse failure.
	 */
	protected static function preview_scheduled_time( $slot_date, $slot_start ) {
		$tz_str = function_exists( 'ocws_get_timezone' ) ? ocws_get_timezone() : wp_timezone_string();
		try {
			$tz   = new DateTimeZone( $tz_str );
			$date = DateTime::createFromFormat( 'd/m/Y', $slot_date, $tz );
		} catch ( Exception $e ) {
			return '';
		}
		if ( ! $date || ! preg_match( '/^(\d{1,2}):(\d{2})$/', trim( $slot_start ), $m ) ) {
			return '';
		}
		$date->setTime( (int) $m[1], (int) $m[2], 0 );
		$offset_min = OCWS_Wolt_Settings::get_dispatch_offset_minutes();
		if ( $offset_min > 0 ) {
			$date->add( new DateInterval( 'PT' . $offset_min . 'M' ) );
		}
		return $date->format( DateTimeInterface::ATOM );
	}
}
