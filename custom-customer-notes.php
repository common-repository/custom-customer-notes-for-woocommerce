<?php
/**
 * Plugin Name: Custom Customer Notes for WooCommerce
 * Version: 1.2.7
 * Description: This is a lightweight plugin for WooCommerce that makes it possible to instant add customer or private notes with predefined messages.
 * Author: Trustio
 * Author URI: https://trustiosolutions.se/
 * Requires at least: 6.0.0
 * Tested up to: 6.6.0
 *
 * Text Domain: custom-customer-notes
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Trustio
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load plugin class files.
require_once 'includes/class-trustio-custom-customer-notes.php';
require_once 'includes/class-trustio-custom-customer-notes-settings.php';

// Load plugin libraries.
require_once 'includes/lib/class-trustio-custom-customer-notes-admin-api.php';

/**
 * Returns the main instance of Trustio_Custom_Customer_Notes to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object Trustio_Custom_Customer_Notes
 */
function trustio_custom_customer_notes() {
	$instance = Trustio_Custom_Customer_Notes::tccn_instance( __FILE__, '1.2.7' );

	if ( tccn_check_if_woocommerce_is_installed() ) {
		// WooCommerce is active, however it has not.
		if ( is_null( $instance->settings ) ) {
			$instance->settings = Trustio_Custom_Customer_Notes_Settings::tccn_instance( $instance );
		}
	}

	return $instance;
}

/**
 * Checks if woocommerce is installed
 *
 * @return bool
 * @since   1.0.0
 */
function tccn_check_if_woocommerce_is_installed() {
	$active_plugins = (array) get_option( 'active_plugins', array() );

	if ( is_multisite() ) {
		$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
	}
	return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
}



trustio_custom_customer_notes();
