<?php
/* 
 * Configuration for the module casserver.
 * 
 */

$config = array (

	'legal_service_urls' => array(
		'http://devel01:9145/bruger/wayf/index.jsp',
		'http://localhost:7070/kultur/login.jsp',
		'http://localhost:8080/kultur/login.jsp',
	),

	// Legal values: saml2, shib13
	'auth' => 'saml2',
	
        'ticketstore' => array(
			       //			       'class' => 'sbcasserver:FileSystemTicketStore',
			       //			       'ticketDirectory' => 'ticketcache',
                                'class' => 'sbcasserver:AttributeStoreTicketStore',
                                'attributeStoreUrl' => 'http://devel06:9561/attributestore/services/json/store/',
                                'attributeStorePrefix' => 'sbmobile.cas',
			       ),

	'attrname' => 'eduPersonPrincipalName', // 'eduPersonPrincipalName',
	'attributes' => TRUE, // enable transfer of attributes

	'authsource' => 'casserver',
	'base64attributes' => true,
);

?>
