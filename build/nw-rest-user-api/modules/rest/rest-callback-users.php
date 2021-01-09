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
			'message' => __('Username or password invalid.', 'nrua'),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response($response, 400);
	} 
	
	// = Validate password value
	$pass_errors = nrua_check_password( $password );
	
	if( count($pass_errors) > 0 ){
		$response = [
			'code' => 400,
			'message' => __('Please, use more complex password.', 'nrua'),
			'data' => [
				'status' => 400,
				'errors' => $pass_errors
				]
			];
		return new WP_REST_Response($response, 400);
	}
			
	// = Validate email value
	if( !is_email( $email ) ){
		$response = [
			'code' => 400,
			'message' => __('Please, use valid email address', 'nrua'),
			'data' => [
				'status' => 400,
			]
		];
		return new WP_REST_Response($response, 400);
	}

	// = Try to login
	$creds = array();
	$creds['user_login'] 	= $email;
	$creds['user_password'] = $password;
	$creds['remember'] = true;

	$user = wp_signon($creds, false );
	
	if ( is_wp_error($user) ) 
	{
		$response = [
			'code' => 403,
			'message' => __( $user->get_error_code(), 'nrua'),
			'data' => [
				'status' => 403,
			]
		];
		return new WP_REST_Response($response, 403);
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
		return new WP_REST_Response($response, 401);
	}		

	// = Procedure to login a user object
	wp_clear_auth_cookie();
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID );
	do_action('my_update_cookie');

	$first_name = get_user_meta( $user->ID, 'first_name', true);
	$last_name = get_user_meta( $user->ID, 'last_name', true);

	// = Create nonce to be used for further REST calls
	$nonce = wp_create_nonce( 'wp_rest' );
	$siteHash = constant( 'COOKIEHASH' );
	
	$cookieData = wp_parse_auth_cookie( '', 'logged_in' );
	
	$username 	= $cookieData['username'];
	$expiration = $cookieData['expiration'];
	$token 		= $cookieData['token'];
	$hmac 		= $cookieData['hmac'];
	
	// = Create cookie to be used for further REST calls
	$cookie = 'wordpress_logged_in_' .$siteHash .'=' .urlencode( $username .'|' .$expiration .'|' .$token .'|' .$hmac );
		
	$response = [
		'code' => 200,
		'message' => __( 'Login successfully', 'nrua'),
		'username' => $username,
		'firstname' => $first_name,
		'lastname' => $last_name,
		'nonce' => $nonce,
		'cookie' => $cookie,
		'data' => [
			'status' => 200
		]
	];
	return new WP_REST_Response($response, 200);
		
}

// === Logout from further REST calls =============================================================
function nrua_get_user_logout($ressource) 
{
	// = Validate user credentials
	$user 		= wp_get_current_user();
	$user_ID 	= $user->ID;
	
	if ( $user_ID < 1 )
	{
		
		$response = [
			'code' => 400,
			'message' => __( 'No userID available!', 'nrua'),
			'nonce' => 0,
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response($response, 400);	
	
	}


	$user = wp_logout();
	
	if ( is_wp_error($user) ) 
	{
		
		$response = [
			'code' => 400,
			'message' => __( $user->get_error_message(), 'nrua'),
			'nonce' => 0,
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response($response, 400);		
	
	} else {
		
		$response = [
			'code' => 200,
			'message' => __( 'Successfully logged out.', 'nrua'),
			'nonce' => NULL,
			'user_ID' => $user_ID,
			'data' => [
				'status' => 200
			]
		];
		return new WP_REST_Response($response, 200);		
		
	}	
}

// === Register user account ======================================================================
function nrua_users_register( WP_REST_Request $request ){

	$settings = get_option('nrua_options');

	if( $settings['disable_registrations'] == 'yes' ){
		$response = [
			'code' => 403,
			'message' => __('Registrations currently disabled (Maintenance).', 'nrua'),
			'data' => [
				'status' => 400,
			]
		];
		return new WP_REST_Response($response, 400);
	}

	$parameters = $request->get_json_params();
 

	$first_name 	= sanitize_text_field( stripslashes( $parameters['user_firstname'] ) );
	$last_name 		= sanitize_text_field( stripslashes( $parameters['user_lastname'] ) );
	$email 			= sanitize_email( stripslashes( $parameters['email'] ) );
	$password 		= sanitize_text_field( stripslashes( $parameters['password'] ) );
	$acceptTerms 	=  $parameters['acceptTerms']   ;
 

	if( $acceptTerms === true  ){
	 
		if( 
			strlen( $first_name ) 	> 1 && strlen( $first_name ) 	< 255   &&
			strlen( $last_name ) 	> 1 && strlen( $last_name ) 	< 255   
			
			){

			// check passowrd
			$pass_errors = nrua_check_password( $password );
			if( count($pass_errors) > 0 ){
				$response = [
					'code' => 400,
					'message' => __('Please, use more complex password.', 'nrua'),
					'data' => [
						'status' => 400,
						'errors' => $pass_errors
					]
				];
				return new WP_REST_Response($response, 400);
			}

			// verify email
			if( !is_email( $email ) ){
				$response = [
					'code' => 400,
					'message' => __('Please, use valid email address', 'nrua'),
					'data' => [
						'status' => 400,
					]
				];
				return new WP_REST_Response($response, 400);
			}

			//creation of user
			$user_id = username_exists( $email );
			if ( ! $user_id && false == email_exists( $email ) ) {
				$user_id = wp_create_user( $email, $password, $email );

				// update meta
				update_user_meta( $user_id, 'first_name', $first_name );
				update_user_meta( $user_id, 'last_name', $last_name );

				// generate code
				$random_code = rand(100000, 999999);
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
			 
				$headers = array('Content-Type: text/html; charset=UTF-8');
				
				$email_send_result = wp_mail( $to, $subject, $body, $headers );

				if( !$email_send_result ){
					$response = [
						'code' => 500,
						'message' => __('Generate confirmation email failed.', 'nrua'),
						'data' => [
							'status' => 500
						]
					];
					return new WP_REST_Response($response, 500);
				} 

				$response =  [
					'code' => 200,
					'id' => $user_id,
					'message' => __('User registration request successful, wait for confirmation code by email.', 'nrua'),
					'data' => [
						'status' => 200
					]
				];
				return new WP_REST_Response($response, 200);
			}else{
				
				// = Account seems to exist already.
				// = Try to login
				$auth = wp_authenticate_username_password(NULL, $email, $password);
				if ( !is_wp_error($auth) ) {
				
					$user_id = username_exists( $email );

					// = Check if confirmed
					$nw_user_confirmed = get_user_meta( $user_id, 'nw_user_confirmed', true );		
//					nrua_writeLog( 'nrua_users_register','nw_user_confirmed=' .$nw_user_confirmed );
					
					if( $nw_user_confirmed == '0' ){

						$response = [
							'code' => 401,
							'message' => __( 'Missing user confirmation.', 'nrua'),
							'data' => [
								'status' => 401,
							]
						];
						return new WP_REST_Response($response, 401);
					}

				}

				// = Seems to be a double registration?
				$response =  [
					'code' => 406,
					'message' => __('Username already exists, please enter another username', 'nrua'),
					'data' => [
						'status' => 400
					]
				];
				return new WP_REST_Response($response, 400);
			}
	
		}else{
			$response =  [
				'code' => 406,
				'message' => __('User first name and last name should be from 2 to 255 chars', 'nrua'),
				'data' => [
					'status' => 400
				]
			];
			return new WP_REST_Response($response, 400);
		}
	}else{
		// 
		$response =  [
			'code' => 406,
			'message' => __('User has not accepted general terms.', 'nrua'),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response($response, 400);
	}

}

// === Confirm user account =======================================================================
function nrua_users_confirm( WP_REST_Request $request ){

	$parameters = $request->get_json_params();

	$email = sanitize_email( stripslashes( $parameters['email'] ) );
	$password = sanitize_text_field( stripslashes( $parameters['password'] ) );
	$confirmation_code = sanitize_text_field( stripslashes( $parameters['confirmation_code'] ) );

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
		return new WP_REST_Response($response, 400);
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
					'message' => __('User confirmed.', 'nrua'),
					'data' => [
						'status' => 200
					]
				];
				return new WP_REST_Response($response, 200);
			}else{
				// coed is wrong
				$response =  [
					'code' => 400,
					'message' => __('User confirmation code is wrong.', 'nrua'),
					'data' => [
						'status' => 400
					]
				];
				return new WP_REST_Response($response, 400);
			}

		}else{
			$response =  [
				'code' => 200,
				'message' => __('User already confirmed.', 'nrua'),
				'data' => [
					'status' => 200
				]
			];
			return new WP_REST_Response($response, 200);
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
		return new WP_REST_Response($response, 400);
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
			$first_name = get_user_meta($user_id, 'first_name', true);
			
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

			$body = str_replace('%first_name%', $first_name, $body);
			$body = str_replace('%six_digit_code%', $random_code, $body);

			$headers = array('Content-Type: text/html; charset=UTF-8');

			$email_send_result = wp_mail( $to, $subject, $body, $headers );

			if( !$email_send_result ){
				$response =  [
					'code' => 500,
					'message' => __('Generate confirmation email failed.', 'nrua'),
					'data' => [
						'status' => 500
					]
				];
				return new WP_REST_Response($response, 500);
			} 

			$response =  [
				'code' => 200,
				'id' => $user_id,
				'message' => str_replace( '%email%', $email, __('User registration request successful, wait for confirmation code by email.', 'nrua') ),
				'data' => [
					'status' => 200
				]
			];
			return new WP_REST_Response($response, 200);

		}else{
			$response =  [
				'code' => 400,
				'message' => __('User already confirmed.', 'nrua'),
				'data' => [
					'status' => 400
				]
			];
			return new WP_REST_Response($response, 400);
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
			'message' => __('User not found', 'nrua'),
			'data' => [
				'status' => 400
			]
		];
		return new WP_REST_Response($response, 400);
	}else{
		// generate code
		$random_code = rand(100000, 999999);
		update_user_meta( $user_id, 'nw_user_password_lost_code', $random_code );
		update_user_meta( $user_id, 'nw_user_confirmed', 0 );

		// send email
		$first_name = get_user_meta($user_id, 'first_name', true);

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

		$body = str_replace('%first_name%', $first_name, $body);
		$body = str_replace('%six_digit_code%', $random_code, $body);

		$headers = array('Content-Type: text/html; charset=UTF-8');
		
		$email_send_result = wp_mail( $to, $subject, $body, $headers );

		if( !$email_send_result ){
			$response =  [
				'code' => 500,
				'message' => __('Generate confirmation email failed.', 'nrua'),
				'data' => [
					'status' => 500
				]
			];
			return new WP_REST_Response($response, 500);
		} 

		$response =  [
			'code' => 200,
			'id' => $user_id,
			'message' => __('Password reset request successful, wait for reset code by email.', 'nrua'),
			'data' => [
				'status' => 200
			]
		];
		return new WP_REST_Response($response, 200);
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
			'message' => __('Please, use more complex password.', 'nrua'),
			'data' => [
				'status' => 400,
				'errors' => $pass_errors
			]
		];
		return new WP_REST_Response($response, 400);
	}

	// verify email
	if( !is_email( $email ) ){
		$response = [
			'code' => 400,
			'message' => __('Please, use correct email address', 'nrua'),
			'data' => [
				'status' => 400,
			]
		];
		return new WP_REST_Response($response, 400);
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
		return new WP_REST_Response($response, 400);
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
				return new WP_REST_Response($response, 500);
			}else{
				$response =  [
					'code' => 200,
					'message' => __('User password changed successful.', 'nrua'),
					'data' => [
						'status' => 200
					]
				];
				return new WP_REST_Response($response, 200);
			}
		}else{
			$response =  [
				'code' => 400,
				'message' => __('User password code is wrong.', 'nrua'),
				'data' => [
					'status' => 400
				]
			];
			return new WP_REST_Response($response, 400);
		}
	}
}

?>