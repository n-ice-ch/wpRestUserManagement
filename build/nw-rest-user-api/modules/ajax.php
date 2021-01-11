<?php 

add_action('wp_ajax_make_active_user', 'nrua_make_active_user');
add_action('wp_ajax_nopriv_make_active_user', 'nrua_make_active_user');

function nrua_make_active_user(){
	global $current_user, $wpdb;
	if( check_ajax_referer( 'ajax_call_nonce', 'security') ){

		if( $_POST['is_checked'] == 'true' ){
			update_user_meta( $_POST['user_id'], 'nw_user_confirmed', 1 );
		}
		if( $_POST['is_checked'] == 'false' ){
			update_user_meta( $_POST['user_id'], 'nw_user_confirmed', 0 );
		}
		echo json_encode( [ 'result' => 'success' ] );
	}
	die();
}

 
?>