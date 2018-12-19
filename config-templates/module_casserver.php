<?php
/* 
 * Configuration for the module casserver.
 * 
 */

$config = [
    'authsource' => 'casserver',

    /* Scopes are named sets of entityIDs to be used for scoping. If a named scope is provided during login, then the
       IdPs listed by the disco service will be restricted to entityIDs in the specified set. */
    'scopes' =>[
        'mobile' => [
            'https://idp1.domain:1234/saml2/idp/metadata.php',
            'https://idp2.domain:5678/saml2/idp/metadata.php'
        ],
        'desktop' => [
            'https://idp3.domain:1234/saml2/idp/metadata.php', 
            'https://idp4.domain:5678/saml2/idp/metadata.php'
        ]
    ],
    'legal_service_urls' => [
         //Any service url string matching any of the following prefixes is accepted
        'http://host1.domain:1234/path1',
        'https://host2.domain:5678/path2/path3',
    ],

    'legal_target_service_urls' => [
         //Any target service url string matching any of the following prefixes is accepted
        'http://host3.domain:4321/path4',
        'https://host4.domain:8765/path5/path6',
        // So is regex
        '|^https://.*\.domain.com/|'
    ],

    'ticketstore' => [
         //defaults to filesystem ticket store using the directory 'ticketcache'
        'class' => 'casserver:FileSystemTicketStore', //Not intended for production
        'directory' => 'ticketcache',

        //'class' => 'casserver:MemCacheTicketStore',
        //'prefix' => 'some_prefix',

        //'class' => 'casserver:SQLTicketStore',
        //'dsn' => 'pgsql:host=localhost;port=5432;dbname=casserver',
        //'username' => 'username',
        //'password' => 'password',
        //'prefix' => 'some_prefix',
        //'options' => array(
        //    \PDO::ATTR_TIMEOUT => 4,
        // )

        //'class' => 'casserver:RedisTicketStore',
        //'prefix' => 'some_prefix',
    ],

    'attrname' => 'eduPersonPrincipalName', // 'eduPersonPrincipalName',
    'attributes' => true, // enable transfer of attributes, defaults to false
    'attributes_to_transfer' => ['eduPersonPrincipalName'], // set of attributes to transfer, defaults to all

    'base64attributes' => true, // base64 encode transferred attributes, defaults to false
    'base64_attributes_indicator_attribute' => 'base64Attributes', /*add an attribute with the value of the base64attributes
                                                                     configuration parameter to the set of transferred attributes.
                                                                     Defaults to not adding an indicator attribute. */

    'enable_logout' => true, // enable CAS logout, defaults to false
    'skip_logout_page' => true, /*perform a redirect instead of showing a logout page with a link to the location
                                  given in the url parameter, defaults to false. Skipping the logout page makes the
                                  url query parameter to CAS logout mandatory for obvious reasons.*/

    'service_ticket_expire_time' => 5, //how many seconds service tickets are valid for, defaults to 5
    'proxy_granting_ticket_expire_time' => 600, //how many seconds proxy granting tickets are valid for at most, defaults to 3600
    'proxy_ticket_expire_time' => 5, //how many seconds proxy tickets are valid for, defaults to 5
];
