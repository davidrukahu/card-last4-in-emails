<?php
/**
 * Plugin Name: Card Last4 in Emails
 * Plugin URI: https://github.com/davidrukahu/card-last4-in-emails
 * Description: Adds the last four digits of payment cards to WooCommerce email notifications for Order Details and Customer Notes. Only displays when a card was actually used for payment.
 * Version: 1.0.0
 * Author: davidrukahu
 * Author URI: https://github.com/davidrukahu
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: card-last4-in-emails
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package CardLast4InEmails
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'CL4E_VERSION', '1.0.0' );
define( 'CL4E_PLUGIN_FILE', __FILE__ );
define( 'CL4E_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CL4E_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class for Card Last4 in Emails.
 *
 * @since 1.0.0
 */
class Card_Last4_In_Emails {

	/**
	 * Plugin instance.
	 *
	 * @var Card_Last4_In_Emails
	 */
	private static $instance = null;

	/**
	 * Get plugin instance.
	 *
	 * @return Card_Last4_In_Emails
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Check if WooCommerce is active.
		add_action( 'plugins_loaded', array( $this, 'check_woocommerce' ) );

		// Register settings early for options.php whitelist and admin UI.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Ensure admin submenu and plugin action links are available.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
		add_filter( 'plugin_action_links_' . plugin_basename( CL4E_PLUGIN_FILE ), array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Check if WooCommerce is active and initialize the plugin.
	 *
	 * @since 1.0.0
	 */
	public function check_woocommerce() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Initialize the plugin.
		$this->init();
	}

	/**
	 * Initialize the plugin functionality.
	 *
	 * @since 1.0.0
	 */
	private function init() {
		// Add hooks for email content.
		add_action( 'woocommerce_email_order_details', array( $this, 'add_card_info_to_order_details' ), 15, 4 );
		add_action( 'woocommerce_email_customer_details', array( $this, 'add_card_info_to_customer_details' ), 25, 3 );

		// Add hooks for plain text emails.
		add_action( 'woocommerce_email_order_details', array( $this, 'add_card_info_to_plain_order_details' ), 15, 4 );
		add_action( 'woocommerce_email_customer_details', array( $this, 'add_card_info_to_plain_customer_details' ), 25, 3 );

		// Load text domain for internationalization.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'card-last4-in-emails',
			false,
			dirname( plugin_basename( CL4E_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Add card information to HTML email order details.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order         Order instance.
	 * @param bool     $sent_to_admin If sent to admin.
	 * @param bool     $plain_text    If is plain text email.
	 * @param WC_Email $email         Email instance.
	 */
	public function add_card_info_to_order_details( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $plain_text ) {
			return;
		}

		// Respect settings: only output for enabled email IDs.
		if ( ! $this->is_email_enabled( $email ) ) {
			return;
		}

		$card_info = $this->get_order_card_info( $order );
		if ( empty( $card_info ) ) {
			return;
		}

		// Add card information after order details.
		$this->output_card_info_html( $card_info );
	}

	/**
	 * Add card information to HTML email customer details.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order         Order instance.
	 * @param bool     $sent_to_admin If sent to admin.
	 * @param bool     $plain_text    If is plain text email.
	 */
	public function add_card_info_to_customer_details( $order, $sent_to_admin, $plain_text ) {
		if ( $plain_text ) {
			return;
		}

		$card_info = $this->get_order_card_info( $order );
		if ( empty( $card_info ) ) {
			return;
		}

		// Add card information to customer details section.
		$this->output_card_info_html( $card_info );
	}

	/**
	 * Add card information to plain text email order details.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order         Order instance.
	 * @param bool     $sent_to_admin If sent to admin.
	 * @param bool     $plain_text    If is plain text email.
	 * @param WC_Email $email         Email instance.
	 */
	public function add_card_info_to_plain_order_details( $order, $sent_to_admin, $plain_text, $email ) {
		if ( ! $plain_text ) {
			return;
		}

		// Respect settings: only output for enabled email IDs.
		if ( ! $this->is_email_enabled( $email ) ) {
			return;
		}

		$card_info = $this->get_order_card_info( $order );
		if ( empty( $card_info ) ) {
			return;
		}

		// Add card information for plain text emails.
		$this->output_card_info_plain( $card_info );
	}

	/**
	 * Add card information to plain text email customer details.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order         Order instance.
	 * @param bool     $sent_to_admin If sent to admin.
	 * @param bool     $plain_text    If is plain text email.
	 */
	public function add_card_info_to_plain_customer_details( $order, $sent_to_admin, $plain_text ) {
		if ( ! $plain_text ) {
			return;
		}

		$card_info = $this->get_order_card_info( $order );
		if ( empty( $card_info ) ) {
			return;
		}

		// Add card information for plain text emails.
		$this->output_card_info_plain( $card_info );
	}

	/**
	 * Get card information for an order.
	 *
	 * @since 1.0.0
	 * @param WC_Order $order Order instance.
	 * @return array|false Card information array or false if no card info available.
	 */
	private function get_order_card_info( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		// Check if order has a total (was paid).
		if ( $order->get_total() <= 0 ) {
			return false;
		}

		// Get payment method.
		$payment_method = $order->get_payment_method();
		if ( empty( $payment_method ) || 'other' === $payment_method ) {
			return false;
		}

		// Get card information using WooCommerce's built-in method.
		$card_info = $order->get_payment_card_info();

		// Only return if we have last4 digits.
		if ( ! isset( $card_info['last4'] ) || empty( $card_info['last4'] ) ) {
			return false;
		}

		return $card_info;
	}

	/**
	 * Output card information in HTML format.
	 *
	 * @since 1.0.0
	 * @param array $card_info Card information array.
	 */
	private function output_card_info_html( $card_info ) {
		$brand = ! empty( $card_info['brand'] ) ? $card_info['brand'] : '';
		$last4 = $card_info['last4'];

		// Format the brand name for display.
		if ( ! empty( $brand ) ) {
			$brand = ucwords( str_replace( '_', ' ', $brand ) );
		}

		// Build the display text.
		if ( ! empty( $brand ) ) {
			/* translators: %1$s: Card brand, %2$s: Last 4 digits */
			$display_text = sprintf(
				esc_html__( '%1$s ending in %2$s', 'card-last4-in-emails' ),
				esc_html( $brand ),
				esc_html( $last4 )
			);
		} else {
			/* translators: %s: Last 4 digits */
			$display_text = sprintf(
				esc_html__( 'Card ending in %s', 'card-last4-in-emails' ),
				esc_html( $last4 )
			);
		}

		// Output the card information.
		echo '<div class="card-info-section" style="margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007cba;">';
		echo '<h3 style="margin: 0 0 10px 0; color: #23282d; font-size: 16px;">' . esc_html__( 'Payment Information', 'card-last4-in-emails' ) . '</h3>';
		echo '<p style="margin: 0; color: #666; font-size: 14px;">' . $display_text . '</p>';
		echo '</div>';
	}

	/**
	 * Output card information in plain text format.
	 *
	 * @since 1.0.0
	 * @param array $card_info Card information array.
	 */
	private function output_card_info_plain( $card_info ) {
		$brand = ! empty( $card_info['brand'] ) ? $card_info['brand'] : '';
		$last4 = $card_info['last4'];

		// Format the brand name for display.
		if ( ! empty( $brand ) ) {
			$brand = ucwords( str_replace( '_', ' ', $brand ) );
		}

		// Build the display text.
		if ( ! empty( $brand ) ) {
			/* translators: %1$s: Card brand, %2$s: Last 4 digits */
			$display_text = sprintf(
				__( '%1$s ending in %2$s', 'card-last4-in-emails' ),
				$brand,
				$last4
			);
		} else {
			/* translators: %s: Last 4 digits */
			$display_text = sprintf(
				__( 'Card ending in %s', 'card-last4-in-emails' ),
				$last4
			);
		}

		// Output the card information in plain text.
		echo "\n" . __( 'Payment Information', 'card-last4-in-emails' ) . "\n";
		echo str_repeat( '-', strlen( __( 'Payment Information', 'card-last4-in-emails' ) ) ) . "\n";
		echo $display_text . "\n\n";
	}

	/**
	 * Display admin notice when WooCommerce is not active.
	 *
	 * @since 1.0.0
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s: WooCommerce plugin name */
					esc_html__( '%s requires WooCommerce to be installed and active.', 'card-last4-in-emails' ),
					'<strong>' . esc_html__( 'Card Last4 in Emails', 'card-last4-in-emails' ) . '</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Plugin activation hook.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( CL4E_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'This plugin requires WooCommerce to be installed and active.', 'card-last4-in-emails' ),
				esc_html__( 'Plugin Activation Error', 'card-last4-in-emails' ),
				array(
					'response' => 200,
					'back_link' => true,
				)
			);
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Add admin menu under WooCommerce.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		// Add under WooCommerce menu when available.
		if ( class_exists( 'WooCommerce' ) ) {
			add_submenu_page(
				'woocommerce',
				__( 'Card Last4 in Emails', 'card-last4-in-emails' ),
				__( 'Card Last4', 'card-last4-in-emails' ),
				'manage_woocommerce',
				'card-last4-settings',
				array( $this, 'render_settings_page' )
			);
		}
	}

	/**
	 * Render plugin settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		$enabled = $this->get_enabled_emails_option();
		$email_labels = array(
			'failed_order' => __( 'Failed order (admin)', 'card-last4-in-emails' ),
			'customer_on_hold_order' => __( 'Order on-hold', 'card-last4-in-emails' ),
			'customer_processing_order' => __( 'Processing order', 'card-last4-in-emails' ),
			'customer_completed_order' => __( 'Completed order', 'card-last4-in-emails' ),
			'customer_refunded_order' => __( 'Refunded order', 'card-last4-in-emails' ),
			'customer_note' => __( 'Customer note', 'card-last4-in-emails' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Card Last4 in Emails', 'card-last4-in-emails' ); ?></h1>
			<p><?php esc_html_e( 'Select which WooCommerce emails should include card details when available.', 'card-last4-in-emails' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'cl4e_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enabled emails', 'card-last4-in-emails' ); ?></th>
							<td>
								<fieldset>
									<?php foreach ( $email_labels as $id => $label ) : ?>
										<label>
											<input name="cl4e_enabled_emails[<?php echo esc_attr( $id ); ?>]" type="checkbox" value="1" <?php checked( ! empty( $enabled[ $id ] ) ); ?> />
											<?php echo esc_html( $label ); ?>
										</label><br />
									<?php endforeach; ?>
								</fieldset>
								<p class="description"><?php esc_html_e( 'These control which email notifications will include the card brand and last 4 digits when a card payment is detected.', 'card-last4-in-emails' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add Settings link on the Plugins page.
	 *
	 * @since 1.0.0
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=card-last4-settings' );
		$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'card-last4-in-emails' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Register plugin settings (placeholder for future options).
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		register_setting(
			'cl4e_settings_group',
			'cl4e_enabled_emails',
			array(
				'type' => 'array',
				'sanitize_callback' => array( $this, 'sanitize_enabled_emails' ),
				'default' => $this->get_default_enabled_emails(),
			)
		);
	}

	/**
	 * Default enabled email types.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_default_enabled_emails() {
		return array(
			'failed_order' => true,
			'customer_on_hold_order' => true,
			'customer_processing_order' => true,
			'customer_completed_order' => true,
			'customer_refunded_order' => true,
			'customer_note' => true,
		);
	}

	/**
	 * Sanitize checkbox inputs.
	 *
	 * @since 1.0.0
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_enabled_emails( $input ) {
		$defaults = $this->get_default_enabled_emails();
		$sanitized = array();
		foreach ( $defaults as $key => $default ) {
			$sanitized[ $key ] = isset( $input[ $key ] ) ? true : false;
		}
		return $sanitized;
	}

	/**
	 * Get enabled email types option.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_enabled_emails_option() {
		$option = get_option( 'cl4e_enabled_emails', $this->get_default_enabled_emails() );
		if ( ! is_array( $option ) ) {
			$option = $this->get_default_enabled_emails();
		}
		return wp_parse_args( $option, $this->get_default_enabled_emails() );
	}

	/**
	 * Check if output is enabled for the current email.
	 *
	 * @since 1.0.0
	 * @param WC_Email $email Email instance.
	 * @return bool
	 */
	private function is_email_enabled( $email ) {
		$enabled = $this->get_enabled_emails_option();
		$email_id = ( is_object( $email ) && isset( $email->id ) ) ? $email->id : '';
		if ( empty( $email_id ) ) {
			return true; // If we cannot detect, do not block.
		}
		return isset( $enabled[ $email_id ] ) ? (bool) $enabled[ $email_id ] : true;
	}
}

// Initialize the plugin.
add_action( 'plugins_loaded', array( 'Card_Last4_In_Emails', 'get_instance' ) );

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'Card_Last4_In_Emails', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Card_Last4_In_Emails', 'deactivate' ) );
