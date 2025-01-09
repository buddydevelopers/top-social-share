<?php
/**
 * File to add plugin settings.
 *
 * @since 1.0.0
 * @package TSS\Admin
 */

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
class TSS_Settings {

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
	 * Load settings files.
	 */
	public function load() {
		// get the setting data array.
		$this->tss_option = get_option( 'tss_options' );
		/**
		 * Register bd_tss_options_page to the admin_menu action hook.
		 */
		add_action( 'admin_menu', array( $this, 'bd_tss_options_page' ) );
		/**
		 * Register our bd_tss_settings_init to the admin_init action hook.
		 */
		add_action( 'admin_init', array( $this, 'bd_tss_settings_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'bd_tss_enqueue_admin_script' ) );
	}

	/**
	 * Enqueue the wp color picket and the custom script required for the plugin setting.
	 */
	public function bd_tss_enqueue_admin_script() {
		// Css rules for Color Picker.
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'tss-script-handle', BD_TSS_PLUGINS_URL . 'assets/js/scripts.js', array( 'wp-color-picker', 'jquery-ui-sortable' ), BD_TSS_PLUGINS_VERSION, true );
		wp_enqueue_style( 'tss-admin-style-handle', BD_TSS_PLUGINS_URL . 'assets/css/admin-style.css', array(), BD_TSS_PLUGINS_VERSION );
	}

	/**
	 * Create the top level menu
	 */
	public function bd_tss_options_page() {
		// add top level menu page.
		add_menu_page(
			__( 'Top Social Sharing Plugin', 'top-social-share' ),
			__( 'Social Sharing', 'top-social-share' ),
			'manage_options',
			'tss',
			array( $this, 'bd_tss_options_page_html_cb' ),
			'dashicons-share'
		);
	} //end bd_tss_options_page.

	/**
	 * Top level menu callback function
	 */
	public function bd_tss_options_page_html_cb() {
		// check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$saved_setting = filter_input( INPUT_GET, 'settings-updated', FILTER_SANITIZE_STRING ) ? filter_input( INPUT_GET, 'settings-updated', FILTER_SANITIZE_STRING ) : '';
		// check if the user have submitted the settings.
		// WordPress will add the "settings-updated" $_GET parameter to the url.
		if ( isset( $saved_setting ) && 'true' === $saved_setting ) {
			// Add settings saved message with the class of "updated".
			add_settings_error( 'tss_messages', 'tss_message', __( 'Settings Saved', 'top-social-share' ), 'updated' );
		}

		// show error/update messages.
		settings_errors( 'tss_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" id="tss_admin_form" method="post">
				<?php
				// output security fields for the registered setting "tss".
				settings_fields( 'tss' );
				// output setting sections and their fields.
				// (sections are registered for "tss", each field is registered to a specific section).
				do_settings_sections( 'tss' );
				// output save settings button.
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Provides default values for the Input Options.
	 */
	public function bd_tss_default_options() {

		$defaults = array(
			'tss_field_post_types_post'              => '1',
			'tss_field_post_types_page'              => '1',
			'tss_field_share_buttons_facebook'       => '1',
			'tss_field_share_buttons_twitter'        => '1',
			'tss_field_share_buttons_linkedIn'       => '1',
			'tss_field_share_buttons_pinterest'      => '1',
			'tss_field_share_buttons_whatsapp'       => '1',
			'tss_field_icon_size'                    => 'small',
			'tss_field_icon_style_bg_color'          => '#000000',
			'tss_field_icon_style'                   => 'original',
			'tss_field_icon_style_foreground'        => 'original',
			'tss_field_icon_style_foreground_color'  => '#8224e3',
			'tss_field_icon_placement_floating_left' => '1',
		);

		return apply_filters( 'bd_tss_default_options', $defaults );
	} // end bd_tss_default_options.

	/**
	 * Initialize custom option and settings
	 */
	public function bd_tss_settings_init() {
		if ( false === $this->tss_option ) {
			add_option( 'tss_options', apply_filters( 'bd_tss_default_options', $this->bd_tss_default_options() ) );
		} // end if.

		// register a new setting for "tss" page.
		register_setting( 'tss', 'tss_options', array( $this, 'bd_tss_sanitize_options' ) );

		// register a new section in the "tss" page.
		add_settings_section(
			'tss_section',
			__( 'Settings', 'top-social-share' ),
			array( $this, 'bd_tss_intro_section_cb' ),
			'tss'
		);

		add_settings_field(
			'tss_field_post_types',
			__( 'Post Types', 'top-social-share' ),
			array( $this, 'bd_tss_field_post_types_cb' ),
			'tss',
			'tss_section',
			array(
				'label_for'       => 'tss_field_post_types_',
				'class'           => 'tss_row',
				'tss_custom_data' => 'tss-plugin-post-types',
			)
		);

		add_settings_field(
			'tss_field_share_buttons',
			__( 'Share Buttons', 'top-social-share' ),
			array( $this, 'bd_tss_field_share_buttons_cb' ),
			'tss',
			'tss_section',
			array(
				'label_for'       => 'tss_field_share_buttons',
				'class'           => 'tss_row',
				'tss_custom_data' => 'tss-plugin-share-buttons',
			)
		);

		add_settings_field(
			'tss_field_icon_size',
			__( 'Sharing Icons Size', 'top-social-share' ),
			array( $this, 'bd_tss_field_icon_size_cb' ),
			'tss',
			'tss_section',
			array(
				'label_for'       => 'tss_field_icon_size',
				'class'           => 'tss_row',
				'tss_custom_data' => 'tss-plugin-icon-size',
			)
		);

		add_settings_field(
			'tss_field_icon_style',
			__( 'Sharing Icons Style', 'top-social-share' ),
			array( $this, 'bd_tss_field_icon_style_cb' ),
			'tss',
			'tss_section',
			array(
				'label_for'       => 'tss_field_icon_style',
				'class'           => 'tss_row',
				'tss_custom_data' => 'tss-plugin-icon-style',
			)
		);

		add_settings_field(
			'tss_field_icon_placement',
			__( 'Sharing Icons Placement', 'top-social-share' ),
			array( $this, 'bd_tss_field_icon_placement_cb' ),
			'tss',
			'tss_section',
			array(
				'label_for'       => 'tss_field_icon_placement',
				'class'           => 'tss_row',
				'tss_custom_data' => 'tss-plugin-icon-placement',
			)
		);
	} //end bd_tss_settings_init.

	/**
	 * Sanitize the `tss_options` array input.
	 *
	 * This function processes and sanitizes all settings under `tss_options` to ensure
	 * safe and valid data is saved in the options table.
	 *
	 * @param array $input The raw input from the settings form.
	 * @return array $sanitized_input The sanitized data to be saved in the options table.
	 */
	public function bd_tss_sanitize_options( $input ) {
		$sanitized_input = array();

		// Sanitize post types selection (checkboxes).
		$public_post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $public_post_types as $post_type ) {
			if ( isset( $input[ 'tss_field_post_types_' . $post_type->name ] ) ) {
				$sanitized_input[ 'tss_field_post_types_' . $post_type->name ] = isset( $input[ 'tss_field_post_types_' . $post_type->name ] ) ? '1' : '0';
			}
		}

		// Sanitize share buttons selection (checkboxes).
		$sanitized_input['tss_field_share_buttons_facebook']  = isset( $input['tss_field_share_buttons_facebook'] ) ? '1' : '0';
		$sanitized_input['tss_field_share_buttons_twitter']   = isset( $input['tss_field_share_buttons_twitter'] ) ? '1' : '0';
		$sanitized_input['tss_field_share_buttons_pinterest'] = isset( $input['tss_field_share_buttons_pinterest'] ) ? '1' : '0';
		$sanitized_input['tss_field_share_buttons_linkedIn']  = isset( $input['tss_field_share_buttons_linkedIn'] ) ? '1' : '0';
		$sanitized_input['tss_field_share_buttons_whatsapp']  = isset( $input['tss_field_share_buttons_whatsapp'] ) ? '1' : '0';

		// Sanitize icon size (select input).
		$allowed_icon_sizes = array( 'small', 'medium', 'large' );
		if ( isset( $input['tss_field_icon_size'] ) && in_array( $input['tss_field_icon_size'], $allowed_icon_sizes, true ) ) {
			$sanitized_input['tss_field_icon_size'] = sanitize_text_field( $input['tss_field_icon_size'] );
		} else {
			$sanitized_input['tss_field_icon_size'] = 'small'; // Default value if invalid.
		}

		// Sanitize icon style (select input).
		$allowed_icon_styles = array( 'original', 'custom' );
		if ( isset( $input['tss_field_icon_style'] ) && in_array( $input['tss_field_icon_style'], $allowed_icon_styles, true ) ) {
			$sanitized_input['tss_field_icon_style'] = sanitize_text_field( $input['tss_field_icon_style'] );
		} else {
			$sanitized_input['tss_field_icon_style'] = 'original'; // Default value if invalid.
		}

		// Sanitize background color for icon style (hex color).
		if ( isset( $input['tss_field_icon_style_bg_color'] ) ) {
			$sanitized_input['tss_field_icon_style_bg_color'] = sanitize_hex_color( $input['tss_field_icon_style_bg_color'] );
		}

		// Sanitize foreground style (select input).
		$allowed_foregrounds = array( 'original', 'custom' );
		if ( isset( $input['tss_field_icon_style_foreground'] ) && in_array( $input['tss_field_icon_style_foreground'], $allowed_foregrounds, true ) ) {
			$sanitized_input['tss_field_icon_style_foreground'] = sanitize_text_field( $input['tss_field_icon_style_foreground'] );
		} else {
			$sanitized_input['tss_field_icon_style_foreground'] = 'original'; // Default value if invalid.
		}

		// Sanitize foreground color (hex color).
		if ( isset( $input['tss_field_icon_style_foreground_color'] ) ) {
			$sanitized_input['tss_field_icon_style_foreground_color'] = sanitize_hex_color( $input['tss_field_icon_style_foreground_color'] );
		}

		// Sanitize icon placement (checkbox).
		if ( isset( $input['tss_field_icon_placement_floating_left'] ) ) {
			$sanitized_input['tss_field_icon_placement_floating_left'] = sanitize_text_field( $input['tss_field_icon_placement_floating_left'] );
		}
		if ( isset( $input['tss_field_icon_placement_below_post_title'] ) ) {
			$sanitized_input['tss_field_icon_placement_below_post_title'] = sanitize_text_field( $input['tss_field_icon_placement_below_post_title'] );
		}
		if ( isset( $input['tss_field_icon_placement_inside_feature_image'] ) ) {
			$sanitized_input['tss_field_icon_placement_inside_feature_image'] = sanitize_text_field( $input['tss_field_icon_placement_inside_feature_image'] );
		}
		if ( isset( $input['tss_field_icon_placement_after_post_content'] ) ) {
			$sanitized_input['tss_field_icon_placement_after_post_content'] = sanitize_text_field( $input['tss_field_icon_placement_after_post_content'] );
		}

		return $sanitized_input;
	}

	/**
	 * Intro section for shortcode
	 *
	 * @param Array $args setting field data.
	 */
	public function bd_tss_intro_section_cb( $args ) {
		?>
		<p id="<?php echo esc_attr( $args['id'] ); ?>"><?php echo wp_kses_data( 'Top Social share plugin also provide a shortcode to embed the social sharing buttons in your website content, you can use the <code>[top-share]</code>, and the butttons will appear.', 'top-social-share' ); ?></p>
		<?php
	}

	/**
	 * Post Types Checkboxes
	 *
	 * @param Array $args setting field data.
	 */
	public function bd_tss_field_post_types_cb( $args ) {
		// Get the value of the setting we've registered with register_setting().
		$public_post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $public_post_types as $post_type ) {

			if ( array_key_exists( 'tss_field_post_types_' . $post_type->name, $this->tss_option ) ) {
				$is_checked = $this->tss_option[ 'tss_field_post_types_' . $post_type->name ];
			} else {
				$is_checked = '';
			}

			if ( 'attachment' !== $post_type->name ) {
				?>
				<input type="checkbox" id="<?php echo esc_attr( $args['label_for'] . $post_type->name ); ?>" name="tss_options[<?php echo esc_attr( $args['label_for'] . $post_type->name ); ?>]" value="1" <?php checked( 1, $is_checked, true ); ?>/>
				<label for="<?php echo esc_attr( $args['label_for'] . $post_type->name ); ?>"><?php echo esc_html( ucfirst( $post_type->label ) ); ?></label>
				<?php
			}
		}
	} // end bd_tss_field_post_types_cb.

	/**
	 * Share buttons.
	 */
	public function bd_tss_field_share_buttons_cb() {

		echo '<p>' . esc_attr__( 'Choose the services you want below.  Click a chosen service again to remove.  Reorder services by dragging and dropping as they appear above.', 'top-social-share' ) . '</p>';
		?>
		<div class="wrap">
			<small><?php esc_html_e( 'Enable sharing icons and reorder them here. WhatsApp icon only appear on mobile.', 'top-social-share' ); ?></small>
			<ul class="sharing-icons-lists" id="tss-social-icon-sortable">
				<?php
				$tss_social_buttons = bd_tss_social_sharing_options();
				if ( array_key_exists( 'tss_social_icon_order_1', $this->tss_option ) ) {
					// load reorderd options.
					$social_share_options_count = count( $tss_social_buttons );
					for ( $tss_icon = 0; $tss_icon <= $social_share_options_count; $tss_icon++ ) {
						if ( array_key_exists( 'tss_social_icon_order_' . $tss_icon, $this->tss_option ) ) {
							$tss_social_icon = $this->tss_option[ 'tss_social_icon_order_' . $tss_icon ];
							if ( array_key_exists( 'tss_field_share_buttons_' . $tss_social_icon, $this->tss_option ) ) {
								$is_checked = $this->tss_option[ 'tss_field_share_buttons_' . $tss_social_icon ];
							} else {
								$is_checked = '';
							}

							if ( ! empty( $tss_social_buttons[ $tss_social_icon ] ) ) {
								$img = ! empty( $tss_social_buttons[ $tss_social_icon ]['img_url'] ) ? '<img src="' . esc_url( $tss_social_buttons[ $tss_social_icon ]['img_url'] ) . '" width="24px" height="24px" style="background-color:' . esc_attr( $tss_social_buttons[ $tss_social_icon ]['bgcolor'] ) . '" />' : '';
								echo '<li data-tss-icon-name="' . esc_attr( $tss_social_icon ) . '"><label><input type="checkbox" name="tss_options[tss_field_share_buttons_' . esc_attr( $tss_social_icon ) . ']" ' . checked( $is_checked, 1, false ) . ' value="1"> ' . wp_kses_post( $img ) . esc_attr( $tss_social_buttons[ $tss_social_icon ]['label'] ) . '</label></li>';
							}
						}
					}
				} else {
					// load default options.
					foreach ( $tss_social_buttons as $button_slug => $tss_social_button ) {
						if ( array_key_exists( 'tss_field_share_buttons_' . $button_slug, $this->tss_option ) ) {
							$is_checked = $this->tss_option[ 'tss_field_share_buttons_' . $button_slug ];
						} else {
							$is_checked = '';
						}

						$img = ! empty( $tss_social_button['img_url'] ) ? '<img src="' . esc_url( $tss_social_button['img_url'] ) . '" width="24px" height="24px" style="background-color:' . esc_attr( $tss_social_button['bgcolor'] ) . '" />' : '';
						echo '<li data-tss-icon-name="' . esc_attr( $button_slug ) . '"><label><input type="checkbox" name="tss_options[tss_field_share_buttons_' . esc_attr( $button_slug ) . ']"  ' . checked( $is_checked, 1, false ) . ' value="1"> ' . wp_kses_post( $img ) . esc_attr( $tss_social_button['label'] ) . '</label></li>';
					}
				}
				?>
			</ul>
			<?php
			$social_share_options_count = count( $tss_social_buttons );
			for ( $tss_icon = 0; $tss_icon <= $social_share_options_count; $tss_icon++ ) {
				if ( array_key_exists( 'tss_social_icon_order_' . $tss_icon, $this->tss_option ) ) {
					echo '<input class="tss_hidden_options" name="tss_options[tss_social_icon_order_' . esc_attr( $tss_icon ) . ']" type="hidden" value="' . esc_attr( $this->tss_option[ 'tss_social_icon_order_' . esc_attr( $tss_icon ) ] ) . '">';
				}
			}
			?>
		</div>
		<?php
	}

	/**
	 * Add icons size options html
	 *
	 * @param Array $args setting field data.
	 */
	public function bd_tss_field_icon_size_cb( $args ) {
		?>
		<div class="wrap">
			<small><?php esc_attr_e( 'Select size for sharing icons', 'top-social-share' ); ?></small>
			<?php

			if ( array_key_exists( 'tss_field_icon_size', $this->tss_option ) ) {
				$is_checked = esc_attr( $this->tss_option['tss_field_icon_size'] );
			} else {
				$is_checked = '';
			}
			?>
			<ul class="tss-icon-size">
				<li>
					<input type="radio" id="<?php echo esc_attr( $args['label_for'] . 'small' ); ?>"
						data-custom="<?php echo esc_attr( $args['tss_custom_data'] ); ?>"
						name="tss_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
						value="small" <?php checked( 'small', $is_checked, true ); ?>/>
					<label for="<?php echo esc_attr( $args['label_for'] . 'small' ); ?>"><?php echo esc_html__( 'Small', 'top-social-share' ); ?></label>
				</li>
				<li>
					<input type="radio" id="<?php echo esc_attr( $args['label_for'] . 'medium' ); ?>"
						data-custom="<?php echo esc_attr( $args['tss_custom_data'] ); ?>"
						name="tss_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
						value="medium" <?php checked( 'medium', $is_checked, true ); ?>/>
					<label for="<?php echo esc_attr( $args['label_for'] . 'medium' ); ?>"><?php echo esc_html__( 'Medium', 'top-social-share' ); ?></label>
				</li>
				<li>
					<input type="radio" id="<?php echo esc_attr( $args['label_for'] . 'large' ); ?>"
						data-custom="<?php echo esc_attr( $args['tss_custom_data'] ); ?>"
						name="tss_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
						value="large" <?php checked( 'large', $is_checked, true ); ?>/>
					<label for="<?php echo esc_attr( $args['label_for'] . 'large' ); ?>"><?php echo esc_html__( 'Large', 'top-social-share' ); ?></label>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Add options to change colors or social share icons globally.
	 *
	 * @param Array $args setting field data.
	 */
	public function bd_tss_field_icon_style_cb( $args ) {
		if ( array_key_exists( 'tss_field_icon_style', $this->tss_option ) ) {
			$tss_field_icon_style = esc_attr( $this->tss_option['tss_field_icon_style'] );
		} else {
			$tss_field_icon_style = '';
		}
		if ( array_key_exists( 'tss_field_icon_style_foreground', $this->tss_option ) ) {
			$tss_field_icon_foreground_style = esc_attr( $this->tss_option['tss_field_icon_style_foreground'] );
		} else {
			$tss_field_icon_foreground_style = '';
		}
		?>
		<div class="wrap tss-style-container">
			<small><?php esc_attr_e( 'Select custom color style for sharing icons', 'top-social-share' ); ?></small>
			<ul>
				<li>
					<label>
						<select class="tss_icon_color"  name="tss_options[<?php echo esc_attr( $args['label_for'] ); ?>]">
							<option value="original" <?php selected( $tss_field_icon_style, 'original' ); ?>>Original</option>
							<option value="custom" <?php selected( $tss_field_icon_style, 'custom' ); ?>>Custom&#8230;</option>
						</select>
						<?php esc_attr_e( 'Background', 'top-social-share' ); ?>
					</label>
					<div class="color-field-container"><input name="tss_options[<?php echo esc_attr( $args['label_for'] ) . '_bg_color'; ?>]" class="color-field" type="text" value="<?php echo ! empty( $this->tss_option['tss_field_icon_style_bg_color'] ) ? esc_attr( $this->tss_option['tss_field_icon_style_bg_color'] ) : '#2a2a2a'; ?>" data-default-color="#2a2a2a"></div>
				</li>
				<li>
					<label>
						<select class="tss_icon_color"  name="tss_options[<?php echo esc_attr( $args['label_for'] ) . '_foreground'; ?>]">
							<option value="original"  <?php selected( $tss_field_icon_foreground_style, 'original' ); ?>>Original</option>
							<option value="custom" <?php selected( $tss_field_icon_foreground_style, 'custom' ); ?>>Custom&#8230;</option>
						</select>
						<?php esc_attr_e( 'Foreground', 'top-social-share' ); ?>
					</label>
					<div class="color-field-container"><input name="tss_options[<?php echo esc_attr( $args['label_for'] ) . '_foreground_color'; ?>]" class="color-field" type="text" value="<?php echo ! empty( $this->tss_option['tss_field_icon_style_foreground_color'] ) ? esc_attr( $this->tss_option['tss_field_icon_style_foreground_color'] ) : '#ffffff'; ?>" data-default-color="#ffffff"></div>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Add options for social icons placements
	 *
	 * @param Array $args setting field data.
	 */
	public function bd_tss_field_icon_placement_cb( $args ) {
		?>
		<div class="wrap">
			<small><?php esc_attr_e( 'Select placement for the sharing icons', 'top-social-share' ); ?></small>
			<?php

			if ( array_key_exists( 'tss_field_icon_placement_below_post_title', $this->tss_option ) ) {
				$below_post_title = esc_attr( $this->tss_option['tss_field_icon_placement_below_post_title'] );
			} else {
				$below_post_title = '';
			}

			if ( array_key_exists( 'tss_field_icon_placement_floating_left', $this->tss_option ) ) {
				$floating_left = esc_attr( $this->tss_option['tss_field_icon_placement_floating_left'] );
			} else {
				$floating_left = '';
			}

			if ( array_key_exists( 'tss_field_icon_placement_after_post_content', $this->tss_option ) ) {
				$after_post_content = esc_attr( $this->tss_option['tss_field_icon_placement_after_post_content'] );
			} else {
				$after_post_content = '';
			}

			if ( array_key_exists( 'tss_field_icon_placement_inside_feature_image', $this->tss_option ) ) {
				$inside_feature_image = esc_attr( $this->tss_option['tss_field_icon_placement_inside_feature_image'] );
			} else {
				$inside_feature_image = '';
			}
			?>
			<ul class="tss-icon-placement">
				<li>
					<input type="checkbox" id="<?php echo esc_attr( $args['label_for'] . '_floating_left' ); ?>"
						data-custom="<?php echo esc_attr( $args['tss_custom_data'] ); ?>"
						name="tss_options[<?php echo esc_attr( $args['label_for'] . '_floating_left' ); ?>]"
						value="1" <?php checked( 1, $floating_left, true ); ?>/>
					<label for="<?php echo esc_attr( $args['label_for'] . '_floating_left' ); ?>"><?php echo esc_html__( 'Floating Left', 'top-social-share' ); ?></label>
				</li>
				<li>
					<input type="checkbox" id="<?php echo esc_attr( $args['label_for'] . '_below_post_title' ); ?>"
						data-custom="<?php echo esc_attr( $args['tss_custom_data'] ); ?>"
						name="tss_options[<?php echo esc_attr( $args['label_for'] . '_below_post_title' ); ?>]"
						value="1" <?php checked( 1, $below_post_title, true ); ?>/>
					<label for="<?php echo esc_attr( $args['label_for'] . '_below_post_title' ); ?>"><?php echo esc_html__( 'Below post title, before content.', 'top-social-share' ); ?></label>
				</li>
				<li>
					<input type="checkbox" id="<?php echo esc_attr( $args['label_for'] . '_inside_feature_image' ); ?>"
						data-custom="<?php echo esc_attr( $args['tss_custom_data'] ); ?>"
						name="tss_options[<?php echo esc_attr( $args['label_for'] . '_inside_feature_image' ); ?>]"
						value="1" <?php checked( 1, $inside_feature_image, true ); ?>/>
					<label for="<?php echo esc_attr( $args['label_for'] . '_inside_feature_image' ); ?>"><?php echo esc_html__( 'Inside Feature Image', 'top-social-share' ); ?></label>
				</li>
				<li>
					<input type="checkbox" id="<?php echo esc_attr( $args['label_for'] . '_after_post_content' ); ?>"
						data-custom="<?php echo esc_attr( $args['tss_custom_data'] ); ?>"
						name="tss_options[<?php echo esc_attr( $args['label_for'] . '_after_post_content' ); ?>]"
						value="1" <?php checked( 1, $after_post_content, true ); ?>/>
					<label for="<?php echo esc_attr( $args['label_for'] . '_after_post_content' ); ?>"><?php echo esc_html__( 'After post content', 'top-social-share' ); ?></label><br/>
				</li>
			</ul>
		</div>
		<?php
	}
}
$instance = new TSS_Settings();
