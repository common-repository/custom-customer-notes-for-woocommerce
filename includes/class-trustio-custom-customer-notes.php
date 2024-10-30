<?php
/**
 * Main plugin class file.
 *
 * @package WordPress Plugin Template/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class Trustio_Custom_Customer_Notes {

	/**
	 * The single instance of Trustio_Custom_Customer_Notes.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * Local instance of Trustio_Custom_Customer_Notes_Admin_API
	 *
	 * @var Trustio_Custom_Customer_Notes_Admin_API|null
	 */
	public $admin = null;

	/**
	 * Settings class object
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version; //phpcs:ignore

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token; //phpcs:ignore

	/**
	 * The main plugin file.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for JavaScripts.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * Constructor funtion.
	 *
	 * @param string $file File constructor.
	 * @param string $version Plugin version.
	 */
	public function __construct( $file = '', $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token   = 'Trustio_Custom_Customer_Notes';

		// Load plugin environment variables.
		$this->file       = $file;
		$this->dir        = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );

		// Load admin JS & CSS.
		add_action( 'tccn_admin_enqueue_scripts', array( $this, 'tccn_admin_enqueue_scripts' ), 10, 1 );
		add_action( 'tccn_admin_enqueue_styles', array( $this, 'tccn_admin_enqueue_styles' ), 10, 1 );

		// Load API for generic admin functions.
		if ( is_admin() ) {
			$this->admin = new Trustio_Custom_Customer_Notes_Admin_API();
		}

		// Handle localisation.
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'tccn_load_localisation' ), 0 );

		// Add new meta box to the woocommerce order admin page.
		add_action( 'add_meta_boxes', array( $this, 'tccn_op_register_menu_meta_box' ), 10, 0 );

		// When button is clicked, execture this function.
		add_action( 'admin_post_send_notes', array( $this, 'trustio_add_custom_customer_note' ), 10, 0 );

	} // End __construct ()


	/**
	 * Send customer note. Function is triggered as a post request
	 */
	public function trustio_add_custom_customer_note() {
		// Verify nonce so that we can trust the request.
		$retrieved_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : null;
		if ( ! wp_verify_nonce( $retrieved_nonce, 'trustio_nonce' ) ) {
			die( 'Failed security check' );
		}

		$note_type        = isset( $_POST['note_type'] ) ? sanitize_text_field( wp_unslash( $_POST['note_type'] ) ) : null;
		$customer_note_id = isset( $_POST['customer_note'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_note'] ) ) : null;
		$order_id         = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : null;
		$return_url       = isset( $_POST['return_url'] ) ? sanitize_text_field( wp_unslash( $_POST['return_url'] ) ) : '';

		$customer_note = get_option( $customer_note_id );

		// Check parameters, return if not set.
		if ( is_null( $order_id ) || is_null( $customer_note_id ) || '' === $customer_note ) {
			wp_safe_redirect( $return_url );
			exit();
		}

		// Get order.
		$order = new WC_Order( $order_id );

		// Add private/customer note.
		if ( 'private' === $note_type ) {
			$order->add_order_note( $customer_note, 0 );
		} elseif ( 'customer' === $note_type ) {
			$order->add_order_note( $customer_note, 1 );
		}

		wp_safe_redirect( $return_url );
		exit();
	}

	/**
	 * Adds a new meta box in the woocommerce single order page.
	 *
	 * @since 1.0.0
	 */
	public function tccn_op_register_menu_meta_box() {
		add_meta_box(
			'Custom Customer Notes',
			esc_html__( 'Custom Customer Notes', 'text-domain' ),
			'tccn_render_meta_box',
			'shop_order', // shop_order is the post type of the admin order page.
			'side', // change to 'side' to move box to side column.
			'low', // priority (where on page to put the box).
		);

		/**
		 * Adds a new form to the metabox which display all available custom customer messages.
		 */
		function tccn_render_meta_box() {
			// Get order id.
			global $post;
			$order_id = $post->ID;

			// Get number of available messages.
			$nmbr_of_customer_notes = get_option( 'trustio_customer_notes_nmbr_of_customer_notes' );
			if ( ! $nmbr_of_customer_notes ) {
				$nmbr_of_customer_notes = 1;
			}

			// Get return url.
			$http_host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$return_url  = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $http_host . $request_uri;

			echo '<form></form>';
			echo '<form action=' . esc_url( admin_url( 'admin-post.php' ) ) . " method='post'>";
			wp_nonce_field( 'trustio_nonce' );
			echo "<div class='custom_customer_note_holder'>";
			echo "<select name='customer_note'>";
			for ( $i = 1; $i <= $nmbr_of_customer_notes; $i++ ) {
				$cn = get_option( 'trustio_customer_notes_customer_note_' . esc_html( $i ) );
				if ( '' !== $cn && false !== $cn ) {
					echo "<option value='trustio_customer_notes_customer_note_" . esc_html( $i ) . "'>";
					echo esc_html( $cn );
					echo '</option>';
				}
			}
			echo '</select>';
			echo '</div custom_customer_note_holder>';
			echo "<select name='note_type'>";
			echo "<option value='customer'>" . esc_html( __( 'Customer Note' ) ) . '</option>';
			echo "<option value='private'>" . esc_html( __( 'Private Note' ) ) . '</option>';
			echo '</select>';
			echo "<input type='hidden' name='action' value='send_notes'>";
			echo "<input type='hidden' name='return_url' value='" . esc_html( $return_url ) . "'>";
			echo "<input type='hidden' name='order_id' value='" . esc_html( $order_id ) . "'>";
			echo "<input class='add_note button custom_customer_note_button' type='submit' value='" . esc_html( __( 'Add' ) ) . "'>";
			echo '</form>';
		}
	}

	/**
	 * Admin enqueue style.
	 *
	 * @param string $hook Hook parameter.
	 *
	 * @return void
	 */
	public function tccn_admin_enqueue_styles( $hook = '' ) {
		wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-admin' );
	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 *
	 * @access  public
	 *
	 * @param string $hook Hook parameter.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function tccn_admin_enqueue_scripts( $hook = '' ) {
		wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version, true );
		wp_enqueue_script( $this->_token . '-admin' );
	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function tccn_load_localisation() {
		load_plugin_textdomain( 'custom-customer-notes', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_plugin_textdomain() {
		$domain = 'custom-customer-notes';

		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	/**
	 * Main Trustio_Custom_Customer_Notes Instance
	 *
	 * Ensures only one instance of Trustio_Custom_Customer_Notes is loaded or can be loaded.
	 *
	 * @param string $file File instance.
	 * @param string $version Version parameter.
	 *
	 * @return Object Trustio_Custom_Customer_Notes instance
	 * @see Trustio_Custom_Customer_Notes()
	 * @since 1.0.0
	 * @static
	 */
	public static function tccn_instance( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}

		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cloning of Trustio_Custom_Customer_Notes is forbidden' ) ), esc_attr( $this->_version ) );

	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Unserializing instances of Trustio_Custom_Customer_Notes is forbidden' ) ), esc_attr( $this->_version ) );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function install() {
		$this->tccn_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	private function tccn_log_version_number() { //phpcs:ignore
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

}
