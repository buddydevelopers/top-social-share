<?php
/**
 *
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://buddydevelopers.com
 * @since             1.0.0
 * @package           TSS
 *
 * @wordpress-plugin
 * Plugin Name:     Top Social Share
 * Description:     This plugin is to create social share functionality.
 * Version:         1.0.1
 * Author:          BuddyDevelopers
 * Author URI:      http://buddydevelopers.com
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:     top-social-share
 * Domain Path:     /languages
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

// Define plugin constants.
if ( ! defined( 'BD_TSS_PLUGINS_URL' ) ) {
	define( 'BD_TSS_PLUGINS_URL', plugin_dir_url( __FILE__ ) );
}
// Define plugin path.
if ( ! defined( 'BD_TSS_PLUGINS_PATH' ) ) {
	define( 'BD_TSS_PLUGINS_PATH', plugin_dir_path( __FILE__ ) );
}
// Define plugin version.
if ( ! defined( 'BD_TSS_PLUGINS_VERSION' ) ) {
	define( 'BD_TSS_PLUGINS_VERSION', '1.0.1' );
}

if ( ! function_exists( 'bd_tss_plugin_activate' ) ) {
	/**
	 * Function to call required plugin files on activation.
	 */
	function bd_tss_plugin_activate() {
		// Settings load on every request so tss_options exists before the front end reads it.
		require_once BD_TSS_PLUGINS_PATH . 'includes/class-tss-settings.php';
		if ( ! is_admin() ) {
			require_once BD_TSS_PLUGINS_PATH . 'includes/class-tss-social-share.php';
		}
		// Load all common functions.
		require_once BD_TSS_PLUGINS_PATH . 'includes/functions.php';
	}
	add_action( 'plugins_loaded', 'bd_tss_plugin_activate' );
}

if ( ! function_exists( 'bd_tss_activate_plugin' ) ) {
	/**
	 * Seed default share settings when the plugin is activated.
	 *
	 * Activation runs after plugins_loaded, so the boot callback above has not
	 * included the settings class yet.
	 */
	function bd_tss_activate_plugin() {
		require_once BD_TSS_PLUGINS_PATH . 'includes/class-tss-settings.php';
	}
	register_activation_hook( __FILE__, 'bd_tss_activate_plugin' );
}
