<?php 

 function nrua_check_password($pwd) {
	$errors = [];
	
	if (strlen($pwd) < 8) {
	$errors[] = "Password too short!";
	}
	
	if (!preg_match("#[0-9]+#", $pwd)) {
	$errors[] = "Password must include at least one number!";
	}
	
	if (!preg_match("#[a-zA-Z]+#", $pwd)) {
	$errors[] = "Password must include at least one letter!";
	}
	
	return $errors;
}

// === WriteLog ==================================================================================
function nrua_writeLog($Module,$LogString)
{
$datum = date("d.m.Y");
$uhrzeit = date("H:i");

	$fp = fopen($_SERVER['DOCUMENT_ROOT']."/n-iceware_nrua.log", 'a+');
	
	if ( is_array( $LogString ) || is_object( $LogString ) ) {
		fwrite( $fp, $datum ." " .$uhrzeit ." - " .$Module .":" .print_r( $LogString, true ) .PHP_EOL );
	} else {
		fwrite( $fp, $datum ." " .$uhrzeit ." - " .$Module .":" .$LogString .PHP_EOL );
	}		
	
	fwrite($fp, $datum ." " .$uhrzeit ." - " .$Module .":" .$LogString .PHP_EOL);
	fclose($fp);	
}

?>