<?php
/**
 * Display entry details in AJAX lightbox
 */

if ( ! isset( $_REQUEST['leadid'] ) || ! isset( $_REQUEST['form'] ) || ! isset( $_GET['view'] ) ) {
	die( 'Entry ID, Form ID, and access key must be set.' );
}

// Bootstratp WordPress core.
$bootstrap_search_dir = dirname( $_SERVER['SCRIPT_FILENAME'] );
$doc_root = dirname( isset( $_SERVER['APPL_PHYSICAL_PATH'] ) ? $_SERVER['APPL_PHYSICAL_PATH'] : $_SERVER['DOCUMENT_ROOT'] );

while ( ! file_exists( $bootstrap_search_dir . '/wp-load.php' ) ) {
	$bootstrap_search_dir = dirname( $bootstrap_search_dir );
	if ( strpos( $bootstrap_search_dir, $doc_root ) === false ) {
		$bootstrap_search_dir = '../../..'; // critical failure in our directory finding, so fall back to relative
		break;
	}
}

// Include the two files we need instead of using `wp-load.php`
require_once( $bootstrap_search_dir . '/wp-blog-header.php' );
require_once( $bootstrap_search_dir . '/wp-admin/includes/admin.php' );

// Check that the GF Directory plugin is active. If it isn't, this shouldn't be shown.
if ( ! is_plugin_active( GF_DIRECTORY_PLUGIN_BASENAME ) ) {
	wp_die( 'The Gravity Forms Directory plugin is not active.', 'gravity-forms-addons' );
}

// Verify that the link is accessed properly
if ( false == wp_verify_nonce( $_GET['view'], sprintf( 'view-%d-%d', $_REQUEST['leadid'], $_REQUEST['form'] ) ) ) {
	 wp_die( 'Verification failed. Please return to the original page, refresh, and click on the entry again.', 'gravity-forms-addons' );
}

// Force a happy header
header( 'HTTP/1.1 200 OK' );

// Prevent some theoretical random stuff from happening
define( 'IFRAME_REQUEST', true );

// Set current screen to prevent error in wp_auth_check_load()
if ( function_exists( 'set_current_screen' ) ) {
	set_current_screen( 'gf-directory' );
}

// Get the GF Styles
wp_enqueue_style( 'gf-admin', GFCommon::get_base_url() . '/css/admin.css', array(), GF_DIRECTORY_VERSION );

// Get the WP Styles
register_admin_color_schemes();

// Get the GF Scripts (for the Lists functionality, etc)
wp_enqueue_script( 'gform_gravityforms', null, array( 'jquery' ), GF_DIRECTORY_VERSION );

// Generate output for IFRAME
// @see http://codex.wordpress.org/Function_Reference/wp_iframe
wp_iframe( 'show_table' );

/**
 * Generate the output for the IFRAME
 */
function show_table() {

	if ( isset( $_REQUEST['leadid'] ) && isset( $_REQUEST['form'] ) ) {

		require_once( GF_DIRECTORY_PATH . 'gravity-forms-addons.php' );

		$transient = false;
		if ( isset( $_REQUEST['post'] ) ) {
			$transient = get_transient( 'gf_form_' . $_REQUEST['form'] . '_post_' . $_REQUEST['post'] . '_showadminonly' );
		}
		$output = '<style>html, body { margin:0; padding: 0!important; } div.wrap { padding:.25em .5em; }</style>';
		$output .= "<div class='wrap'>";

		$leadid = (int) $_REQUEST['leadid'];

		$lightbox = apply_filters( 'kws_gf_directory_showadminonly_lightbox', apply_filters( 'kws_gf_directory_showadminonly_lightbox_' . $_REQUEST['form'], $transient, $leadid ), $leadid );

		$detail = GFDirectory::process_lead_detail( false, '', $lightbox, $leadid );

		$detail = apply_filters( 'kws_gf_directory_detail', apply_filters( 'kws_gf_directory_detail_' . $leadid, $detail, $leadid ), $leadid );

		$output .= $detail . '</div>';

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
