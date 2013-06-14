<?php
/* 
 * Configuration for the module casserver.
 * 
 */

$config = array(

    'legal_service_urls' => array(
        'http://localhost:7070/kultur/login.jsp',
        'http://localhost:8080/kultur/login.jsp',
    ),

    'legal_target_service_urls' => array(
        'http://localhost:7070/kultur/login.jsp',
        'http://localhost:8080/kultur/login.jsp',
    ),

    'ticketstore' => array( //defaults to filesystem ticket store using the directory 'ticketcache'
        'class' => 'sbcasserver:FileSystemTicketStore',
        'directory' => 'ticketcache',

        //'class' => 'sbcasserver:MemCacheTicketStore',
        //'prefix' => 'bibsys',

        //'class' => 'sbcasserver:AttributeStoreTicketStore',
        //'attributeStoreUrl' => 'http://devel06:9561/attributestore/services/json/store/',
        //'attributeStoreDeleteUrl' => 'http://devel06:9561/attributestore/services/store/',
        //'attributeStorePrefix' => 'sbmobile.cas',
    ),

    'attrname' => 'eduPersonPrincipalName', // 'eduPersonPrincipalName',
    'attributes' => TRUE, // enable transfer of attributes

    'authsource' => 'casserver',
    'base64attributes' => true,
    'enable_logout' => true,
    'skip_logout_page' => true, //perform a redirect instead of showing a logout page with a link to the location given in the url parameter

    'service_ticket_expire_time' => 5, //how many seconds service tickets are valid for
    'proxy_granting_ticket_expire_time' => 600, //how many seconds proxy granting tickets are valid for at most
    'proxy_ticket_expire_time' => 5, //how many seconds proxy tickets are valid for
);

?>
