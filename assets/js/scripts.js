/**
 * File add custom js operations like
 * - make social sharing icon sortable
 * - add color picker js
 *
 * @package TSS
 */

jQuery( document ).ready(
	function(){
		// Add color picker.
		jQuery( '.tss-style-container .color-field' ).wpColorPicker();

		/**
		 * Function to show color picker field for custom style selection only.
		 */
		function show_color_picker_for_custom(){
			var $this             = jQuery( this );
			var $colorFieldParent = $this.parent().next( 'div' ).first();

			if ('custom' === $this.val()) {
				$colorFieldParent.slideDown( 'fast' );
			} else {
				$colorFieldParent.hide();
			}
		}

		/**
		 * Function used to track and handle sorted social options.
		 *
		 * This function creates hidden field to track reordered item lists.
		 */
		var to_input = function(this_sortable){
			// Clear any previous hidden inputs for storing chosen services.
			// and special service options.
			jQuery( 'input.tss_hidden_options' ).remove();
			var services_array = jQuery( this_sortable ).sortable( 'toArray', {attribute: 'data-tss-icon-name'} ),
			services_size      = services_array.length;
			if (services_size < 1) {
				return;
			}

			for (var i = 0, service_name, show_count_value, fb_verb_value; i < services_size; i++) {
				if (services_array[i] != '') { // Exclude dummy icon.
					// Special service options?.
					jQuery( '#tss_admin_form' ).append( '<input class="tss_hidden_options" name="tss_options[tss_social_icon_order_' + i + ']" type="hidden" value="' + services_array[i] + '"/>' );
				}
			}
		};

		// Make Social sharing options sortable.
		jQuery( '#tss-social-icon-sortable' ).sortable(
			{
				forcePlaceholderSize: true,
				placeholder: 'ui-sortable-placeholder',
				opacity: 0.6,
				tolerance: 'pointer',
				update: function(){
					to_input( this );
				}
			}
		);

		// Show color picker when "Custom" color is selected.
		jQuery( 'select.tss_icon_color' ).on( 'change', show_color_picker_for_custom ).each( show_color_picker_for_custom );
	}
);
