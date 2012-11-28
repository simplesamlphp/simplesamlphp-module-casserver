<?php

$config = array(

		// An authentication source which can authenticate against both SAML 2.0
		// and Shibboleth 1.3 IdPs.
		'mediestream' => array(
				       'saml:SP',

				       // The entity ID of this SP.
				       // Can be NULL/unset, in which case an entity ID is generated based on the metadata URL.
				       'entityID' => 'https://mediestream-samldev.statsbiblioteket.dk',

				       // The entity ID of the IdP this should SP should contact.
				       // Can be NULL/unset, in which case the user will be shown a list of available IdPs.
				       'idp' => NULL,

				       // The URL to the discovery service.
				       // Can be NULL/unset, in which case a builtin discovery service will be used.
				       'discoURL' => NULL,
				       'redirect.sign' => true,
				       'redirect.validate' => true,

				       'privatekey' => 'star.statsbiblioteket.dk.key',
				       'certificate' => 'star.statsbiblioteket.dk.crt',

				       'authproc' => array(10 => array(
								       'class' => 'sbcasserver:IPAuth',
								       'url' => 'http://alhena:7950/iprolemapping/getRoles/',
								       'append.string' => '@ip.roles.statsbiblioteket.dk',
								       'base64.encode' => false,
								       'targetAttributeName' => 'SBRoles',//defaults to 'eduPersonScopedAffiliation'
								       ),
							   20 => array(
								       'class' => 'sbcasserver:AttributeCollector',
								       'attributeStoreUrl' => 'http://devel06:9561/attributestore/services/json/store/',
								       'attributeStorePrefix' => 'aarhusstadsarkiv',
								       ),
							   30 => array(
								       'class' => 'sbcasserver:BorrowerId',
								       'ws-userregistry' =>'http://devel06:9140/ws-userregistry/services/UserRegistryPort?wsdl',
								       'sbBorrowerIdAttribute' => 'SBBorrowerId',
								       ),
							   40=> array(
								      'class' => 'sbcasserver:UserRegistryAttributes',
								      'ws-userregistry' =>'http://devel06:9140/ws-userregistry/services/UserRegistryPort?wsdl',
								      'sbBorrowerIdAttribute' => 'SBBorrowerId',
								      'sbPersonPrimaryAffiliationAttribute' => 'SBRoles',
								      'sbPersonPrimaryAffiliationMapping' => array('staff' => '/^ans@statsbiblioteket\.dk/'),
								      'sbPersonScopedAffiliationAttribute' => 'SBRoles',
								      'sbPersonScopedAffiliationMapping' => array('staff@' => '/^ans@/',
														  'student@'=>'/^stud@/',
														  'affiliate@'=>'/^emeritus@/'),
								      ),
							   ),
				       )
		);
