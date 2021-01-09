<?
// ### If this file is called directly, abort #####################################################
if ( ! defined( 'WPINC' ) ) {die;}
// ################################################################################################

// === Register REST routes and endpoints =========================================================
add_action( 'rest_api_init', function () 
{
	// ### U S E R S ##########################################################
		register_rest_route( 'nw/v1', '/users/login/', array(
				'methods'  => 'POST',
				'callback' => 'nrua_post_user_login',
		) );
		
		register_rest_route( 'nw/v1', '/users/logout/', array(
				'methods'  => 'GET',
				'callback' => 'nrua_get_user_logout',
		) );

		register_rest_route( 'nw/v1', '/users/register', array(
				'methods' => 'POST',
				'callback' => 'nrua_users_register',
		) );

		register_rest_route( 'nw/v1', '/users/confirm', array(
				'methods' => 'POST',
				'callback' => 'nrua_users_confirm',
		) );

		register_rest_route( 'nw/v1', '/users/confirm/resend', array(
				'methods' => 'POST',
				'callback' => 'nrua_users_confirm_resend',
		) );
	
		register_rest_route( 'nw/v1', '/users/password/lost', array(
				'methods' => 'POST',
				'callback' => 'nrua_users_password_lost',
		) );

		register_rest_route( 'nw/v1', '/users/password/change', array(
				'methods' => 'POST',
				'callback' => 'nrua_users_password_change',
	) );
	
})

?>