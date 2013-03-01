<?php
/*
* Frontend for login.php, validate.php, serviceValidate.php, logout.php, proxy and proxyValidate. It allows them to be
* called as cas.php/login, cas.php/validate, cas.php/serviceValidate, cas.php/logout, cas.php/proxy and cas.php/proxyValidate
* and is meant for clients like phpCAS which expects one configured prefix which it appends login, validate, serviceValidate and logout to.
*
*/

$validFunctions = array(
    'login' => 'login',
    'validate' => 'validate',
    'serviceValidate' => 'serviceValidate',
    'logout' => 'logout',
    'proxy' => 'proxy',
    'proxyValidate' => 'serviceValidate',
);

$function = substr($_SERVER['PATH_INFO'], 1);

if (!isset($validFunctions[$function])) {
    throw new SimpleSAML_Error_NotFound('Not a valid function for cas.php.');
}

include(dirname(__FILE__) . '/' . $validFunctions[$function] . '.php');

?>
