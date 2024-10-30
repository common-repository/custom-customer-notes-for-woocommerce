<?php
/**
 * Settings class file.
 *
 * @package WordPress Plugin Template/Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 */
class Trustio_Custom_Customer_Notes_Settings {

	/**
	 * The single instance of Trustio_Custom_Customer_Notes_Settings.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * The main plugin object.
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 *
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = array();

	/**
	 * Constructor function.
	 *
	 * @param object $parent Parent object.
	 */
	public function __construct( $parent ) {
		$this->parent = $parent;

		$this->base = 'trustio_customer_notes_';

		// Initialise settings.
		add_action( 'init', array( $this, 'tccn_init_settings' ), 11 );

		// Register plugin settings.
		add_action( 'admin_init', array( $this, 'tccn_register_settings' ) );

		// Add settings page to menu.
		add_action( 'admin_menu', array( $this, 'tccn_add_menu_item' ) );

		// Add settings link to plugins page.
		add_filter(
			'plugin_action_links_' . plugin_basename( $this->parent->file ),
			array(
				$this,
				'tccn_add_settings_link',
			)
		);

		// Configure placement of plugin settings page. See readme for implementation.
		add_filter( $this->base . 'tccn_menu_settings', array( $this, 'tccn_configure_settings' ) );
	}

	/**
	 * Initialise settings
	 *
	 * @return void
	 */
	public function tccn_init_settings() {
		$this->settings = $this->tccn_settings_fields();
	}

	/**
	 * Add settings page to admin menu
	 *
	 * @return void
	 */
	public function tccn_add_menu_item() {

		$args = $this->tccn_menu_settings();

		// Do nothing if wrong location key is set.
		if ( is_array( $args ) && isset( $args['location'] ) && function_exists( 'add_' . $args['location'] . '_page' ) ) {
			switch ( $args['location'] ) {
				case 'options':
				case 'submenu':
					$page = add_submenu_page( $args['parent_slug'], $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function'] );
					break;
				case 'menu':
					$page = add_menu_page( $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function'], $args['icon_url'], $args['position'] );
					break;
				default:
					return;
			}
		}
	}

	/**
	 * Prepare default settings page arguments
	 *
	 * @return mixed|void
	 */
	private function tccn_menu_settings() {
		return apply_filters(
			$this->base . 'tccn_menu_settings',
			array(
				'location'    => 'menu', // Possible settings: options, menu, submenu.
				'parent_slug' => 'options-general.php',
				'page_title'  => __( 'Customer Notes', 'custom-customer-notes' ),
				'menu_title'  => __( 'Customer Notes', 'custom-customer-notes' ),
				'capability'  => 'manage_options',
				'menu_slug'   => $this->parent->_token . '_settings',
				'function'    => array( $this, 'tccn_settings_page' ),
				'icon_url'    => '',
				'position'    => null,
			)
		);
	}

	/**
	 * Container for settings page arguments
	 *
	 * @param array $settings Settings array.
	 *
	 * @return array
	 */
	public function tccn_configure_settings( $settings = array() ) {
		return $settings;
	}


	/**
	 * Add settings link to plugin list table
	 *
	 * @param  array $links Existing links.
	 * @return array        Modified links.
	 */
	public function tccn_add_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=' . $this->parent->_token . '_settings">' . __( 'Settings', 'custom-customer-notes' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}

	/**
	 * Build settings fields
	 *
	 * @return array Fields to be displayed on settings page
	 */
	private function tccn_settings_fields() {
		$template_message = array(
			'id'          => 'customer_note_',
			'label'       => __( 'Customer Note ', 'custom-customer-notes' ),
			'description' => '',
			'type'        => 'textarea',
			'default'     => '',
			'placeholder' => __( 'Placeholder text for this textarea', 'custom-customer-notes' ),
		);

		$fields_arr = array(
			array(
				'id'          => 'nmbr_of_customer_notes',
				'label'       => __( 'Number of customer notes', 'custom-customer-notes' ),
				'description' => __( '(save to update)', 'custom-customer-notes' ),
				'type'        => 'number',
				'default'     => '',
				'placeholder' => __( '1', 'custom-customer-notes' ),
			),
		);

		$nmbr_of_customer_notes = get_option( 'trustio_customer_notes_nmbr_of_customer_notes' );
		if ( ! $nmbr_of_customer_notes ) {
			$nmbr_of_customer_notes = 1;
		}
		for ( $i = 1; $i <= $nmbr_of_customer_notes; $i++ ) {
			$a          = $template_message;
			$a['id']    = $a['id'] . $i;
			$a['label'] = $a['label'] . $i;
			array_push( $fields_arr, $a );
		}

		$settings['standard'] = array(
			'title'       => __( 'Settings', 'custom-customer-notes' ),
			'description' => __( 'Add your custom notes here!', 'custom-customer-notes' ),
			'fields'      => $fields_arr,
		);

		$settings = apply_filters( $this->parent->_token . 'tccn_settings_fields', $settings );

		return $settings;
	}

	/**
	 * Register plugin settings
	 *
	 * @return void
	 */
	public function tccn_register_settings() {
		if ( is_array( $this->settings ) ) {

			// Check posted/selected tab.
			//phpcs:disable
			$current_section = '';
			if ( isset( $_POST['tab'] ) && $_POST['tab'] ) {
				$current_section = sanitize_text_field($_POST['tab']);
				// Validate that tab field is one of the possible values
				if( !array_key_exists($current_section, $this->settings) ) {
					return;
				}
			} else {
				if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
					$current_section = sanitize_text_field($_GET['tab']);
					// Validate that tab field is one of the possible values
					if( !array_key_exists($current_section, $this->settings) ) {
						return;
					}
				}
			}
			//phpcs:enable

			foreach ( $this->settings as $section => $data ) {

				if ( $current_section && $current_section !== $section ) {
					continue;
				}

				// Add section to page.
				add_settings_section( $section, $data['title'], array( $this, 'tccn_settings_section' ), $this->parent->_token . '_settings' );

				foreach ( $data['fields'] as $field ) {

					// Validation callback for field.
					$validation = '';
					if ( isset( $field['callback'] ) ) {
						$validation = $field['callback'];
					}

					// Register field.
					$option_name = $this->base . $field['id'];
					register_setting( $this->parent->_token . '_settings', $option_name, $validation );

					// Add field to page.
					add_settings_field(
						$field['id'],
						$field['label'],
						array( $this->parent->admin, 'tccn_display_field' ),
						$this->parent->_token . '_settings',
						$section,
						array(
							'field'  => $field,
							'prefix' => $this->base,
						)
					);
				}

				if ( ! $current_section ) {
					break;
				}
			}
		}
	}

	/**
	 * Settings section.
	 *
	 * @param array $section Array of section ids.
	 * @return void
	 */
	public function tccn_settings_section( $section ) {
		echo '<p> ' . esc_html( $this->settings[ $section['id'] ]['description'] ) . '</p>' . "\n";
	}

	/**
	 * Load settings page content.
	 *
	 * @return void
	 */
	public function tccn_settings_page() {

		// Print page HTML.
		echo '<div class="wrap" id="' . esc_attr( $this->parent->_token ) . '_settings">' . "\n";
		echo '<h2>' . esc_html( __( 'Custom Customer Notes', 'custom-customer-notes' ) ) . '</h2>' . "\n";

		$tab = '';

		//phpcs:disable
		if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
			$tab .= sanitize_text_field($_GET['tab']);
			// Validate that tab field is one of the possible values
			if( !array_key_exists($tab, $this->settings) ) {
				return;
			}
		}
		//phpcs:enable

		// Show page tabs.
		if ( is_array( $this->settings ) && 1 < count( $this->settings ) ) {

			echo '<h2 class="nav-tab-wrapper">' . "\n";

			$c = 0;
			foreach ( $this->settings as $section => $data ) {

				// Set tab class.
				$class = 'nav-tab';
				if ( ! isset( $_GET['tab'] ) ) { //phpcs:ignore
					if ( 0 === $c ) {
						$class .= ' nav-tab-active';
					}
				} else {
					if ( isset( $_GET['tab'] ) && $section == $_GET['tab'] ) { //phpcs:ignore
						$class .= ' nav-tab-active';
					}
				}

				// Set tab link.
				$tab_link = add_query_arg( array( 'tab' => $section ) );
				if ( isset( $_GET['settings-updated'] ) ) { //phpcs:ignore
					$tab_link = remove_query_arg( 'settings-updated', $tab_link );
				}

				// Output tab.
				echo '<a href="' . esc_attr( $tab_link ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $data['title'] ) . '</a>' . "\n";

				++$c;
			}

			echo '</h2>' . "\n";
		}

			echo '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";

			// Get settings fields.
			ob_start();
			settings_fields( $this->parent->_token . '_settings' );
			do_settings_sections( $this->parent->_token . '_settings' );
			echo ob_get_clean(); //phpcs:ignore

			echo '<p class="submit">' . "\n";
			echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />' . "\n";
			echo '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings', 'custom-customer-notes' ) ) . '" />' . "\n";
			echo '</p>' . "\n";
			echo '</form>' . "\n";
			echo '</div>' . "\n";
	}

	/**
	 * Main Trustio_Custom_Customer_Notes_Settings Instance
	 *
	 * Ensures only one instance of Trustio_Custom_Customer_Notes_Settings is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see Trustio_Custom_Customer_Notes()
	 * @param object $parent Object instance.
	 * @return object Trustio_Custom_Customer_Notes_Settings instance
	 */
	public static function tccn_instance( $parent ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}
		return self::$_instance;
	} // End instance()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cloning of Custom_Customer_Notes_API is forbidden.' ) ), esc_attr( $this->parent->_version ) );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Unserializing instances of Custom_Customer_Notes_API is forbidden.' ) ), esc_attr( $this->parent->_version ) );
	} // End __wakeup()

}
