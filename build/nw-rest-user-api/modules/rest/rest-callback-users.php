<? 
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// ### If this file is called directly, abort #####################################################
if ( ! defined( 'WPINC' ) ) {die;}
// ################################################################################################

// === Login for further REST calls ===============================================================
function nrua_post_user_login( WP_REST_Request $request ) 
{	
	$parameters 	= $request->get_json_params();
	
	$email 			= sanitize_email( 		stripslashes( $parameters['email'] ) );
	$password 		= sanitize_text_field( 	stripslashes( $parameters['password'] ) );
	
	// = Simple validate email & password
	if( 
		strlen( $email ) 	> 1 && strlen( $email ) 	< 255   &&
		strlen( $password ) > 1 && strlen( $password ) 	< 255   
	) {} else {
	
		$response =  [
			'code' => 400,
			'message' => __( 'Username or password invalid.', 'nrua' ),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );
	} 
	
	// = Validate password value
	$pass_errors = nrua_check_password( $password );
	
	if( count( $pass_errors ) > 0 ){
		$response = [
			'code' => 400,
			'message' => __( 'Please, use more complex password.', 'nrua' ),
			'data' => [
				'status' => 400,
				'errors' => $pass_errors
				]
			];
		return new WP_REST_Response( $response, 400 );
	}
			
	// = Validate email value
	if( !is_email( $email ) ){
		$response = [
			'code' => 400,
			'message' => __( 'Please, use valid email address', 'nrua' ),
			'data' => [
				'status' => 400,
			]
		];
		return new WP_REST_Response( $response, 400 );
	}

	// = Try to login
	$creds 					= array();
	$creds['user_login'] 	= $email;
	$creds['user_password'] = $password;
	$creds['remember'] 		= true;

	$user 					= wp_signon( $creds, false );
	
	if ( is_wp_error($user) ) 
	{
		$response = [
			'code' => 403,
			'message' => __( $user->get_error_code(), 'nrua' ),
			'data' => [
				'status' => 403,
			]
		];
		return new WP_REST_Response( $response, 403 );
	}
	
	// = Check if confirmed
	$nw_user_confirmed = get_user_meta( $user->ID, 'nw_user_confirmed', true );

	if( $nw_user_confirmed == '0' ){

		$response = [
			'code' => 401,
			'message' => __( 'Missing user confirmation.', 'nrua'),
			'data' => [
				'status' => 401,
			]
		];
		return new WP_REST_Response( $response, 401 );
	}		

	// = Procedure to login a user object
	wp_clear_auth_cookie();
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID );
	do_action('my_update_cookie');

	$first_name = get_user_meta( $user->ID, 'first_name', true);
	$last_name 	= get_user_meta( $user->ID, 'last_name', true);

	// = Create nonce to be used for further REST calls
	$nonce 		= wp_create_nonce( 'wp_rest' );
	$siteHash 	= constant( 'COOKIEHASH' );
	
	$cookieData = wp_parse_auth_cookie( '', 'logged_in' );
	
	$username 	= $cookieData['username'];
	$expiration = $cookieData['expiration'];
	$token 		= $cookieData['token'];
	$hmac 		= $cookieData['hmac'];
	
	// = Create cookie to be used for further REST calls
	$cookie = 'wordpress_logged_in_' .$siteHash .'=' .urlencode( $username .'|' .$expiration .'|' .$token .'|' .$hmac );
		
	$response = [
		'code' => 200,
		'message' => __( 'Login successfully', 'nrua' ),
		'username' => $username,
		'firstname' => $first_name,
		'lastname' => $last_name,
		'nonce' => $nonce,
		'cookie' => $cookie,
		'data' => [
			'status' => 200
		]
	];
	return new WP_REST_Response( $response, 200 );
		
}

// === Logout from further REST calls =============================================================
function nrua_get_user_logout( $ressource ) 
{
	// = Validate user credentials
	$user 		= wp_get_current_user();
	$user_ID 	= $user->ID;
	
	if ( $user_ID < 1 )
	{
		
		$response = [
			'code' => 400,
			'message' => __( 'No userID available!', 'nrua' ),
			'nonce' => 0,
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );	
	
	}

	$user = wp_logout();
	
	if ( is_wp_error( $user ) ) 
	{
		
		$response = [
			'code' => 400,
			'message' => __( $user->get_error_message(), 'nrua' ),
			'nonce' => 0,
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );		
	
	} else {
		
		$response = [
			'code' => 200,
			'message' => __( 'Successfully logged out.', 'nrua' ),
			'nonce' => NULL,
			'user_ID' => $user_ID,
			'data' => [
				'status' => 200
			]
		];
		return new WP_REST_Response( $response, 200 );		
		
	}	
}

// === Register user account ======================================================================
function nrua_users_register( WP_REST_Request $request ){

	$settings = get_option( 'nrua_options' );

	if( $settings['disable_registrations'] == 'yes' ){
		$response = [
			'code' => 403,
			'message' => __( 'Registrations currently disabled (Maintenance).', 'nrua' ),
			'data' => [
				'status' => 400,
			]
		];
		return new WP_REST_Response( $response, 400 );
	}

	$parameters = $request->get_json_params();
 

	$first_name 	= sanitize_text_field( stripslashes( $parameters['user_firstname'] ) );
	$last_name 		= sanitize_text_field( stripslashes( $parameters['user_lastname'] ) );
	$email 			= sanitize_email( stripslashes( $parameters['email'] ) );
	$password 		= sanitize_text_field( stripslashes( $parameters['password'] ) );
	$acceptTerms 	= $parameters['acceptTerms']   ;
 

	if( $acceptTerms === true  ){
	 
		if( 
			strlen( $first_name ) 	> 1 && strlen( $first_name ) 	< 255   &&
			strlen( $last_name ) 	> 1 && strlen( $last_name ) 	< 255   
			
			){

			// check passowrd
			$pass_errors = nrua_check_password( $password );
			if( count( $pass_errors ) > 0 ){
				$response = [
					'code' => 400,
					'message' => __( 'Please, use more complex password.', 'nrua' ),
					'data' => [
						'status' => 400,
						'errors' => $pass_errors
					]
				];
				return new WP_REST_Response( $response, 400 );
			}

			// verify email
			if( !is_email( $email ) ){
				$response = [
					'code' => 400,
					'message' => __( 'Please, use valid email address', 'nrua' ),
					'data' => [
						'status' => 400,
					]
				];
				return new WP_REST_Response( $response, 400 );
			}

			//creation of user
			$user_id = username_exists( $email );
			if ( ! $user_id && false == email_exists( $email ) ) {
				$user_id = wp_create_user( $email, $password, $email );

				// update meta
				update_user_meta( $user_id, 'first_name', $first_name );
				update_user_meta( $user_id, 'last_name', $last_name );

				// generate code
				$random_code = rand( 100000, 999999 );
				update_user_meta( $user_id, 'nw_user_confirmation_code', $random_code );
				update_user_meta( $user_id, 'nw_user_confirmed', 0 );

				// send email

				$to = $email;
				$subject = 'Athletic Circuit Training - Registration';
				$body = '<p>Dear %first_name%,</p>
				<p>Thank you for register to our service!</p>
				<p>To confirm and activate your registration please send following activation code within your
				app:</p>
				<table style="border-collapse: collapse; width: 80%;" border="1">
				<tbody>
				<tr>
				<td style="width: 100%; text-align: center;">%six_digit_code%</td>
				</tr>
				</tbody>
				</table>
				<p>Thank you, your Athletic Circuit Training admins.</p>';

				$body = str_replace('%first_name%', $first_name, $body);
				$body = str_replace('%six_digit_code%', $random_code, $body);
			 
				$headers = array( 'Content-Type: text/html; charset=UTF-8' );
				
				$email_send_result = wp_mail( $to, $subject, $body, $headers );

				if( !$email_send_result ){
					$response = [
						'code' => 500,
						'message' => __( 'Generate confirmation email failed.', 'nrua' ),
						'data' => [
							'status' => 500
						]
					];
					return new WP_REST_Response( $response, 500 );
				} 

				$response =  [
					'code' => 200,
					'id' => $user_id,
					'message' => __( 'User registration request successful, wait for confirmation code by email.', 'nrua' ),
					'data' => [
						'status' => 200
					]
				];
				return new WP_REST_Response( $response, 200 );
			}else{
				
				// = Account seems to exist already.
				// = Try to login
				$auth = wp_authenticate_username_password( NULL, $email, $password );
				if ( !is_wp_error( $auth ) ) {
				
					$user_id = username_exists( $email );

					// = Check if confirmed
					$nw_user_confirmed = get_user_meta( $user_id, 'nw_user_confirmed', true );		
//					nrua_writeLog( 'nrua_users_register','nw_user_confirmed=' .$nw_user_confirmed );
					
					if( $nw_user_confirmed == '0' ){

						$response = [
							'code' => 401,
							'message' => __( 'Missing user confirmation.', 'nrua' ),
							'data' => [
								'status' => 401,
							]
						];
						return new WP_REST_Response( $response, 401 );
					}

				}

				// = Seems to be a double registration?
				$response =  [
					'code' => 406,
					'message' => __( 'Username already exists, please enter another username', 'nrua' ),
					'data' => [
						'status' => 400
					]
				];
				return new WP_REST_Response( $response, 400 );
			}
	
		}else{
			$response =  [
				'code' => 406,
				'message' => __( 'User first name and last name should be from 2 to 255 chars', 'nrua' ),
				'data' => [
					'status' => 400
				]
			];
			return new WP_REST_Response( $response, 400 );
		}
	}else{
		// 
		$response =  [
			'code' => 406,
			'message' => __( 'User has not accepted general terms.', 'nrua' ),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );
	}

}

// === Confirm user account =======================================================================
function nrua_users_confirm( WP_REST_Request $request ){

	$parameters = $request->get_json_params();

	$email = sanitize_email( stripslashes( $parameters['email'] ) );
	$password = sanitize_text_field( stripslashes( $parameters['password'] ) );
	$confirmation_code = sanitize_text_field( stripslashes( $parameters['confirmation_code'] ) );

	// auth check
	$auth = wp_authenticate_username_password( NULL, $email, $password );
	if ( is_wp_error( $auth ) ) {

		$response =  [
			'code' => 401,
			'message' => __( 'User authentication failed.', 'nrua' ),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );
	} else {
		$user_id = username_exists( $email );
		// verify confirmed
		$nw_user_confirmed = get_user_meta( $user_id, 'nw_user_confirmed', true );

		if( $nw_user_confirmed == '0' ){

			// check confirmation code
			$user_conf_code = get_user_meta( $user_id, 'nw_user_confirmation_code', true );
			if( $confirmation_code === $user_conf_code ){
				update_user_meta( $user_id, 'nw_user_confirmed', 1 );
				delete_user_meta( $user_id, 'nw_user_confirmation_code' );

				// user confirmed
				$response =  [
					'code' => 200,
					'message' => __( 'User confirmed.', 'nrua' ),
					'data' => [
						'status' => 200
					]
				];
				return new WP_REST_Response( $response, 200 );
			}else{
				// coed is wrong
				$response =  [
					'code' => 400,
					'message' => __( 'User confirmation code is wrong.', 'nrua' ),
					'data' => [
						'status' => 400
					]
				];
				return new WP_REST_Response( $response, 400 );
			}

		}else{
			$response =  [
				'code' => 200,
				'message' => __( 'User already confirmed.', 'nrua' ),
				'data' => [
					'status' => 200
				]
			];
			return new WP_REST_Response( $response, 200 );
		}
	}
}

// === Resend confirm code ========================================================================
function nrua_users_confirm_resend( WP_REST_Request $request ){

	$parameters = $request->get_json_params();

	$email = sanitize_email( stripslashes( $parameters['email'] ) );
	$password = sanitize_text_field( stripslashes( $parameters['password'] ) );
 
	// auth check
	$auth = wp_authenticate_username_password(NULL, $email, $password);
	if ( is_wp_error($auth) ) {

		$response =  [
			'code' => 401,
			'message' => __('User authentication failed.', 'nrua'),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );
	} else {
		$user_id = username_exists( $email );
		// verify confirmed
		$nw_user_confirmed = get_user_meta( $user_id, 'nw_user_confirmed', true );

		if( $nw_user_confirmed == "0" ){
			// generate code
			$random_code = rand(100000, 999999);
			update_user_meta( $user_id, 'nw_user_confirmation_code', $random_code );
			update_user_meta( $user_id, 'nw_user_confirmed', 0 );

			// send email
			$first_name = get_user_meta( $user_id, 'first_name', true );
			
			$to = $email;
			$subject = 'Athletic Circuit Training - Confirmation';
			$body = '<p>Dear %first_name%,</p>
			<p>Thank you for register to our service!</p>
			<p>To confirm and activate your registration please send following activation code within your
			app:</p>
			<table style="border-collapse: collapse; width: 80%;" border="1">
			<tbody>
			<tr>
			<td style="width: 100%; text-align: center;">%six_digit_code%</td>
			</tr>
			</tbody>
			</table>
			<p>Thank you, your Athletic Circuit Training admins.</p>';

			$body = str_replace( '%first_name%', $first_name, $body );
			$body = str_replace( '%six_digit_code%', $random_code, $body );

			$headers = array( 'Content-Type: text/html; charset=UTF-8');

			$email_send_result = wp_mail( $to, $subject, $body, $headers );

			if( !$email_send_result ){
				$response =  [
					'code' => 500,
					'message' => __( 'Generate confirmation email failed.', 'nrua' ),
					'data' => [
						'status' => 500
					]
				];
				return new WP_REST_Response( $response, 500 );
			} 

			$response =  [
				'code' => 200,
				'id' => $user_id,
				'message' => str_replace( '%email%', $email, __( 'User registration request successful, wait for confirmation code by email.', 'nrua' ) ),
				'data' => [
					'status' => 200
				]
			];
			return new WP_REST_Response( $response, 200 );

		}else{
			$response =  [
				'code' => 400,
				'message' => __( 'User already confirmed.', 'nrua' ),
				'data' => [
					'status' => 400
				]
			];
			return new WP_REST_Response( $response, 400 );
		}

	}
}

// === Password lost ==============================================================================
function nrua_users_password_lost( WP_REST_Request $request ){

	$parameters = $request->get_json_params();

	$email = sanitize_email( stripslashes( $parameters['email'] ) );

	$user_id = username_exists( $email );
	if ( ! $user_id && false == email_exists( $email ) ) {
		$response =  [
			'code' => 404,
			'message' => __( 'User not found', 'nrua' ),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );
	}else{
		// generate code
		$random_code = rand(100000, 999999);
		update_user_meta( $user_id, 'nw_user_password_lost_code', $random_code );
//		update_user_meta( $user_id, 'nw_user_confirmed', 0 ); // 2021 January 11: Disable line as a lost password is no reason to disable account again.

		// send email
		$first_name = get_user_meta( $user_id, 'first_name', true );

		$to = $email;
		$subject = 'Athletic Circuit Training - Lost password';
		$body = '<p>Dear %first_name%,</p>
		<p>To reset your password send following password reset code within your app:</p>
		<table style="border-collapse: collapse; width: 80%;" border="1">
		<tbody>
		<tr>
		<td style="width: 100%; text-align: center;">%six_digit_code%</td>
		</tr>
		</tbody>
		</table>
		<p>Thank you, your Athletic Circuit Training admins.</p>';

		$body = str_replace( '%first_name%', $first_name, $body );
		$body = str_replace( '%six_digit_code%', $random_code, $body );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		
		$email_send_result = wp_mail( $to, $subject, $body, $headers );

		if( !$email_send_result ){
			$response =  [
				'code' => 500,
				'message' => __( 'Generate confirmation email failed.', 'nrua' ),
				'data' => [
					'status' => 500
				]
			];
			return new WP_REST_Response( $response, 500 );
		} 

		$response =  [
			'code' => 200,
			'id' => $user_id,
			'message' => __( 'Password reset request successful, wait for reset code by email.', 'nrua' ),
			'data' => [
				'status' => 200
			]
		];
		return new WP_REST_Response( $response, 200 );
	}
}

// === Password change ============================================================================
function nrua_users_password_change( WP_REST_Request $request ){

	$parameters = $request->get_json_params();

	$email = sanitize_email( stripslashes( $parameters['email'] ) );
	$password = sanitize_text_field( stripslashes( $parameters['password'] ) );
	$password_lost_code = sanitize_text_field( stripslashes( $parameters['password_lost_code'] ) );

	// check passowrd
	$pass_errors = nrua_check_password( $password );
	if( count($pass_errors) > 0 ){
		$response = [
			'code' => 400,
			'message' => __( 'Please, use more complex password.', 'nrua' ),
			'data' => [
				'status' => 400,
				'errors' => $pass_errors
			]
		];
		return new WP_REST_Response( $response, 400 );
	}

	// verify email
	if( !is_email( $email ) ){
		$response = [
			'code' => 400,
			'message' => __( 'Please, use correct email address', 'nrua' ),
			'data' => [
				'status' => 400,
			]
		];
		return new WP_REST_Response( $response, 400 );
	}

	$user_id = username_exists( $email );
	if ( ! $user_id && false == email_exists( $email ) ) {
		$response =  [
			'code' => 404,
			'message' => __('User not found', 'nrua'),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );
	}else{
		$nw_user_password_lost_code = get_user_meta( $user_id, 'nw_user_password_lost_code', true );
	 
		if( $password_lost_code === $nw_user_password_lost_code &&  $nw_user_password_lost_code > 0 ){
			delete_user_meta( $user_id, 'nw_user_password_lost_code');

			$result = wp_set_password( $password, $user_id );
			if( is_wp_error($result) ){
				$response =  [
					'code' => 500,
					'message' => __('Process user password change failed.', 'nrua'),
					'data' => [
						'status' => 500
					]
				];
				return new WP_REST_Response( $response, 500 );
			}else{
				$response =  [
					'code' => 200,
					'message' => __( 'User password changed successful.', 'nrua' ),
					'data' => [
						'status' => 200
					]
				];
				return new WP_REST_Response( $response, 200 );
			}
		}else{
			$response =  [
				'code' => 400,
				'message' => __( 'User password code is wrong.', 'nrua' ),
				'data' => [
					'status' => 400
				]
			];
			return new WP_REST_Response( $response, 400 );
		}
	}
}

// = Get last login
function nrua_users_last_login( WP_REST_Request $request ){

	// = Validate user credentials
	$user 		= wp_get_current_user();
	$user_id 	= $user->ID;

	if ( $user_id < 1 )
	{
			$response =  [
				'code' => 400,
				'message' => __( 'No running user session!', 'nrua' ),
				'data' => [
					'status' => 400
				]
			];
			return new WP_REST_Response( $response, 400 );		
	}
	
	$last_login = get_user_meta( $user_id, 'last_login', true );
    $diff_login_date = human_time_diff($last_login);
	
	//$str_login_date = get_date_from_gmt( date( 'Y-m-d H:i:s', $last_login ), 'F j, Y H:i:s' );
	$str_login_date = get_date_from_gmt( date( 'Y-m-d H:i:s', $last_login ), 'd. F Y, H:i:s' );
	
	$response =  [
		'code' => 200,
		'message' => __( 'Last login was ' .$str_login_date, 'nrua' ),
		'last_login_unix' => __( $last_login, 'nrua' ),
		'data' => [
			'status' => 200
		]
	];
	return new WP_REST_Response( $response, 200 );
}

// === Delete user avatar =========================================================================
function nrua_del_users_profile_avatar( WP_REST_Request $request ) 
{
	// = Validate user credentials
	$user 		= wp_get_current_user();
	$user_ID 	= $user->ID;
	
	if ( $user_ID < 1 )
	{
		$response = [
			'code' => 403,
			'message' => __( 'No userID available!', 'nrua' ),
			'data' => [
				'status' => 403
			]
		];
		return new WP_REST_Response( $response, 403 );
	}
	
	global $wpdb;
	$table_name = $wpdb->prefix .'nw_nrua_user_avatar';
	$result = $wpdb->delete( $table_name, array( 'wp_user_id' => $user_ID ) );
	
	if ( $result === false)
	{
			$response = [
			'code' => 404,
			'message' => __( $wpdb->last_error, 'nrua' ),
			'data' => [
				'status' => 404
			]
		];
		return new WP_REST_Response( $response, 404 );	
		
	} else {

		$response = [
			'code' => 200,
			'message' => __( 'Removed user avatar successfully', 'nrua' ),
			'result' => __( $result ),
			'data' => [
				'status' => 200
			]
		];
		return new WP_REST_Response( $response, 200 );	
		
	}

}

// === Get user avatar ==========================================================================
function nrua_get_users_profile_avatar( WP_REST_Request $request ) 
{
	// = Validate user credentials
	$user 		= wp_get_current_user();
	$user_ID 	= $user->ID;
	
	if ( $user_ID < 1 )
	{
		$response = [
			'code' => 403,
			'message' => __( 'No userID available!', 'nrua' ),
			'data' => [
				'status' => 403
			]
		];
		return new WP_REST_Response( $response, 403 );
	}

	// = Check for existing avatar in db
	global $wpdb;
	$table_name = $wpdb->prefix .'nw_nrua_user_avatar';
	$dbrow 		= $wpdb->get_row( "SELECT blob_base64, blob_size, mime_type, file_name, file_size FROM $table_name WHERE ( wp_user_id = $user_ID )" );
	
	if ( $dbrow !== null ) 
	{
		$blob_size = intval( $dbrow->blob_size );
		$file_size = intval( $dbrow->file_size );
		
		$response = [
			'code' => 200,
			'message' => __( 'Loaded user avatar successfully', 'nrua' ),
			'blob_base64' => __( $dbrow->blob_base64 ),
			'blob_size' => __( $blob_size ),
			'mime_type' => __( $dbrow->mime_type ),
			'file_name' => __( $dbrow->file_name ),
			'file_size' => __( $file_size ),
			'data' => [
				'status' => 200
			]
		];
		return new WP_REST_Response( $response, 200 );
		
	} else {
		$response = [
			'code' => 404,
			'message' => __( 'No user avatar found', 'nrua' ),
			'data' => [
				'status' => 404
			]
		];
		return new WP_REST_Response( $response, 404 );		

	}
	
}

// === Store user avatar ==========================================================================
function nrua_post_users_profile_avatar( WP_REST_Request $request ) 
{
	// = Validate user credentials
	$user 		= wp_get_current_user();
	$user_ID 	= $user->ID;
	
	if ( $user_ID < 1 )
	{
		$response = [
			'code' => 403,
			'message' => __( 'No userID available!', 'nrua' ),
			'data' => [
				'status' => 403
			]
		];
		return new WP_REST_Response( $response, 403 );
	}
	
	// = Validate request
	$parameters 	= $request->get_json_params();
	$file_name 		= sanitize_text_field( stripslashes( $parameters['file_name'] ) );
	$data_base64 	= sanitize_text_field( stripslashes( $parameters['data_base64'] ) );
	
	// = Validate base64 string size
	$blob_size = strlen( $data_base64 );
	if ( $blob_size < 1 )
	{
		$response = [
			'code' => 400,
			'message' => __( 'Invalid data_base64 value!', 'nrua' ),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );		
	}
	
	if ( !nrua_is_base64( $data_base64 ) )
	{
		$response = [
			'code' => 400,
			'message' => __( 'Invalid data_base64 value!', 'nrua' ),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );		
	}
	
	// = Validate file size (base64 is usually 33% bigger than file size!)
	$file_size = strlen( base64_decode( $data_base64 ) );
	if ( ( $file_size > 257000 ) || ( strlen( $data_base64 ) > 341000 ) )
	{
		$response = [
			'code' => 400,
			'message' => __( 'Invalid data_base64 file_size! (Only 256KB allowed)', 'nrua' ),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );		
	}	
	
	// = Validate file name max length
	if ( strlen( $file_name ) > 255 )
	{
		$response = [
			'code' => 400,
			'message' => __( 'Invalid filename! (Only 255 chars allowed)', 'nrua' ),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );		
	}
	
	// = Validate file mime_type
	$imgdata = base64_decode( $data_base64 );
	$f = finfo_open();
	$mime_type = finfo_buffer( $f, $imgdata, FILEINFO_MIME_TYPE );
	
	if ( ( $mime_type !== 'image/png' ) && ( $mime_type !== 'image/jpeg' )  )
	{
		$response = [
			'code' => 400,
			'message' => __( 'Invalid data_base64 mime_type! (Only PNG or JPG allowed)', 'nrua' ),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );		
	}	
	
	// = Validate file name min length
	if ( strlen( $file_name ) < 3)
	{
		if ( $mime_type == 'image/png' ) 
		{ 
			$file_name = 'avatar.png'; 
		}
		
		if ( $mime_type == 'image/jpeg' ) 
		{ 
			$file_name = 'avatar.jpeg'; 
		}
	}
	
	// = Get MD5 hash
	$hash_md5 	= md5( $data_base64 );
	$hash_md5	= strtoupper( $hash_md5 );

	// = Check if update in DB is needed
	global $wpdb;
	$table_name = $wpdb->prefix .'nw_nrua_user_avatar';
	$dbrow 		= $wpdb->get_row( "SELECT id, hash_md5 FROM $table_name WHERE ( wp_user_id = $user_ID )" );
	
	if ( $dbrow == null )
	{
		// = Insert new row
		$wpdb->replace( $table_name, array( 'wp_user_id' => $user_ID, 'blob_base64' => $data_base64, 'blob_size' => $blob_size, 'mime_type' => $mime_type, 'file_name' => $file_name, 'file_size' => $file_size, 'hash_md5' => $hash_md5 ) );

		if( $wpdb->last_error !== '' )
		{
			$response = [
				'code' => 400,
				'message' => __( $wpdb->print_error(), 'nrua' ),
				'data' => [
					'status' => 400
				]
			];
			return new WP_REST_Response( $response, 400 );		
		}
			
	} else {

		// = Check MD5
		if ( $hash_md5 !== $dbrow->hash_md5 )
		{
			// = Update
			$dbRowID = $dbrow->id;
			$wpdb->update($table_name, array( 'blob_base64' => $data_base64, 'blob_size' => $blob_size, 'mime_type' => $mime_type, 'file_name' => $file_name, 'file_size' => $file_size, 'hash_md5' => $hash_md5 ), array( 'id' => $dbRowID ) );
			
			if($wpdb->last_error !== '')
			{
				$response = [
					'code' => 400,
					'message' => __( $wpdb->print_error(), 'nrua' ),
					'data' => [
						'status' => 400
					]
				];
				return new WP_REST_Response( $response, 400 );		
			}
		
		}
			
	}
	
	$blob_size = intval( $blob_size );
	$file_size = intval( $file_size );
		
	$response =  [
		'code' 		=> 200,
		'message' 	=> __( 'Stored user avatar successfully', 'nrua' ),
		'hash_md5' 	=> __( $hash_md5 ),
		'blob_size' => __( $blob_size ),
		'mime_type' => __( $mime_type ),
		'file_name' => __( $file_name ),
		'file_size' => __( $file_size ),
		'data' => [
			'status' => 200
		]
	];
	return new WP_REST_Response( $response, 200 );	
	
}

// === Store user data ============================================================================
function nrua_post_users_profile_data( WP_REST_Request $request ) 
{

	// = Validate user credentials
	$user 		= wp_get_current_user();
	$user_ID 	= $user->ID;
	
	if ( $user_ID < 1 )
	{
		$response = [
			'code' => 403,
			'message' => __( 'No userID available!', 'nrua' ),
			'data' => [
				'status' => 403
			]
		];
		return new WP_REST_Response( $response, 403 );
	}
	
	// = Validate request
	$parameters 	= $request->get_json_params();
	$first_name 	= sanitize_text_field( stripslashes( $parameters['user_firstname'] ) );
	$last_name 		= sanitize_text_field( stripslashes( $parameters['user_lastname'] ) );
	$gender 		= sanitize_text_field( stripslashes( $parameters['user_gender'] ) );
	$gender 		= intval( $gender );
	$birthday 		= sanitize_text_field( stripslashes( $parameters['user_birthday'] ) );
	$phone_mobile	= sanitize_text_field( stripslashes( $parameters['user_phone_mobile'] ) );
	
	// = Validate first & lastname	
	if( 
		strlen( $first_name ) 	> 1 && strlen( $first_name ) 	< 255   &&
		strlen( $last_name ) 	> 1 && strlen( $last_name ) 	< 255   ){} else {
		
		$response =  [
			'code' => 406,
			'message' => __( 'User first name and last name should be from 2 to 255 chars', 'nrua' ),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response( $response, 400 );
	}

	// = Validate gender
	if ( ( $gender < 0 ) || ( $gender > 3 ) )
	{
		$gender = null;
	}
	
	// = Validate birthday
	if ( nrua_validateDate( $birthday .' 00:00:00') )
	{
		// = Check if birthday is in the future or before 1900?
		$past 		= DateTime::createFromFormat( 'Y-m-d', "1900-01-01" );
		$now       	= new DateTime();
		$user_date 	= DateTime::createFromFormat( 'Y-m-d', $birthday );
		if ( ( $user_date >= $now ) || ( $past >= $user_date ) )
		{
			$birthday = null;
		}
	
	} else {
		$birthday = null;	
	}

	// = Validate phone_mobile
	$phone_mobile = nrua_sanitize_phone_number( $phone_mobile );

	// = Update meta
	update_user_meta( $user_ID, 'first_name', 	$first_name );
	update_user_meta( $user_ID, 'last_name', 	$last_name );
	update_user_meta( $user_ID, 'gender', 		$gender );	
	update_user_meta( $user_ID, 'birthday', 	$birthday );
	update_user_meta( $user_ID, 'phone_mobile', $phone_mobile );

	// = Send response
	$response = [
		'code' 				=> 200,
		'message' 			=> __( 'Stored user data successfully', 'nrua' ),
		'user_firstname' 	=> __( $first_name, 'nrua' ),
		'user_lastname' 	=> __( $last_name, 'nrua' ),
		'user_gender' 		=> __( $gender, 'nrua' ),
		'user_birthday' 	=> __( $birthday, 'nrua' ),
		'user_phone_mobile' => __( $phone_mobile, 'nrua' ),
		'data' => [
			'status' => 200
		]
	];
	return new WP_REST_Response( $response, 200 );	

}

function nrua_get_users_profile_data( WP_REST_Request $request ) 
{
	
	// = Validate user credentials
	$user 		= wp_get_current_user();
	$user_ID 	= $user->ID;
	
	if ( $user_ID < 1 )
	{
		$response = [
			'code' => 403,
			'message' => __( 'No userID available!', 'nrua' ),
			'data' => [
				'status' => 403
			]
		];
		return new WP_REST_Response( $response, 403 );
	}
	
	$first_name 	= get_user_meta( $user_ID, 'first_name', true );
	$last_name 		= get_user_meta( $user_ID, 'last_name', true );
	$gender 		= intval( get_user_meta( $user_ID, 'gender', true ) );
	$birthday 		= get_user_meta( $user_ID, 'birthday', true );
	$phone_mobile	= get_user_meta( $user_ID, 'phone_mobile', true );
	
	$response = [
		'code' => 400,
		'message' => __( 'Loaded user data successfully', 'nrua' ),
		'user_firstname' 	=> __( $first_name, 'nrua' ),
		'user_lastname' 	=> __( $last_name, 'nrua' ),
		'user_gender' 		=> __( $gender, 'nrua' ),
		'user_birthday' 	=> __( $birthday, 'nrua' ),
		'user_phone_mobile' => __( $phone_mobile, 'nrua' ),
		'data' => [
			'status' => 400
		]
	];
	return new WP_REST_Response( $response, 400 );	
		
}

?>