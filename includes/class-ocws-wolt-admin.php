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
if ( ! class_exists( 'OCWS_Wolt_Admin' ) ) :
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

		add_action( 'wp_ajax_ocws_wolt_test_connection',     array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ocws_wolt_generate_secret',     array( __CLASS__, 'ajax_generate_secret' ) );
		add_action( 'wp_ajax_ocws_wolt_simulate',            array( __CLASS__, 'ajax_simulate' ) );
		add_action( 'wp_ajax_ocws_wolt_register_webhook',    array( __CLASS__, 'ajax_register_webhook' ) );
		add_action( 'wp_ajax_ocws_wolt_unregister_webhook',  array( __CLASS__, 'ajax_unregister_webhook' ) );
		add_action( 'wp_ajax_ocws_wolt_cancel_delivery',     array( __CLASS__, 'ajax_cancel_delivery' ) );
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
					'testing'        => __( 'Testing…', 'oc-wolt-drive' ),
					/* translators: %d: number of delivery areas Wolt returned */
					'connOk'         => __( 'Connected. Wolt returned %d delivery area(s).', 'oc-wolt-drive' ),
					'connFail'       => __( 'Connection failed.', 'oc-wolt-drive' ),
					'simRunning'     => __( 'Running simulation…', 'oc-wolt-drive' ),
					'copied'         => __( 'Copied to clipboard.', 'oc-wolt-drive' ),
					'registering'    => __( 'Registering webhook at Wolt…', 'oc-wolt-drive' ),
					'unregistering'  => __( 'Unregistering webhook…', 'oc-wolt-drive' ),
					'registerOk'     => __( 'Webhook registered. ID stored. Wolt will start sending events.', 'oc-wolt-drive' ),
					'unregisterOk'   => __( 'Webhook unregistered.', 'oc-wolt-drive' ),
					'confirmUnreg'    => __( 'Stop receiving Wolt events for this site? You can re-register at any time.', 'oc-wolt-drive' ),
					'cancelling'      => __( 'Cancelling…', 'oc-wolt-drive' ),
					'cancelOk'        => __( 'Delivery cancelled.', 'oc-wolt-drive' ),
					'confirmCancel'   => __( 'Cancel this Wolt delivery? This cannot be undone.', 'oc-wolt-drive' ),
					'reasonRequired'  => __( 'Please pick a cancellation reason.', 'oc-wolt-drive' ),
				),
			)
		);
	}

	/**
	 * Resolve the current tab from the query string.
	 */
	protected static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'deliveries'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $tab, array( 'deliveries', 'settings', 'webhook', 'tools' ), true ) ? $tab : 'deliveries';
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

			<nav class="ocws-wolt-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Wolt sections', 'oc-wolt-drive' ); ?>">
				<?php
				$tabs = array(
					'deliveries' => __( 'Deliveries', 'oc-wolt-drive' ),
					'settings'   => __( 'Settings', 'oc-wolt-drive' ),
					'webhook'    => __( 'Webhook', 'oc-wolt-drive' ),
					'tools'      => __( 'Tools', 'oc-wolt-drive' ),
				);
				foreach ( $tabs as $slug => $label ) {
					$url      = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) );
					$active   = $tab === $slug;
					$class    = 'ocws-wolt-tab' . ( $active ? ' is-active' : '' );
					$selected = $active ? ' aria-current="page"' : '';
					echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '" role="tab"' . $selected . '>' . esc_html( $label ) . '</a>';
				}
				?>
			</nav>

			<?php
			switch ( $tab ) {
				case 'webhook':
					self::render_webhook_tab();
					break;
				case 'deliveries':
					self::render_deliveries_tab();
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
			<?php settings_fields( OCWS_Wolt_Settings::SETTINGS_GROUP_WEBHOOK ); ?>
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
			<h2><?php esc_html_e( 'Registration with Wolt', 'oc-wolt-drive' ); ?></h2>
			<?php self::render_webhook_registration_block(); ?>
			<p class="description">
				<?php
				printf(
					wp_kses_post(
						/* translators: %s: link */
						__( 'Calls %s under the hood. You only need to do this once per merchant.', 'oc-wolt-drive' )
					),
					'<code>POST /v1/merchants/{merchant_id}/webhooks</code>'
				);
				?>
				<a href="https://developer.wolt.com/docs/wolt-drive/webhooks#create-a-webhook" target="_blank" rel="noopener"><?php esc_html_e( 'Wolt docs', 'oc-wolt-drive' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the registration status block: ID, dot, and primary action button.
	 */
	protected static function render_webhook_registration_block() {
		$webhook_id  = OCWS_Wolt_Settings::get_webhook_id();
		$has_secret  = '' !== OCWS_Wolt_Settings::get_webhook_secret();
		$has_creds   = OCWS_Wolt_Api::is_configured() && '' !== OCWS_Wolt_Settings::get_merchant_id();
		$is_registered = '' !== $webhook_id;
		?>
		<div class="ocws-wolt-webhook-reg">
			<div class="ocws-wolt-webhook-reg-status">
				<span class="ocws-wolt-status-item <?php echo $is_registered ? 'is-ok' : 'is-warn'; ?>">
					<span class="dot"></span>
					<strong>
						<?php echo $is_registered
							? esc_html__( 'Registered', 'oc-wolt-drive' )
							: esc_html__( 'Not registered', 'oc-wolt-drive' ); ?>
					</strong>
				</span>
				<?php if ( $is_registered ) : ?>
					<span class="ocws-wolt-webhook-id"><?php esc_html_e( 'Wolt webhook ID:', 'oc-wolt-drive' ); ?> <code><?php echo esc_html( $webhook_id ); ?></code></span>
				<?php endif; ?>
			</div>

			<div class="ocws-wolt-webhook-reg-actions">
				<?php if ( $is_registered ) : ?>
					<button type="button" class="button" id="ocws-wolt-register-webhook"><?php esc_html_e( 'Re-register', 'oc-wolt-drive' ); ?></button>
					<button type="button" class="button button-link-delete" id="ocws-wolt-unregister-webhook"><?php esc_html_e( 'Unregister', 'oc-wolt-drive' ); ?></button>
				<?php else : ?>
					<button type="button" class="button button-primary" id="ocws-wolt-register-webhook" <?php disabled( ! $has_secret || ! $has_creds ); ?>>
						<?php esc_html_e( 'Register webhook with Wolt', 'oc-wolt-drive' ); ?>
					</button>
				<?php endif; ?>
				<span id="ocws-wolt-webhook-msg" class="ocws-wolt-inline-msg"></span>
			</div>

			<?php if ( ! $has_secret || ! $has_creds ) : ?>
				<p class="description ocws-wolt-error">
					<?php esc_html_e( 'Fill in API URL, API Key, Merchant ID, and a webhook secret before registering.', 'oc-wolt-drive' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ─── Tab: Tools ──────────────────────────────────────────────── */

	/**
	 * Render tools tab: quote simulator + scheduled time preview.
	 */
	protected static function render_tools_tab() {
		$sim_default_date = function_exists( 'wp_date' ) ? wp_date( 'd/m/Y' ) : gmdate( 'd/m/Y' );
		?>
		<div class="ocws-wolt-card ocws-wolt-tools-card">
			<h2><?php esc_html_e( 'Quote simulator', 'oc-wolt-drive' ); ?></h2>
			<p class="ocws-wolt-tools-lead"><?php esc_html_e( 'See the live shipment quote from Wolt for any address. Optionally add a checkout-style time window to preview the scheduled customer dropoff time the plugin would send (slot start + your dispatch offset from Settings).', 'oc-wolt-drive' ); ?></p>

			<form id="ocws-wolt-sim-form" class="ocws-wolt-form ocws-wolt-tools-form">
				<div class="ocws-wolt-tools-section">
					<h3 class="ocws-wolt-tools-section-title"><?php esc_html_e( '1. Destination', 'oc-wolt-drive' ); ?></h3>
					<p class="ocws-wolt-tools-section-desc description"><?php esc_html_e( 'Use a full address, coordinates, or both. Coordinates help when the address is vague or hard to geocode.', 'oc-wolt-drive' ); ?></p>
					<div class="ocws-wolt-tools-field ocws-wolt-tools-field-full">
						<label for="sim_address" class="ocws-wolt-tools-label"><?php esc_html_e( 'Address', 'oc-wolt-drive' ); ?></label>
						<input id="sim_address" type="text" name="address" class="regular-text" value="<?php echo esc_attr( __( 'Dizengoff St 50, Tel Aviv-Yafo', 'oc-wolt-drive' ) ); ?>" placeholder="<?php esc_attr_e( 'Street, City', 'oc-wolt-drive' ); ?>" autocomplete="street-address" />
					</div>
					<div class="ocws-wolt-tools-row">
						<div class="ocws-wolt-tools-field">
							<label for="sim_lat" class="ocws-wolt-tools-label"><?php esc_html_e( 'Latitude (optional)', 'oc-wolt-drive' ); ?></label>
							<input id="sim_lat" type="text" name="lat" inputmode="decimal" value="32.0853" placeholder="<?php esc_attr_e( 'e.g. 32.0853', 'oc-wolt-drive' ); ?>" class="regular-text" />
						</div>
						<div class="ocws-wolt-tools-field">
							<label for="sim_lng" class="ocws-wolt-tools-label"><?php esc_html_e( 'Longitude (optional)', 'oc-wolt-drive' ); ?></label>
							<input id="sim_lng" type="text" name="lng" inputmode="decimal" value="34.7818" placeholder="<?php esc_attr_e( 'e.g. 34.7818', 'oc-wolt-drive' ); ?>" class="regular-text" />
						</div>
					</div>
				</div>

				<div class="ocws-wolt-tools-section">
					<h3 class="ocws-wolt-tools-section-title"><?php esc_html_e( '2. Delivery window (optional)', 'oc-wolt-drive' ); ?></h3>
					<p class="ocws-wolt-tools-section-desc description"><?php esc_html_e( 'Same formats as at checkout. Leave empty to only preview pricing; the scheduled dropoff preview appears when both date and start time are valid.', 'oc-wolt-drive' ); ?></p>
					<div class="ocws-wolt-tools-row">
						<div class="ocws-wolt-tools-field">
							<label for="sim_slot_date" class="ocws-wolt-tools-label"><?php esc_html_e( 'Date', 'oc-wolt-drive' ); ?></label>
							<input id="sim_slot_date" type="text" name="slot_date" value="<?php echo esc_attr( $sim_default_date ); ?>" placeholder="<?php esc_attr_e( 'e.g. 25/02/2026 (day/month/year)', 'oc-wolt-drive' ); ?>" class="regular-text" inputmode="numeric" autocomplete="off" />
						</div>
						<div class="ocws-wolt-tools-field">
							<label for="sim_slot_start" class="ocws-wolt-tools-label"><?php esc_html_e( 'Window start', 'oc-wolt-drive' ); ?></label>
							<input id="sim_slot_start" type="text" name="slot_start" value="16:00" placeholder="<?php esc_attr_e( 'e.g. 16:00 (24-hour)', 'oc-wolt-drive' ); ?>" class="regular-text" inputmode="numeric" autocomplete="off" />
						</div>
					</div>
				</div>

				<div class="ocws-wolt-tools-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Run simulation', 'oc-wolt-drive' ); ?></button>
				</div>
			</form>

			<div id="ocws-wolt-sim-result" class="ocws-wolt-result" role="region" aria-live="polite" aria-label="<?php esc_attr_e( 'Simulation results', 'oc-wolt-drive' ); ?>"></div>
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

		$html  = '<div class="ocws-wolt-sim-out">';
		$html .= '<div class="ocws-wolt-sim-panel">';
		$html .= '<h3>' . esc_html__( 'Shipment promise', 'oc-wolt-drive' ) . '</h3>';
		if ( ! empty( $price_result['success'] ) ) {
			$with_markup = OCWS_Wolt_Settings::apply_markup( (float) $price_result['cost'] );
			$html .= '<dl class="ocws-wolt-sim-dl">';
			$html .= '<dt>' . esc_html__( 'Wolt quote', 'oc-wolt-drive' ) . '</dt><dd>' . esc_html( number_format_i18n( $price_result['cost'], 2 ) ) . '</dd>';
			$html .= '<dt>' . esc_html__( 'With your markup', 'oc-wolt-drive' ) . '</dt><dd>' . esc_html( number_format_i18n( $with_markup, 2 ) ) . '</dd>';
			$html .= '</dl>';
		} else {
			$html .= '<p class="ocws-wolt-error">' . esc_html( isset( $price_result['error'] ) ? $price_result['error'] : __( 'Error', 'oc-wolt-drive' ) ) . '</p>';
		}
		$html .= '</div>';
		$html .= '<div class="ocws-wolt-sim-panel">';
		$html .= '<h3>' . esc_html__( 'Scheduled dropoff time', 'oc-wolt-drive' ) . '</h3>';
		if ( '' !== $scheduled ) {
			$html .= '<p class="ocws-wolt-sim-scheduled"><code>' . esc_html( $scheduled ) . '</code></p>';
			$html .= '<p class="description">' . esc_html__( 'Includes your dispatch offset from Settings.', 'oc-wolt-drive' ) . '</p>';
		} else {
			$html .= '<p class="description">' . esc_html__( 'Add a valid date and window start above, or a real order without a slot is sent as ASAP.', 'oc-wolt-drive' ) . '</p>';
		}
		$html .= '</div></div>';
		wp_send_json_success( array( 'html' => $html ) );
	}

	/* ─── Tab: Deliveries ─────────────────────────────────────────── */

	const DELIVERIES_PER_PAGE = 20;

	/**
	 * List recent orders that have a Wolt delivery attached.
	 *
	 * @param int $paged Current page (1-based).
	 * @return array{ orders: WC_Order[], total: int, total_pages: int, paged: int }
	 */
	protected static function query_deliveries( $paged = 1 ) {
		$paged = max( 1, (int) $paged );
		$args  = array(
			'limit'        => self::DELIVERIES_PER_PAGE,
			'paged'        => $paged,
			'orderby'      => 'date',
			'order'        => 'DESC',
			'meta_key'     => OCWS_Wolt_Delivery_Trigger::META_DELIVERY_ID,
			'meta_compare' => 'EXISTS',
			'paginate'     => true,
		);
		$result = wc_get_orders( $args );
		return array(
			'orders'      => isset( $result->orders )      ? $result->orders      : array(),
			'total'       => isset( $result->total )       ? (int) $result->total : 0,
			'total_pages' => isset( $result->max_num_pages ) ? (int) $result->max_num_pages : 1,
			'paged'       => $paged,
		);
	}

	/**
	 * Map a Wolt status string to a CSS pill class.
	 *
	 * @param string $status Status string from Wolt.
	 * @return string
	 */
	protected static function pill_class_for_status( $status ) {
		$s = strtoupper( (string) $status );
		if ( '' === $s )                                          { return 'is-neutral'; }
		if ( in_array( $s, array( 'DELIVERED', 'COMPLETED' ), true ) )                 { return 'is-success'; }
		if ( in_array( $s, array( 'CANCELLED', 'REJECTED', 'FAILED' ), true ) )        { return 'is-bad'; }
		if ( in_array( $s, array( 'INFO_RECEIVED', 'CREATED', 'RECEIVED' ), true ) )   { return 'is-info'; }
		return 'is-progress';
	}

	/**
	 * Render the Deliveries tab: paginated table of every Wolt delivery on the site.
	 */
	protected static function render_deliveries_tab() {
		$paged = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query = self::query_deliveries( $paged );
		?>
		<div class="ocws-wolt-card ocws-wolt-deliveries-card">
			<div class="ocws-wolt-deliveries-header">
				<h2><?php esc_html_e( 'Wolt deliveries', 'oc-wolt-drive' ); ?></h2>
				<span class="ocws-wolt-deliveries-count">
					<?php
					printf(
						/* translators: %d: total deliveries */
						esc_html( _n( '%d delivery', '%d deliveries', $query['total'], 'oc-wolt-drive' ) ),
						(int) $query['total']
					);
					?>
				</span>
			</div>

			<?php if ( empty( $query['orders'] ) ) : ?>
				<div class="ocws-wolt-empty">
					<p><?php esc_html_e( 'No Wolt deliveries dispatched yet.', 'oc-wolt-drive' ); ?></p>
					<p class="description"><?php esc_html_e( 'When orders move to the auto-dispatch status (or you click "Create Wolt delivery now" on an order), they will appear here.', 'oc-wolt-drive' ); ?></p>
				</div>
			<?php else : ?>
				<div class="ocws-wolt-table-wrap">
					<table class="ocws-wolt-deliveries">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Order', 'oc-wolt-drive' ); ?></th>
								<th><?php esc_html_e( 'Customer', 'oc-wolt-drive' ); ?></th>
								<th><?php esc_html_e( 'Dropoff address', 'oc-wolt-drive' ); ?></th>
								<th><?php esc_html_e( 'Status', 'oc-wolt-drive' ); ?></th>
								<th><?php esc_html_e( 'ETA', 'oc-wolt-drive' ); ?></th>
								<th><?php esc_html_e( 'Cost', 'oc-wolt-drive' ); ?></th>
								<th><?php esc_html_e( 'Created', 'oc-wolt-drive' ); ?></th>
								<th class="ocws-wolt-actions-th"><?php esc_html_e( 'Actions', 'oc-wolt-drive' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $query['orders'] as $order ) : self::render_delivery_row( $order ); endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php self::render_deliveries_pagination( $query ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a single row in the deliveries table.
	 *
	 * @param WC_Order $order Order.
	 */
	protected static function render_delivery_row( $order ) {
		$order_id     = $order->get_id();
		$delivery_id  = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_DELIVERY_ID );
		$wolt_ref     = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_WOLT_ORDER_REF );
		$wolt_status  = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_WOLT_STATUS );
		$tracking     = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_TRACKING_URL );
		$last_error   = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_LAST_ERROR );

		$customer_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		if ( '' === $customer_name ) {
			$customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		}
		$phone = $order->get_meta( '_shipping_phone' );
		if ( ! $phone ) { $phone = $order->get_billing_phone(); }

		$display_status = $wolt_status ?: ( $last_error ? 'FAILED' : 'CREATED' );
		$pill_class     = self::pill_class_for_status( $display_status );
		$can_cancel     = $wolt_ref && ! in_array( strtoupper( (string) $wolt_status ), array( 'DELIVERED', 'CANCELLED', 'FAILED' ), true );
		?>
		<tr class="ocws-wolt-row" data-order-id="<?php echo esc_attr( $order_id ); ?>">
			<td class="ocws-wolt-col-order">
				<a href="<?php echo esc_url( ocws_wolt_order_edit_url( $order_id ) ); ?>" class="ocws-wolt-order-link">#<?php echo esc_html( $order->get_order_number() ); ?></a>
				<div class="ocws-wolt-meta">
					<?php if ( $delivery_id ) : ?>
						<span class="ocws-wolt-delivery-id" title="Wolt delivery id"><code><?php echo esc_html( substr( $delivery_id, 0, 12 ) ); ?>…</code></span>
					<?php endif; ?>
				</div>
			</td>
			<td class="ocws-wolt-col-customer">
				<div class="ocws-wolt-customer-name"><?php echo esc_html( $customer_name ?: '—' ); ?></div>
				<?php if ( $phone ) : ?>
					<a href="tel:<?php echo esc_attr( $phone ); ?>" class="ocws-wolt-phone"><?php echo esc_html( $phone ); ?></a>
				<?php endif; ?>
			</td>
			<td class="ocws-wolt-col-address">
				<?php echo esc_html( $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address() ); ?>
			</td>
			<td class="ocws-wolt-col-status">
				<span class="ocws-wolt-pill <?php echo esc_attr( $pill_class ); ?>"><?php echo esc_html( $display_status ); ?></span>
				<?php if ( $last_error ) : ?>
					<details class="ocws-wolt-error-details">
						<summary><?php esc_html_e( 'View error', 'oc-wolt-drive' ); ?></summary>
						<code><?php echo esc_html( $last_error ); ?></code>
					</details>
				<?php endif; ?>
			</td>
			<td class="ocws-wolt-col-eta">
				<?php
				$dropoff_eta_display = OCWS_Wolt_Delivery_Trigger::get_dropoff_eta_display( $order );
				echo esc_html( $dropoff_eta_display ?: '—' );
				?>
			</td>
			<td class="ocws-wolt-col-cost">
				<?php
				$cost_amount   = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_COST_AMOUNT );
				$cost_currency = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_COST_CURRENCY );
				if ( '' !== $cost_amount && null !== $cost_amount && function_exists( 'wc_price' ) ) {
					echo wp_kses_post( wc_price( $cost_amount, array( 'currency' => $cost_currency ) ) );
				} else {
					echo '—';
				}
				?>
			</td>
			<td class="ocws-wolt-col-date">
				<?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '—' ); ?>
			</td>
			<td class="ocws-wolt-col-actions">
				<?php if ( $tracking ) : ?>
					<a href="<?php echo esc_url( $tracking ); ?>" target="_blank" rel="noopener" class="button ocws-wolt-btn-track">
						<?php esc_html_e( 'Track', 'oc-wolt-drive' ); ?>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( ocws_wolt_order_edit_url( $order_id ) ); ?>" class="button">
					<?php esc_html_e( 'Order', 'oc-wolt-drive' ); ?>
				</a>
				<?php if ( $can_cancel ) : ?>
					<button type="button" class="button button-link-delete ocws-wolt-btn-cancel"
						data-order-id="<?php echo esc_attr( $order_id ); ?>"
						data-wolt-ref="<?php echo esc_attr( $wolt_ref ); ?>">
						<?php esc_html_e( 'Cancel', 'oc-wolt-drive' ); ?>
					</button>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render pagination links below the deliveries table.
	 *
	 * @param array $query Output of query_deliveries().
	 */
	protected static function render_deliveries_pagination( $query ) {
		if ( $query['total_pages'] <= 1 ) {
			return;
		}
		$base = add_query_arg(
			array( 'page' => self::MENU_SLUG, 'tab' => 'deliveries' ),
			admin_url( 'admin.php' )
		);
		$links = paginate_links( array(
			'base'      => $base . '%_%',
			'format'    => '&paged=%#%',
			'current'   => $query['paged'],
			'total'     => $query['total_pages'],
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'type'      => 'array',
		) );
		if ( empty( $links ) ) {
			return;
		}
		echo '<nav class="ocws-wolt-pagination"><ul>';
		foreach ( $links as $link ) {
			echo '<li>' . $link . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</ul></nav>';
	}

	/**
	 * AJAX: cancel a Wolt delivery (PATCH /order/{ref}/status/cancel).
	 */
	public static function ajax_cancel_delivery() {
		self::verify_ajax();

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$reason   = isset( $_POST['reason'] )   ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

		if ( $order_id <= 0 || '' === $reason ) {
			wp_send_json_error( array( 'message' => __( 'Order ID and reason required.', 'oc-wolt-drive' ) ) );
		}
		if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'oc-wolt-drive' ) ), 403 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'oc-wolt-drive' ) ) );
		}

		$wolt_ref = $order->get_meta( OCWS_Wolt_Delivery_Trigger::META_WOLT_ORDER_REF );
		if ( '' === $wolt_ref ) {
			wp_send_json_error( array( 'message' => __( 'Order is missing wolt_order_reference_id — cannot cancel via API.', 'oc-wolt-drive' ) ) );
		}

		$result = OCWS_Wolt_Api::cancel_delivery( $wolt_ref, $reason );
		if ( empty( $result['success'] ) ) {
			$err = isset( $result['error'] ) ? $result['error'] : __( 'Unknown error.', 'oc-wolt-drive' );
			$order->add_order_note( sprintf(
				/* translators: %s: error message returned by Wolt */
				__( 'Wolt: cancel failed — %s', 'oc-wolt-drive' ),
				$err
			) );
			wp_send_json_error( array( 'message' => $err ) );
		}

		$order->update_meta_data( OCWS_Wolt_Delivery_Trigger::META_WOLT_STATUS, 'CANCELLED' );
		$order->save();
		$order->add_order_note(
			sprintf(
				/* translators: %s: reason */
				__( 'Wolt: delivery cancelled (reason: %s).', 'oc-wolt-drive' ),
				$reason
			)
		);
		wp_send_json_success( array( 'message' => __( 'Cancelled.', 'oc-wolt-drive' ) ) );
	}

	/**
	 * AJAX: register a webhook at Wolt using the configured URL + secret.
	 * On success, save the returned webhook id locally.
	 */
	public static function ajax_register_webhook() {
		self::verify_ajax();

		$callback_url = rest_url( 'ocws-wolt/v1/webhook' );
		$secret       = OCWS_Wolt_Settings::get_webhook_secret();

		if ( '' === $secret ) {
			wp_send_json_error( array( 'message' => __( 'Generate a webhook secret first.', 'oc-wolt-drive' ) ) );
		}

		$result = OCWS_Wolt_Api::register_webhook( $callback_url, $secret );
		if ( empty( $result['success'] ) ) {
			$err = isset( $result['error'] ) ? $result['error'] : __( 'Unknown error.', 'oc-wolt-drive' );
			wp_send_json_error( array( 'message' => $err ) );
		}

		if ( ! empty( $result['id'] ) ) {
			OCWS_Wolt_Settings::set_webhook_id( $result['id'] );
		}
		wp_send_json_success(
			array(
				'id'      => isset( $result['id'] ) ? $result['id'] : '',
				'message' => __( 'Webhook registered.', 'oc-wolt-drive' ),
			)
		);
	}

	/**
	 * AJAX: delete the registered webhook at Wolt and clear the stored id.
	 */
	public static function ajax_unregister_webhook() {
		self::verify_ajax();

		$webhook_id = OCWS_Wolt_Settings::get_webhook_id();
		if ( '' === $webhook_id ) {
			wp_send_json_error( array( 'message' => __( 'No webhook is registered.', 'oc-wolt-drive' ) ) );
		}

		$result = OCWS_Wolt_Api::delete_webhook( $webhook_id );
		if ( empty( $result['success'] ) ) {
			$err = isset( $result['error'] ) ? $result['error'] : __( 'Unknown error.', 'oc-wolt-drive' );
			wp_send_json_error( array( 'message' => $err ) );
		}
		OCWS_Wolt_Settings::clear_webhook_id();
		wp_send_json_success( array( 'message' => __( 'Webhook unregistered.', 'oc-wolt-drive' ) ) );
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

endif;
