<?php 

// === Sanitize phone number ======================================================================
function nrua_sanitize_phone_number( $phone_mobile )
{
	// = Remove all non int chars
	$phone_mobile = filter_var( $phone_mobile, FILTER_SANITIZE_NUMBER_INT );
	$phone_mobile = str_replace("+", "", $phone_mobile);
	$phone_mobile = str_replace("-", "", $phone_mobile);
	
	// = Add the international char
	$phone_mobile = '+' .$phone_mobile;
	
	// = Check lenght
	if ( ( strlen( $phone_mobile ) < 10 ) || ( strlen( $phone_mobile ) > 14 ) ) 
	{
		$phone_mobile = null;
	}
	
	return $phone_mobile;
}

// === Check if a date is a date ==================================================================
function nrua_validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

// === Check password complexity ==================================================================
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

// === Check for base64 ===========================================================================
function nrua_is_base64( $str )
{
	if( $str === base64_encode( base64_decode( $str ) ) )
	{
		return true;
	}
	
	return false;
}

// === WriteLog ===================================================================================
function nrua_writeLog($Module, $LogString)
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