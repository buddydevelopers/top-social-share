<?php 
/**
 * file to add plugin settings.
 * 
 * @since 1.0.0
 * @package TSS\Admin 
 */

namespace TSS\Admin;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Load Plugin Settings
 * 
 * @package    TSS
 * @subpackage TSS/Admin
 * @author     buddydevelopers <buddydevelopers@gmail.com> 
 * @since 2.0.0
 */
class Settings{

	/**
	 * Class constructor
	 */
	public function __construct() {
		# ...
	}

    /**
     * Load settings files.
     */
    public function load(){
        echo 'Inside admin settings load';
    }

}
