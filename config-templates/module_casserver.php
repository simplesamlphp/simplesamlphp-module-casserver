<?php
/* 
 * Configuration for the module casserver.
 * 
 * $Id: $
 */

$config = array (

	'legal_service_urls' => array(
		'http://test.feide.no/casclient',
		'http://test.feide.no/cas2',
	),

	'auth' => 'saml2', // Legal values: saml2, shib13
	'ticketcache' => 'ticketcache',
	'attrname' => 'mail', // 'eduPersonPrincipalName' (an attribute that CAS server returns to the CAS client to prove user is authenticated)
	'attributes' => FALSE, // set to TRUE if you want to enable transfer of attributes

);

?>
