<?php
/*
Plugin Name: 	Gravity Forms Directory & Addons
Plugin URI: 	https://katz.co/gravity-forms-addons/
Description: 	Turn <a href="https://katz.si/gravityforms">Gravity Forms</a> into a great WordPress directory...and more!
Author: 		Katz Web Services, Inc.
Version: 		4.1.3
Author URI:		https://gravityview.co
Text Domain:    gravity-forms-addons
License:		GPLv2 or later
License URI: 	https://www.gnu.org/licenses/gpl-2.0.html

Copyright 2018 Katz Web Services, Inc.  (email: info@katzwebservices.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
if ( ! defined( 'GF_DIRECTORY_VERSION' ) ) {
	define( 'GF_DIRECTORY_VERSION', '4.1.3' );
}
if ( ! defined( 'GF_DIRECTORY_URL' ) ) {
	define( 'GF_DIRECTORY_URL', plugins_url( '/', __FILE__ ) );
}
if ( ! defined( 'GF_DIRECTORY_PATH' ) ) {
	define( 'GF_DIRECTORY_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'GF_DIRECTORY_PLUGIN_BASENAME' ) ) {
	define( 'GF_DIRECTORY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'GF_DIRECTORY_FILE' ) ) {
	define( 'GF_DIRECTORY_FILE', __FILE__ );
}

define( 'GF_DIRECTORY_MIN_GF_VERSION', '2.4' );

if ( ! gf_directory_check_dependancy() ) {
	return;
}

/**
* Check if Gravity Forms is installed.
*
* @return void
*/
function gf_directory_check_dependancy() {

	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	// check if dependency is met.
	if ( ! is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		unset( $_GET['activate'] );
		add_action( 'admin_notices', 'gf_directory_dependancy_notice' );
		return false;
	}
	return true;
}

/**
* Outputs a loader warning notice.
*
* @return void
*/
function gf_directory_dependancy_notice() {
	echo '<div class="error"><p> ' . __( 'Plugin deactivated - To make <strong>Gravity Forms Directory & Addons</strong> plugin work, you need to install and activate Gravity Forms plugin first.', 'gravity-forms-addons' ) . '</p></div>';
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-gf-directory-activator.php
 */
function activate_gf_directory() {
	require_once GF_DIRECTORY_PATH . 'includes/class-gf-directory-activator.php';
	GFDirectory_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-gf-directory-deactivator.php
 */
function deactivate_gf_directory() {
	require_once GF_DIRECTORY_PATH . 'includes/class-gf-directory-deactivator.php';
	GFDirectory_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_gf_directory' );
register_deactivation_hook( __FILE__, 'deactivate_gf_directory' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-gf-directory.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-gf-directory-shortcode.php';


/**
 * Main instance of GFDirectory.
 *
 * Returns the main instance of the class Instance.
 *
 * @since  3.0.0
 *
 * @return object GFDirectory
 */
function gfdirectory_class_instance() {
	return GFDirectory::get_instance();
}

$gf_directory = gfdirectory_class_instance();
GFDirectory_Shortcode::get_instance();

add_action( 'plugins_loaded', 'kws_gf_load_functions' );

function kws_gf_load_functions() {

	// If Gravity Forms is installed and exists
	if ( defined( 'RG_CURRENT_PAGE' ) ) {

		function gf_field_value( $leadid, $fieldid, $form = array() ) {
			echo get_gf_field_value( $leadid, $fieldid, $form );
		}


		// To retrieve textarea inputs from a lead
		// Example: get_gf_field_value_long(22, '14');
		function get_gf_field_value_long( $leadid, $fieldid, $form = array(), $apply_filter = true ) {
			return RGFormsModel::get_field_value_long( $leadid, $fieldid, $form, $apply_filter );
		}

		// To retrieve textarea inputs from a lead
		// Example: get_gf_field_value_long(22, '14');
		function get_gf_field_value( $leadid, $fieldid, $form = array() ) {
			$lead    = RGFormsModel::get_lead( $leadid );
			$fieldid = floatval( $fieldid );
			if ( is_numeric( $fieldid ) ) {
				$result = $lead["$fieldid"];
			}

			$max_length = GFORMS_MAX_FIELD_LENGTH;

			if ( strlen( $result ) >= ( $max_length - 50 ) ) {
				$result = get_gf_field_value_long( $lead["id"], $fieldid, $form );
			}
			$result = trim( $result );

			if ( ! empty( $result ) ) {
				return $result;
			}

			return false;
		}

		function gf_field_value_long( $leadid, $fieldid, $form = array() ) {
			echo get_gf_field_value_long( $leadid, $fieldid, $form );
		}


		// Gives you the label for a form input (such as First Name). Enter in the form and the field ID to access the label.
		// Example: echo get_gf_field_label(1,1.3);
		// Gives you the label for a form input (such as First Name). Enter in the form and the field ID to access the label.
		// Example: echo get_gf_field_label(1,1.3);
		function get_gf_field_label( $form_id, $field_id ) {
			$form = RGFormsModel::get_form_meta( $form_id );
			foreach ( $form["fields"] as $field ) {
				if ( $field->id == $field_id ) {
					$output = esc_html( $field->label ); // Using esc_html(), a WP function
				} elseif ( is_array( $field->inputs ) ) {
					foreach ( $field->inputs as $input ) {
						if ( $input['id'] == $field_id ) {
							$output = esc_html( GFCommon::get_label( $field, $field_id ) );
						}
					}
				}
			}

			return $output;
		}

		function gf_field_label( $form_id, $field_id ) {
			echo get_gf_field_label( $form_id, $field_id );
		}

		// Returns a form using php instead of shortcode
		function get_gf_form( $id, $display_title = true, $display_description = true, $force_display = false, $field_values = NULL ) {
			if ( class_exists( 'GFFormDisplay' ) ) {
				return GFFormDisplay::get_form( $id, $display_title = true, $display_description = true, $force_display = false, $field_values = NULL );
			} else {
				return RGFormsModel::get_form( $id, $display_title, $display_description );
			}
		}

		function gf_form( $id, $display_title = true, $display_description = true, $force_display = false, $field_values = NULL ) {
			echo get_gf_form( $id, $display_title, $display_description, $force_display, $field_values );
		}

		// Returns array of leads for a specific form
		function get_gf_leads( $form_id, $sort_field_number = 0, $sort_direction = 'DESC', $search = '', $offset = 0, $page_size = 3000, $star = NULL, $read = NULL, $is_numeric_sort = false, $start_date = NULL, $end_date = NULL, $status = 'active', $approvedcolumn = false, $limituser = false ) {
			return GFDirectory::get_leads( $form_id, $sort_field_number, $sort_direction, $search, $offset, $page_size, $star, $read, $is_numeric_sort, $start_date, $end_date, $status, $approvedcolumn, $limituser );
		}

		function gf_leads( $form_id, $sort_field_number = 0, $sort_direction = 'DESC', $search = '', $offset = 0, $page_size = 3000, $star = NULL, $read = NULL, $is_numeric_sort = false, $start_date = NULL, $end_date = NULL ) {
			echo get_gf_leads( $form_id, $sort_field_number, $sort_direction, $search, $offset, $page_size, $star, $read, $is_numeric_sort, $start_date, $end_date );
		}

		function kws_gf_directory( $atts ) {
			GFDirectory::make_directory( $atts );
		}


		if ( ! function_exists( 'kws_print_r' ) ) {
			function kws_print_r( $content, $die = false ) {
				echo '<pre>' . print_r( $content, true ) . '</pre>';
				if ( $die ) {
					die();
				}

				return $content;
			}
		}

	}
}

/* Ending ?> left out intentionally */
