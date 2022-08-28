<?php
/**
 * File called when Top Social Share plugin is uninstalled.
 *
 * @package TSS
 */

// if uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

$option_name = 'tss_options';

// Delete the pluin setting option data.
delete_option( $option_name );
