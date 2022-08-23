<?php 

/**
 * file to add plugin View.
 * 
 * @since 1.0.0
 * @package TSS\Admin 
 */

namespace TSS\Front;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Load Plugin View
 * 
 * @package    TSS
 * @subpackage TSS/Admin
 * @author     buddydevelopers <buddydevelopers@gmail.com> 
 * @since 2.0.0
 */
class View{

    /**
     * Class constructor
     */
    public function __construct() {
        # ...
    }

    /**
     * Load view files.
     */
    public function load(){
        echo 'Inside front view load';
    }

}
