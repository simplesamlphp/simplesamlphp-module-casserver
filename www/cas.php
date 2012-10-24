<?php

/*
 * Frontend for login.php, validate.php and serviceValidate.php. It allows them to be called
 * as cas.php/login, cas.php/validate and cas.php/serviceValidate and is meant for clients
 * like phpCAS which expects one configured prefix which it appends login, validate and 
 * serviceValidate to.
 * 
 * ServiceTickets (ST) now have a 5 secs ttl.
 *
 */
 
 
$validFunctions = array(
	'login' => 'login',
	'validate' => 'validate',
	'serviceValidate' => 'serviceValidate',
        'logout' => 'serviceLogout',
);

$function = substr($_SERVER['PATH_INFO'], 1);

if (!isset($validFunctions[$function])) {
	throw new SimpleSAML_Error_NotFound('Not a valid function for cas.php.');
}

include($validFunctions[$function].".php");

?>
