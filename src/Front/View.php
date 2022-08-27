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
        // add_action( 'wp_head', array( $this, 'tss_display_positions' ) );
        add_action( 'wp_footer', array( $this, 'tss_display_social_left' ) );
        add_filter( 'the_content', array( $this, 'add_tss_social_icons_placement' ) );
        add_filter( 'post_thumbnail_html', array( $this, 'add_tss_social_icons_inside_featured_image' ) );
    }

    /**
     * Build the Social Sharing Icons
     *
     * @return string
     */

    function build_the_tss_icons() {

        //Get all the options and settings to build the buttons

        $options = get_option( 'tss_options' );

        //Color option
        if ( $options['tss_field_button_colors'] === 'default' ) {
            $tss_color_option = '';
        } else {
            $tss_color_option = 'style="color: ' . $options['tss_field_icon_style_foreground_color'] . '; background-color: ' . $options['tss_field_icon_style_bg_color'] . ';"';
        }


        //Size
        if ( $options['tss_field_icon_size'] === 'medium' ) {
            $tss_size_option = 'fa-2x';
        } elseif ( $options['tss_field_icon_size'] === 'large' ) {
            $tss_size_option = 'fa-3x';
        } else {
            $tss_size_option = '';
        }
        $html = '<div class="tss-social-div"><ul>';
            global $post;
            $post_permalink = get_permalink( $post->ID );
            $post_title = $post->ID;
            $post_attachment_url = wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) );

            $fb_link = 'href="http://www.facebook.com/sharer.php?u=' . $post_permalink . '" target="_blank"';
            $tw_link = 'href="https://twitter.com/intent/tweet?url=' . $post_permalink . '" target="_blank"';
            $go_link = 'href="https://plus.google.com/share?url=' . $post_permalink . '" target="_blank"';
            $pi_link = 'data-pin-do="buttonPin" data-pin-count="above" href="https://www.pinterest.com/pin/create/button/?url=https%3A%2F%2Fakgoods.com&media=' . $post_attachment_url . '&description=Check%20this%20out!" target="_blank"';
            $li_link = 'href="https://www.linkedin.com/shareArticle?mini=true&url=' . $post_permalink . '&title=' . $post_title . '" target="_blank"';
            $wa_link = 'href="whatsapp://send?text=' . $post_permalink . '" data-action="share/whatsapp/share" target="_blank"';

            $tss_social_buttons = $this->tss_social_sharing_options();
            if ( array_key_exists( 'tss_social_icon_order_1', $options ) ) {
                // load reorderd options
                for( $tss_icon = 0; $tss_icon < 4; $tss_icon++ ){
                    if ( array_key_exists( 'tss_social_icon_order_'.$tss_icon, $options ) ) {
                        $tss_social_icon = $options[ 'tss_social_icon_order_'. $tss_icon ];
                        if( !empty( $tss_social_buttons[$tss_social_icon] ) ){
                            $share_btn_link = $this->tss_social_share_link( $tss_social_icon );
                            if ( array_key_exists( 'tss_field_share_buttons_'.$tss_social_icon, $options ) && !empty( $share_btn_link ) ) {
                                $img = !empty( $tss_social_buttons[$tss_social_icon]['img'] ) ? '<img src="'. esc_url( $tss_social_buttons[$tss_social_icon]['img'] ).'" width="24px" height="24px" style="background-color:'. esc_attr( $tss_social_buttons[$tss_social_icon]['bgcolor'] ) .'" />' : esc_attr( $tss_social_buttons[$tss_social_icon]['label'] );
                                $html .= '<li data-tss-icon-name="'. esc_attr( $tss_social_icon ) .'"><a '. $share_btn_link.' >' . $img .'</a></li>';
                            }
                        }
                    }                
                }
            } else{
                // load default options
                foreach( $tss_social_buttons as $button_slug => $tss_social_button ){
                    $share_btn_link = $this->tss_social_share_link( $button_slug );
                    if ( array_key_exists( 'tss_field_share_buttons_'.$button_slug, $options ) && !empty( $share_btn_link ) ) {
                        $img = !empty( $tss_social_button['img'] ) ? '<img src="'. esc_url( $tss_social_button['img'] ).'" width="24px" height="24px" style="background-color:'. esc_attr( $tss_social_button['bgcolor'] ) .'" />' : '';
                        $html .= '<li data-tss-icon-name="'. esc_attr( $button_slug ) .'"><a '. $share_btn_link.' >' . $img .'</a></li>';
                    }
                }
            } 

        $html .= '</ul></div>';
        return $html;
    }
    /**
     * Function to display social sharing options on left.
     */
    public function tss_display_social_left(){
        $options = get_option( 'tss_options' );
        $display_left = array_key_exists( 'tss_field_icon_placement_floating_left', $options );
        if ( $display_left ) {
            echo $this->build_the_tss_icons();
        }
    }

    public function add_tss_social_icons_placement( $content ){
        $options = get_option( 'tss_options' );
        $current_post_type = get_post_type( get_the_ID() );
        if( in_the_loop() && ( is_singular() || is_page() ) && array_key_exists( 'tss_field_post_types_' . $current_post_type, $options ) ) {
            
            $tss_options = get_option( 'tss_options' );

            if ( array_key_exists( 'tss_field_icon_placement_below_post_title', $tss_options ) ) {
                $tss_icons = $this->build_the_tss_icons();
                $content   = $tss_icons . $content;
            } 
            if( array_key_exists( 'tss_field_icon_placement_after_post_content', $tss_options ) ){
                $tss_icons = $this->build_the_tss_icons();
                $content   = $content . $tss_icons;
            }
        }
        return $content;
    }

    //Add social sharing to featured image
    function add_tss_social_icons_inside_featured_image( $html ) {
        $options = get_option( 'tss_options' );
        $current_post_type = get_post_type( get_the_ID() );
        if( ( is_singular() || is_page() )  && array_key_exists( 'tss_field_post_types_' . $current_post_type, $options ) ){
            if ( array_key_exists( 'tss_field_icon_placement_inside_feature_image', $options ) ) {
                $post_thumbnail = $html;
                $html = '<div class="tss-featured-image-overlay">';
                $html .= $post_thumbnail;
                $html .= $this->build_the_tss_icons();
                $html .= '</div>';
            }
        }
        return $html;
    }

    public function tss_social_share_link( $option ){
        
        global $post;
        $post_permalink = get_permalink( $post->ID );
        $post_title = $post->ID;
        $post_attachment_url = wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) );

        switch ( $option ) {

            case 'facebook':
                $link = 'href="http://www.facebook.com/sharer.php?u=' . $post_permalink . '" target="_blank"';
                break;
            case 'twitter':
                $link = 'href="https://twitter.com/intent/tweet?url=' . $post_permalink . '" target="_blank"';
                break;
            case 'pinterest':
                $link = 'data-pin-do="buttonPin" data-pin-count="above" href="https://www.pinterest.com/pin/create/button/?url=https%3A%2F%2Fakgoods.com&media=' . $post_attachment_url . '&description=Check%20this%20out!" target="_blank"';
                break;
            case 'linkedIn':
                $link = 'href="https://www.linkedin.com/shareArticle?mini=true&url=' . $post_permalink . '&title=' . $post_title . '" target="_blank"';
                break;
            case 'whatsapp':
                if ( wp_is_mobile() ) {
                    $link = 'href="whatsapp://send?text=' . $post_permalink . '" data-action="share/whatsapp/share" target="_blank"';'href="whatsapp://send?text=' . $post_permalink . '" data-action="share/whatsapp/share" target="_blank"';
                } //end wp_is_mobile
        } // end switch
        return $link;
    }

    public function tss_social_sharing_options(){

        $tss_social_buttons = array( 
            'facebook' => array(
                'label' => 'Facebook',
                'img' => TSS_PLUGINS_URL.'src/icons/facebook.svg',
                'bgcolor' => '#1877F2'
            ),
            'twitter' => array(
                'label' => 'Twitter',
                'img' => TSS_PLUGINS_URL.'src/icons/twitter.svg',
                'bgcolor' => '#1D9BF0'
            ),
            'pinterest' => array(
                'label' => 'Pinterest',
                'img' => TSS_PLUGINS_URL.'src/icons/pinterest.svg',
                'bgcolor' => '#BD081C'
            ),
            'linkedIn' => array(
                'label' => 'LinkedIn',
                'img' => TSS_PLUGINS_URL.'src/icons/linkedIn.svg',
                'bgcolor' => '#007BB5'
            ),
            'whatsapp' => array(
                'label' => 'whatsapp',
                'img' => TSS_PLUGINS_URL.'src/icons/whatsapp.svg',
                'bgcolor' => '#12AF0A'
            ),
        );

        /**
         * Allow to add more social share options and update existing options.
         * 
         * @since 1.0.0
         * @param array $tss_social_buttons array of all social buttons
         */
        $tss_social_buttons = apply_filters_ref_array( 'tss_social_buttons', array( &$tss_social_buttons ));
        return $tss_social_buttons;

    }

}
