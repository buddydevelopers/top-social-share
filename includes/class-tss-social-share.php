<?php
/**
 * File to social bar in frontend at different places.
 *
 * @since 1.0.0
 * @package TSS\Admin
 */

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
class TSS_Social_Share {

	/**
	 * Plugin settings data array.
	 *
	 * @access  private
	 *
	 * @var Array  $tss_option settings array
	 */
	private $tss_option;


	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->load();
	}

	/**
	 * Load view files.
	 */
	public function load() {
		// get the setting data array.
		$this->tss_option = get_option( 'tss_options' );
		add_action( 'wp_footer', array( $this, 'tss_display_social_left' ) );
		add_filter( 'the_content', array( $this, 'add_tss_social_icons_placement' ) );
		add_filter( 'post_thumbnail_html', array( $this, 'add_tss_social_icons_inside_featured_image' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'tss_enqueue_script' ) );
		// Add shortcode here.
		add_shortcode( 'top-share', array( $this, 'tss_share_shortcode' ) );
	}

	/**
	 *  Add share bar style.
	 */
	public function tss_enqueue_script() {
		global $post;
		wp_enqueue_style( 'tss-style-handle', TSS_PLUGINS_URL . 'assets/css/style.css', array(), TSS_PLUGINS_VERSION );
		// background color.
		$additional_icon_color_style = '';
		if ( ! empty( $this->tss_option['tss_field_icon_style'] ) && 'custom' === $this->tss_option['tss_field_icon_style'] ) {
			$additional_icon_color_style .= '.tss-social-div svg{  background-color: ' . $this->tss_option['tss_field_icon_style_bg_color'] . ' !important; }';
		}
		// Foreground color.
		$fore_color = '';
		if ( ! empty( $this->tss_option['tss_field_icon_style_foreground'] ) && 'custom' === $this->tss_option['tss_field_icon_style_foreground'] ) {
			$fore_color                   = $this->tss_option['tss_field_icon_style_foreground_color'];
			$additional_icon_color_style .= '.tss-social-div svg path{  fill:' . $this->tss_option['tss_field_icon_style_foreground_color'] . ' !important; }';
		}
		if ( ! empty( $post->ID ) ) {
			wp_add_inline_style( 'tss-style-handle', $additional_icon_color_style );
		}
	}

	/**
	 * Build the Social Sharing Icons
	 *
	 * @return string Social sharing icons html
	 */
	public function build_the_tss_icons() {

		// Size.
		if ( 'medium' === $this->tss_option['tss_field_icon_size'] ) {
			$tss_size_option = 'tss-size-2x';
		} elseif ( 'large' === $this->tss_option['tss_field_icon_size'] ) {
			$tss_size_option = 'tss-size-3x';
		} else {
			$tss_size_option = '';
		}
		global $post;
		$html = '';
		if ( ! empty( $post->ID ) ) {
			$html                       = '<div class="tss-social-div"><ul>';
			$post_permalink             = get_permalink( $post->ID );
			$post_title                 = $post->ID;
			$post_attachment_url        = wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) );
			$tss_social_buttons         = tss_social_sharing_options();
			$social_share_options_count = count( $tss_social_buttons );
			if ( array_key_exists( 'tss_social_icon_order_1', $this->tss_option ) ) {
				// load reorderd options.
				for ( $tss_icon = 0; $tss_icon < $social_share_options_count; $tss_icon++ ) {
					if ( array_key_exists( 'tss_social_icon_order_' . $tss_icon, $this->tss_option ) ) {
						$tss_social_icon = $this->tss_option[ 'tss_social_icon_order_' . $tss_icon ];
						if ( ! empty( $tss_social_buttons[ $tss_social_icon ] ) ) {
							$share_btn_link = $this->tss_social_share_link( $tss_social_icon, $post->ID );
							if ( array_key_exists( 'tss_field_share_buttons_' . $tss_social_icon, $this->tss_option ) && ! empty( $share_btn_link ) ) {
								$img   = ! empty( $tss_social_buttons[ $tss_social_icon ]['img'] ) ? $tss_social_buttons[ $tss_social_icon ]['img'] : esc_attr( $tss_social_buttons[ $tss_social_icon ]['label'] );
								$html .= '<li data-tss-icon-name="' . esc_attr( $tss_social_icon ) . '" class="' . $tss_size_option . '"><a ' . $share_btn_link . ' title="' . esc_attr( $tss_social_buttons[ $tss_social_icon ]['label'] ) . '">' . $img . '</a></li>';
							}
						}
					}
				}
			} else {
				// load default options.
				foreach ( $tss_social_buttons as $button_slug => $tss_social_button ) {
					$share_btn_link = $this->tss_social_share_link( $button_slug, $post->ID );
					if ( array_key_exists( 'tss_field_share_buttons_' . $button_slug, $this->tss_option ) && ! empty( $share_btn_link ) ) {
						$img   = ! empty( $tss_social_buttons[ $button_slug ]['img'] ) ? $tss_social_buttons[ $button_slug ]['img'] : esc_attr( $tss_social_buttons[ $button_slug ]['label'] );
						$html .= '<li data-tss-icon-name="' . esc_attr( $button_slug ) . '" class="' . $tss_size_option . '"><a ' . $share_btn_link . ' title="' . esc_attr( $tss_social_buttons[ $button_slug ]['label'] ) . '">' . $img . '</a></li>';
					}
				}
			}
			$html .= '</ul></div>';
		}
		return $html;
	}

	/**
	 * Function to display social sharing options on left.
	 */
	public function tss_display_social_left() {
		$current_post_type = get_post_type( get_the_ID() );
		if ( ( is_singular() || is_page() ) && array_key_exists( 'tss_field_post_types_' . $current_post_type, $this->tss_option ) ) {
			if ( array_key_exists( 'tss_field_icon_placement_floating_left', $this->tss_option ) ) {
				$tss_icons = $this->build_the_tss_icons();
				echo '<div class="tss-share-floating-bar">' . $tss_icons . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ignoring escaping for $tss_icons
			}
		}
	}

	/**
	 * Add social sharing icons above and below the content based on condition.
	 *
	 * @param String $content Post content with social sharing icons.
	 */
	public function add_tss_social_icons_placement( $content ) {
		$current_post_type = get_post_type( get_the_ID() );
		if ( in_the_loop() && ( is_singular() || is_page() ) && array_key_exists( 'tss_field_post_types_' . $current_post_type, $this->tss_option ) ) {

			if ( array_key_exists( 'tss_field_icon_placement_below_post_title', $this->tss_option ) ) {
				$tss_icons = $this->build_the_tss_icons();
				$content   = $tss_icons . $content;
			}
			if ( array_key_exists( 'tss_field_icon_placement_after_post_content', $this->tss_option ) ) {
				$tss_icons = $this->build_the_tss_icons();
				$content   = $content . $tss_icons;
			}
		}
		return $content;
	}


	/**
	 * Add social sharing below featured image.
	 *
	 * @param String $html featured image with social sharing icons.
	 */
	public function add_tss_social_icons_inside_featured_image( $html ) {
		$current_post_type = get_post_type( get_the_ID() );
		// only add when featured image exist.
		if ( ( is_singular() || is_page() ) && array_key_exists( 'tss_field_post_types_' . $current_post_type, $this->tss_option ) && has_post_thumbnail() ) {
			if ( array_key_exists( 'tss_field_icon_placement_inside_feature_image', $this->tss_option ) ) {
				$post_thumbnail = $html;
				$html           = '<div class="tss-featured-image-overlay">';
				$html          .= $post_thumbnail;
				$html          .= $this->build_the_tss_icons();
				$html          .= '</div>';
			}
		}
		return $html;
	}

	/**
	 * Function return link href attribute based on social share option.
	 *
	 * @param String $option social share option name.
	 * @param int    $post_id current post id.
	 */
	public function tss_social_share_link( $option, $post_id ) {
		if ( empty( $post_id ) ) {
			global $post;
			$post_id = $post->ID;
		}
		$post_permalink      = get_permalink( $post_id );
		$post_title          = get_the_title( $post_id );
		$post_attachment_url = wp_get_attachment_url( get_post_thumbnail_id( $post_id ) );
		$link                = ''; // if link is empty, the social icon will not appear ( specially for whatsapp icon).
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
					$link = 'href="https://api.whatsapp.com/send?text=' . $post_permalink . '" data-action="share/whatsapp/share" target="_blank"';
				} //end wp_is_mobile
		} // end switch
		return $link;
	}

	/**
	 * Add shortcode to add share bar.
	 *
	 * @return string $html social share bar
	 */
	public function tss_share_shortcode() {
		$html      = '<div class="tss-share-shortcode-output">';
			$html .= $this->build_the_tss_icons();
		$html     .= '</div>';

		return $html;
	}
}
$instance = new TSS_Social_Share();
