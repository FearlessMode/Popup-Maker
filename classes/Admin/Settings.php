<?php
/*******************************************************************************
 * Copyright (c) 2017, WP Popup Maker
 ******************************************************************************/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PUM_Admin_Settings
 */
class PUM_Admin_Settings {

	/**
	 * @var array
	 */
	public static $notices = array();

	/**
	 *
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_init', array( __CLASS__, 'save' ) );
		//add_action( 'pum_license_deactivated', array( __CLASS__, 'license_deactivated' ) );
		//add_action( 'pum_license_check_failed', array( __CLASS__, 'license_deactivated' ) );
	}

	// display default admin notice

	/**
	 * Displays any saved admin notices.
	 */
	public static function notices() {

		if ( isset( $_GET['success'] ) && get_option( 'pum_settings_admin_notice' ) ) {
			self::$notices[] = array(
				'type'    => $_GET['success'] ? 'success' : 'error',
				'message' => get_option( 'pum_settings_admin_notice' ),
			);

			delete_option( 'pum_settings_admin_notice' );
		}

		if ( ! empty( self::$notices ) ) {
			foreach ( self::$notices as $notice ) { ?>
				<div class="notice notice-<?php esc_attr_e( $notice['type'] ); ?> is-dismissible">
					<p><strong><?php esc_html_e( $notice['message'] ); ?></strong></p>
					<button type="button" class="notice-dismiss">
						<span class="screen-reader-text"><?php _e( 'Dismiss this notice.', 'popup-maker' ); ?></span>
					</button>
				</div>
			<?php }
		}
	}


	/**
	 * Save settings when needed.
	 */
	public static function save() {
		if ( ! empty( $_POST['pum_settings'] ) && empty( $_POST['pum_license_activate'] ) && empty( $_POST['pum_license_deactivate'] ) ) {

			if ( ! isset( $_POST['pum_settings_nonce'] ) || ! wp_verify_nonce( $_POST['pum_settings_nonce'], basename( __FILE__ ) ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$settings = self::sanitize_settings( $_POST['pum_settings'] );

			if ( PUM_Options::update_all( $settings ) ) {
				self::$notices[] = array(
					'type'    => 'success',
					'message' => __( 'Settings saved successfully!', 'popup-maker' ),
				);
			} else {
				self::$notices[] = array(
					'type'    => 'error',
					'message' => __( 'There must have been an error, settings not saved successfully!', 'popup-maker' ),
				);
			}

			return;

			/**
			 * Process licensing if set.
			 *
			 * // We store the key in wp_options for use by the update & licensing system to keep things cleanly detached.
			 * $old_license = get_option( 'pum_license_key' );
			 *
			 * if ( empty( $settings['pum_license_key'] ) ) {
			 * delete_option( 'pum_license_key' ); // empty key, remove existing license info.
			 * delete_option( 'pum_license' ); // empty key, remove existing license info.
			 * } else if ( $old_license != $settings['pum_license_key'] ) {
			 * update_option( 'pum_license_key', $settings['pum_license_key'] );
			 * delete_option( 'pum_license' ); // new license has been entered, so must reactivate
			 *
			 * // Prevent additional calls to licensing.
			 * if ( empty( $_POST['pum_license_activate'] ) ) {
			 * $message = PUM_Licensing::activate();
			 *
			 * if ( $message !== true && ! empty ( $message ) ) {
			 * self::$notices[] = array(
			 * 'type'    => 'error',
			 * 'message' => $message,
			 * );
			 * } else {
			 * self::$notices[] = array(
			 * 'type'    => 'success',
			 * 'message' => __( 'License activated successfully!', 'popup-maker' ),
			 * );
			 * }
			 * }
			 * }
			 */
		}


	}

	/**
	 * @param array $settings
	 *
	 * @return array
	 */
	public static function sanitize_settings( $settings = array() ) {

		foreach ( $settings as $key => $value ) {
			$field = self::get_field( $key );

			if ( $field ) {

				switch ( $field['type'] ) {
					default:
						$settings[ $key ] = is_string( $value ) ? trim( $value ) : $value;
						break;

					case 'measure':
						$settings[ $key ] .= $settings[ $key . '_unit' ];
						break;

					case 'license_key':
						$old = PUM_Options::get( $key );
						$new = trim( $value );

						if ( $old && $old != $new ) {
							delete_option( str_replace( '_license_key', '_license_active', $key ) );
							call_user_func( $field['options']['activation_callback'] );
						}

						$settings[ $key ] = is_string( $value ) ? trim( $value ) : $value;
						// Activate / deactivate license keys maybe?
						break;
				}
			} else {
				// Some custom field types include multiple additional fields that do not need to be saved, strip out any non-whitelisted fields.
				unset( $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * @param $id
	 *
	 * @return bool
	 */
	public static function get_field( $id ) {
		$tabs = self::fields();

		foreach ( $tabs as $tab => $sections ) {

			if ( self::is_field( $sections ) ) {
				$sections = array(
					'main' => array(
						$tab => $sections,
					),
				);
			}

			foreach ( $sections as $section => $fields ) {

				foreach ( $fields as $key => $args ) {
					if ( $key == $id ) {
						return $args;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Returns array of pum settings fields.
	 *
	 * @return mixed
	 */
	public static function fields() {

		static $tabs;

		if ( ! isset( $tabs ) ) {
			$tabs = apply_filters( 'pum_settings_fields', array(
				'general'    => array(
					'main' => array(),
				),
				'assets'     => array(
					'main' => array(
						'disable_google_font_loading'     => array(
							'type' => 'checkbox',
							'label' => __( 'Don\'t Load Google Fonts', 'popup-maker' ),
							'desc' => __( 'Check this disable loading of google fonts, useful if the fonts you chose are already loaded with your theme.', 'popup-maker' ),
						),
						'disable_popup_maker_core_styles' => array(
							'type' => 'checkbox',
							'label' => __( 'Don\'t load Popup Maker core stylesheet.', 'popup-maker' ),
							'desc' => __( 'Check this if you have copied the Popup Maker core styles to your own stylesheet or are using custom styles.', 'popup-maker' ),
						),
						'disable_popup_theme_styles'      => array(
							'type' => 'checkbox',
							'label' => __( 'Don\'t load popup theme styles to the head.', 'popup-maker' ),
							'desc' => __( 'Check this if you have copied the popup theme styles to your own stylesheet or are using custom styles.', 'popup-maker' ),
						),
						'output_pum_styles'               => array(
							'id'   => 'output_pum_styles',
							'type' => 'html',
							'content' => popmake_output_pum_styles(),
						),
					),
				),
				'extensions' => array(
					'main' => array(),
				),
				'licenses'   => array(
					'main' => array(),
				),
				'misc'       => array(
					'main' => array(
						'disabled_admin_bar'                   => array(
							'type' => 'checkbox',
							'label' => __( 'Disable Popups Admin Bar', 'popup-maker' ),
							'desc' => __( 'This will disable the admin Popups menu item.', 'popup-maker' ),
						),
						'debug_mode'                           => array(
							'type' => 'checkbox',
							'label' => __( 'Enable Debug Mode', 'popup-maker' ),
							'desc' => __( 'This will turn on multiple debug tools used to quickly find issues.', 'popup-maker' ),
						),
						'enable_easy_modal_compatibility_mode' => array(
							'type'  => 'checkbox',
							'label' => __( 'Enable Easy Modal v2 Compatibility Mode', 'popup-maker' ),
							'desc'  => __( 'This will automatically make any eModal classes you have added to your site launch the appropriate Popup after import.', 'popup-maker' ),
						),
						'disable_popup_open_tracking'          => array(
							'type'  => 'checkbox',
							'label' => __( 'Disables popup open tracking?', 'popup-maker' ),
							'desc'  => __( 'This will disable the built in analytics functionality.', 'popup-maker' ),
						),
						'disable_admin_support_widget'         => array(
							'type'  => 'checkbox',
							'label' => __( 'Hide Admin Support Widget', 'popup-maker' ),
							'desc'  => __( 'This will hide the support widget on all popup maker admin pages.', 'popup-maker' ),
						),
						'disable_popup_category_tag'           => array(
							'type'  => 'checkbox',
							'label' => __( 'Disable categories & tags?', 'popup-maker' ),
							'desc'  => __( 'This will disable the popup tags & categories.', 'popup-maker' ),
						),
						'disable_cache' => array(
							'type'  => 'checkbox',
							'label' => __( 'Disable Popup Maker caching', 'popup-maker' ),
						),
					),
				),
			) );

			foreach ( $tabs as $tab_id => $sections ) {

				foreach ( $sections as $section_id => $fields ) {

					if ( self::is_field( $fields ) ) {
						// Allow for flat tabs with no sections.
						$section_id = 'main';
						$fields     = array(
							$section_id => $fields,
						);
					}

					foreach ( $fields as $field_id => $field ) {
						if ( ! is_array( $field ) || ! self::is_field( $field ) ) {
							continue;
						}

						if ( empty( $field['id'] ) ) {
							$field['id'] = $field_id;
						}
						if ( empty( $field['name'] ) ) {
							$field['name'] = 'pum_settings[' . $field_id . ']';
						}

						$tabs[ $tab_id ][ $section_id ][ $field_id ] = wp_parse_args( $field, array(
							'section'        => 'main',
							'type'           => 'text',
							'id'             => null,
							'label'          => '',
							'desc'           => '',
							'name'           => null,
							'templ_name'     => null,
							'size'           => 'regular',
							'options'        => array(),
							'std'            => null,
							'rows'           => 5,
							'cols'           => 50,
							'min'            => 0,
							'max'            => 50,
							'force_minmax'   => false,
							'step'           => 1,
							'select2'        => null,
							'object_type'    => 'post_type',
							'object_key'     => 'post',
							'post_type'      => null,
							'taxonomy'       => null,
							'multiple'       => null,
							'as_array'       => false,
							'placeholder'    => null,
							'checkbox_val'   => 1,
							'allow_blank'    => true,
							'readonly'       => false,
							'required'       => false,
							'disabled'       => false,
							'hook'           => null,
							'unit'           => __( 'ms', 'popup-maker' ),
							'desc_position'  => 'bottom',
							'units'          => array(
								'px'  => 'px',
								'%'   => '%',
								'em'  => 'em',
								'rem' => 'rem',
							),
							'priority'       => null,
							'doclink'        => '',
							'button_type'    => 'submit',
							'class'          => '',
							'messages'       => array(),
							'license_status' => '',
						) );
					}
				}
			}
		}

		return $tabs;
	}

	/**
	 * Checks if an array is a field.
	 *
	 * @param array $array
	 *
	 * @return bool
	 */
	public static function is_field( $array = array() ) {
		$field_tests = array(
			isset( $array['id'] ),
			isset( $array['label'] ),
			isset( $array['type'] ),
			isset( $array['options'] ),
			isset( $array['desc'] ),
		);

		return in_array( true, $field_tests );
	}

	/**
	 * @return array
	 */
	public static function user_role_options() {
		global $wp_roles;

		$options = array();
		foreach ( $wp_roles->roles as $role => $labels ) {
			$options[ $role ] = $labels['name'];
		}

		return $options;
	}

	/**
	 * Render settings page with tabs.
	 */
	public static function page() {

		$settings = PUM_Options::get_all();

		if ( empty( $settings ) ) {
			$settings = self::defaults();
		}

		?>

		<div class="wrap">

			<form id="pum-settings" method="post" action="">

				<?php wp_nonce_field( basename( __FILE__ ), 'pum_settings_nonce' ); ?>

				<button class="right top button-primary"><?php _e( 'Save', 'popup-maker' ); ?></button>

				<h1><?php _e( 'Popup Maker Settings', 'popup-maker' ); ?></h1>

				<div id="pum-settings-container" class="pum-settings-container"></div>

				<script type="text/javascript">
                    window.pum_settings_editor = <?php echo json_encode( apply_filters( 'pum_settings_editor_args', array(
						'form_args'      => array(
							'id'       => 'pum-settings',
							'tabs'     => self::tabs(),
							'sections' => self::sections(),
							'fields'   => self::fields(),
							'maintabs' => array(
								'meta' => array(
									'data-min-height' => 0,
								),
							),
						),
						'active_tab'     => self::get_active_tab(),
						'active_section' => self::get_active_section(),
						'current_values' => self::parse_values( $settings ),
					) ) ); ?>;
				</script>

				<button class="button-primary bottom right"><?php _e( 'Save', 'popup-maker' ); ?></button>

			</form>
		</div>

		<?php
	}

	/**
	 * @return array
	 */
	public static function defaults() {
		$tabs = self::fields();

		$defaults = array();

		foreach ( $tabs as $section_id => $fields ) {
			foreach ( $fields as $key => $field ) {
				$defaults[ $key ] = isset( $field['std'] ) ? $field['std'] : null;
			}
		}

		return $defaults;
	}

	/**
	 * List of tabs & labels for the settings panel.
	 *
	 * @return array
	 */
	public static function tabs() {
		static $tabs;

		if ( ! isset( $tabs ) ) {
			$tabs = apply_filters( 'pum_settings_tabs', array(
				'general'    => __( 'General', 'popup-maker' ),
				'assets'     => __( 'Assets', 'popup-maker' ),
				'extensions' => __( 'Extensions', 'popup-maker' ),
				'licenses'   => __( 'Licenses', 'popup-maker' ),
				'misc'       => __( 'Misc', 'popup-maker' ),
			) );

			/** @deprecated 1.7.0 */
			$tabs = apply_filters( 'popmake_settings_tabs', $tabs );
		}


		return $tabs;
	}

	/**
	 * List of tabs & labels for the settings panel.
	 *
	 * @return array
	 */
	public static function sections() {
		return apply_filters( 'pum_settings_tab_sections', array(
			'general'    => array(
				'main' => __( 'General Settings', 'popup-maker' ),
			),
			'assets'     => array(
				'main' => __( 'Asset Settings', 'popup-maker' ),
			),
			'extensions' => array(
				'main' => __( 'Extension Settings', 'popup-maker' ),
			),
			'licenses'   => array(
				'main' => __( 'License Settings', 'popup-maker' ),
			),
			'misc'       => array(
				'main' => __( 'Misc Settings', 'popup-maker' ),
			),
		) );
	}

	/**
	 * @return int|null|string
	 */
	public static function get_active_tab() {
		$tabs = self::tabs();

		return isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $tabs ) ? sanitize_text_field( $_GET['tab'] ) : key( $tabs );
	}

	/**
	 * @return bool|int|null|string
	 */
	public static function get_active_section() {
		$active_tab = self::get_active_tab();
		$sections   = self::sections();

		$tab_sections = ! empty( $sections[ $active_tab ] ) ? $sections[ $active_tab ] : false;

		if ( ! $tab_sections ) {
			return false;
		}

		return isset( $_GET['section'] ) && array_key_exists( $_GET['section'], $tab_sections ) ? sanitize_text_field( $_GET['section'] ) : key( $tab_sections );
	}

	/**
	 * Parse values for form rendering.
	 *
	 * Add additional data for license_key fields, split the measure fields etc.
	 *
	 * @param $settings
	 *
	 * @return mixed
	 */
	public static function parse_values( $settings ) {

		foreach ( $settings as $key => $value ) {
			$field = self::get_field( $key );


			if ( $field ) {

				/**
				 * Process fields with specific types.
				 */
				switch ( $field['type'] ) {
					case 'measure':
						break;
					case 'license_key':
						$license  = get_option( $field['options']['is_valid_license_option'] );

						$settings[ $key ] = array(
							'key'      => trim( $value ),
							'status'   => PUM_Licensing::get_status( $license, ! empty( $value ) ),
							'messages' => PUM_Licensing::get_status_messages( $license, trim( $value ) ),
							'expires'  => PUM_Licensing::get_license_expiration( $license ),
							'classes'  => PUM_Licensing::get_status_classes( $license ),
						);
						break;
				}

				/**
				 * Process fields with specific ids.
				 */
				switch ( $field['id'] ) {
					/*
					case 'pum_license_status':
						$settings[ $key ] = Licensing::get_status();
						break;
					*/
				}

			}
		}

		return $settings;
	}

	/**
	 * Checks if an array is a section.
	 *
	 * @param array $array
	 *
	 * @return bool
	 */
	public static function is_section( $array = array() ) {
		return ! self::is_field( $array );
	}

	/**
	 *
	 */
	public static function license_deactivated() {

	}

	/**
	 * @param array $meta
	 *
	 * @return array
	 */
	public static function sanitize_objects( $meta = array() ) {
		if ( ! empty( $meta ) ) {

			foreach ( $meta as $key => $value ) {

				if ( is_string( $value ) ) {
					try {
						$value = json_decode( stripslashes( $value ) );
					} catch ( Exception $e ) {
					};
				}

				$meta[ $key ] = PUM_Admin_Helpers::object_to_array( $value );
			}
		}

		return $meta;
	}


}
