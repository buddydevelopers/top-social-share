<?php 
/**
 * This file is the functionality loader.
 * It will include plugin setting file and call the proper files based on the setting.
 *
 * @since 2.0.0
 * @package TSS
 */

namespace TSS;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

use TSS\Admin\Settings;
use TSS\Front\View;

/**
 * Load TSS functionality 
 * 
 * @package    TSS
 * @subpackage TSS/Loader
 * @author     buddydevelopers <buddydevelopers@gmail.com> 
 * @since 2.0.0
 */
final class Loader {

	/**
	 * Class constructor
	 */
	public function __construct() {
		# ...
	}
	
	/**
	 * Load all the required files.
	 */
	public static function load(){
		if( is_admin() ){
			// Load Setting Class.
			$instance = new Settings();
			$instance->load();
	
		} else{
			// Load public class.
			$instance = new View();
			$instance->load();
		}
	}
}
