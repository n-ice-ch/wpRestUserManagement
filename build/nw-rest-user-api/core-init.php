<?php

// If this file is called directly, abort. //
if ( ! defined( 'WPINC' ) ) {die;} // end if

// ======================================================================================

// ### Important hook to get a valid nonce via REST api #################################
function nrua_update_cookie( $logged_in_cookie )
{
	$_COOKIE[LOGGED_IN_COOKIE] = $logged_in_cookie;
}
add_action( 'set_logged_in_cookie', 'nrua_update_cookie' );

add_action( 'wp_logout', function() { wp_set_current_user( 0 ); }, PHP_INT_MAX );
// ======================================================================================


if ( file_exists( plugin_dir_path( __FILE__ ) . 'modules/rest/rest-callback-users.php' ) ) 
{
	require_once( plugin_dir_path( __FILE__ ) . 'modules/rest/rest-callback-users.php' );

}

if ( file_exists( plugin_dir_path( __FILE__ ) . 'modules/rest/rest-route-users.php' ) ) 
{

	require_once( plugin_dir_path( __FILE__ ) . 'modules/rest/rest-route-users.php' );

}

