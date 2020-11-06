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
	}

	public static function add_permissions() {
		global $wp_roles;
		$wp_roles->add_cap( "administrator", "gravityforms_directory" );
		$wp_roles->add_cap( "administrator", "gravityforms_directory_uninstall" );
	}

	static public function flush_rules() {
		global $wp_rewrite;
		self::add_rewrite();
		$wp_rewrite->flush_rules();
		return;
	}
}