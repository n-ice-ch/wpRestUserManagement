<?php
/*
Plugin Name: WP REST User Framework (nw-rest-user-api / nrua)
Plugin URI: https://github.com/n-ice-ch/wpRestUserManagement
Description: REST based user framework for Wordpress as backend for mobile applications. Simplified with REST requests only.
Version: 1.2.0
Author: n-iceware.com - Evgen "EvgenDob" Dobrzhanskiy
Author URI: https://github.com/n-ice-ch/wpRestUserManagement
Stable tag: 1.2
*/

//error_reporting(E_ALL);
//ini_set('display_errors', 'On');


if ( ! defined( 'ABSPATH' ) ) {
	wp_die( 'Direct Access is not Allowed' );
}

// core initialization
if( !class_Exists('nwMain') ){
	class nwMain{
		public $locale;
		function __construct( $locale, $includes, $path ){
			$this->locale = $locale;
			
			// include files
			foreach( $includes as $single_path ){
				include( $path.$single_path );				
			}
			// calling localization
			add_action('plugins_loaded', array( $this, 'myplugin_init' ) );

			register_activation_hook( __FILE__, array( $this, 'plugin_activation' ) );
			
			register_uninstall_hook(__FILE__, 'plugin_uninstall');
		}

		function plugin_activation(){
			flush_rewrite_rules();
		}
		
		function plugin_uninstall(){
			 
		}

		function myplugin_init() {
					
		 	$plugin_dir = basename(dirname(__FILE__));
		 	load_plugin_textdomain( $this->locale , false, $plugin_dir );
			
			if ( file_exists( plugin_dir_path( __FILE__ ) . 'core-init.php' ) ) {

				require_once( plugin_dir_path( __FILE__ ) . 'core-init.php' );
			}
			
			require_once plugin_dir_path(__FILE__) . 'modules/class-db.php';
			nrua_db_check();

		}
	}

}

// initiate main class

$obj = new nwMain('nrua', array(
	'modules/formElementsClass.php',
	'modules/scripts.php',
	'modules/helper.php',
	'modules/settings.php',
	'modules/hooks.php',
	'modules/ajax.php',
), dirname(__FILE__).'/' );
 
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'salcode_add_plugin_page_settings_link');
function salcode_add_plugin_page_settings_link( $links ) {
	$links[] = '<a href="' .
		admin_url( 'options-general.php?page=nruauser_api_settings' ) .
		'">' . __('Settings') . '</a>';
	return $links;
}

/* === Replaced with plugin "WP Mail SMTP" ==================================================
// Function to change email address
function wpb_sender_email( $original_email_address ) {
    return 'someone@example.com';
}
 
// Function to change sender name
function wpb_sender_name( $original_email_from ) {
    return 'Someone - No reply';
}
 
// Hooking up our functions to WordPress filters 
add_filter( 'wp_mail_from', 'wpb_sender_email' );
add_filter( 'wp_mail_from_name', 'wpb_sender_name' );
============================================================================================= */

?>