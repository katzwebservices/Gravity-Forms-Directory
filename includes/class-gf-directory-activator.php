<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @link       https://gravityview.co
 * @since      TODO
 *
 * @package    gravity-forms-addons
 * @subpackage gravity-forms-addons/includes
 */

class GFDirectory_Activator {
	
	public static function activate() {
		self::add_permissions();
		self::flush_rules();
		self::add_activation_notice();
	}

	public static function add_permissions() {
		global $wp_roles;
		$wp_roles->add_cap( "administrator", "gravityforms_directory" );
		$wp_roles->add_cap( "administrator", "gravityforms_directory_uninstall" );
	}

	 public static function flush_rules() {
		global $wp_rewrite;
		GFDirectory::add_rewrite();
		$wp_rewrite->flush_rules();
		return;
	}

	public static function add_activation_notice() {
		$message = sprintf(
			esc_html__( 'Congratulations - the Gravity Forms Directory & Addons plugin has been installed. %sGo to the settings page%s to read usage instructions and configure the plugin default settings. %sGo to settings page%s', 'gravity-forms-addons' ),
			'<a href="' . esc_url_raw( admin_url( 'admin.php?page=gf_settings&addon=Directory+%26+Addons&viewinstructions=true' ) ) . '">','</a>',
				'<p class="submit"><a href="' . esc_url_raw( admin_url( 'admin.php?page=gf_settings&addon=Directory+%26+Addons&viewinstructions=true' ) ) . '" class="button button-secondary">',
			'</a></p>'
		);
		set_transient( 'kws_gf_activation_notice', $message, 60 * 60 );
	}
}