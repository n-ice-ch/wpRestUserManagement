<?

global $nw_db_version;
$nrua_db_version = '1.1';

function nrua_db_check() 
{
	nrua_db_install();
	
/*	
    if (get_site_option('nrua_db_version') != $nrua_db_version) 
	{
		nrua_db_install();
        nrua_db_update();
    };
*/
}

function nrua_db_update() 
{
/*
	writeLog('nw_db_update','process!');
	
	$table_name = $wpdb->prefix .'nw_sbo_author ';
	$sql = "CREATE TABLE $table_name
	(
		id INT NOT NULL AUTO_INCREMENT,
		owner_id INT NOT NULL,
		firstname VARCHAR(100),
		lastname VARCHAR(100) NOT NULL,
		changed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		FOREIGN KEY (owner_id) REFERENCES wp_users(ID)
	) 
	{$charset_collate};";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	
//	update_option( "sbo_db_version", $sbo_db_version );
*/
}
	
function nrua_db_install() 
{

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	
	global $wpdb;
	
	$table_name = $wpdb->prefix .'nw_nrua_user_avatar ';
	$sql = "CREATE TABLE $table_name
	(
		id INT NOT NULL AUTO_INCREMENT,
		wp_user_id INT NOT NULL DEFAULT '0',
		blob_base64 MEDIUMBLOB,
		blob_size INT DEFAULT '0',
		mime_type VARCHAR(255),
		file_name VARCHAR(255),
		file_size INT DEFAULT '0',
		hash_md5 VARCHAR(32),
		changed TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		FOREIGN KEY (wp_user_id) REFERENCES wp_users(ID)
	) 
	{$charset_collate};";
	dbDelta( $sql );
	
	// = Set current DB scheme version
	add_option( 'nrua_db_version', $nrua_db_version );

}

function nrua_db_install_data()
{
	global $wpdb;

}