<?php 

/*
add_filter( 'get_avatar' , 'my_custom_avatar' , 1 , 5 );
 
function my_custom_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
    
	$user = false;
 
    if ( is_numeric( $id_or_email ) ) {
 
        $id = (int) $id_or_email;
        $user = get_user_by( 'id' , $id );
 
    } elseif ( is_object( $id_or_email ) ) {
 
        if ( ! empty( $id_or_email->user_id ) ) {
            $id = (int) $id_or_email->user_id;
            $user = get_user_by( 'id' , $id );
        }
 
    } else {
        $user = get_user_by( 'email', $id_or_email );   
    }
 
    if ( $user && is_object( $user ) ) {
 
        if ( $user->data->ID == '1' ) {
            $avatar = 'https://act.n-iceware.net/blub2.png';
            $avatar = "<img alt='{$alt}' src='{$avatar}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
        }
 
    }
 
    return $avatar;
}
*/

// ================================================================================
add_filter( 'bulk_actions-users', 'nrua_user_actions' );
 
function nrua_user_actions( $bulk_array ) {
	$locale = 'nrua';
	$bulk_array['make_activate'] = __('Activate', $locale);
	$bulk_array['make_deactivate'] = __('Deactivate', $locale);
	return $bulk_array;
 
}

add_filter( 'handle_bulk_actions-users', 'nrua_bulk_action_handler', 10, 3 );
function nrua_bulk_action_handler( $redirect, $doaction, $object_ids ) {

	$redirect = remove_query_arg( array( 'make_activate_done', 'make_activate_done' ), $redirect );

	if ( $doaction == 'make_activate' ) {
 
		foreach ( $object_ids as $post_id ) {
			update_user_meta( $post_id, 'nw_user_confirmed', 1 );
		}

		$redirect = add_query_arg(
			'make_activate_done', 
			count( $object_ids ), 
		$redirect );
 
	}

	if ( $doaction == 'make_deactivate' ) {
		foreach ( $object_ids as $post_id ) {
			update_user_meta( $post_id, 'nw_user_confirmed', 0 );
		}
		$redirect = add_query_arg( 'make_deactivate_done', count( $object_ids ), $redirect );
	}
 
	return $redirect;
 
}

// custom column
add_filter( 'manage_users_columns', 'nrua_user_columns' ) ;
function nrua_user_columns( $columns ) {
	$locale = 'nrua';
	$columns['activation'] = __( 'Activation', $locale );
	return $columns;
}

add_action( 'manage_users_custom_column', 'nrua_posts_custom_column', 10, 3 );

function nrua_posts_custom_column( $val, $column_name, $user_id ) {
	global $post;
	switch( $column_name ) {
 
		case 'activation' :
			return '<input type="checkbox" class="activation_checkbox" id="'.$user_id.'" data-id="'.$user_id.'" value="on" '.( get_user_meta( $user_id, 'nw_user_confirmed', true ) == '1' ? ' checked ' : '' ).' />';
		break;
		default :
		break;
	}
}
add_filter('manage_users_sortable_columns', 'sort_mishas_user_columns');
function sort_mishas_user_columns ($columns){
	$columns["activation"] = "activation";
	return $columns;
}


add_action("pre_get_users", function ($WP_User_Query) {
	global $wpdb;
    if (    isset($WP_User_Query->query_vars["orderby"])
        &&  ("activation" === $WP_User_Query->query_vars["orderby"])
    ) {
		$all_users = $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->prefix}usermeta");
		$all_existed_users = $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->prefix}usermeta WHERE meta_key = 'nw_user_confirmed'");
		$diff = array_diff( $all_users,  $all_existed_users);
		foreach( $diff as $s_user_id ){
			update_user_meta( $s_user_id, 'nw_user_confirmed', '' );
		}
		// patch things
        $WP_User_Query->query_vars["meta_key"] = "nw_user_confirmed";
        $WP_User_Query->query_vars["orderby"] = "meta_value_num";
    }

}, 10, 1);

// === Store last user login for every login ======================================================
function user_last_login( $user_login, $user ) {
    update_user_meta( $user->ID, 'last_login', time() );
}
add_action( 'wp_login', 'user_last_login', 10, 2 );


?>