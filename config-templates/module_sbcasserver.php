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

    'ticketstore' => array(
        'class' => 'sbcasserver:FileSystemTicketStore',
        'directory' => 'ticketcache',
        //'class' => 'sbcasserver:MemCacheTicketStore',
        //'prefix' => 'bibsys',
        //'expireInSeconds' => 5,
        //'class' => 'sbcasserver:AttributeStoreTicketStore',
        //'attributeStoreUrl' => 'http://devel06:9561/attributestore/services/json/store/',
        //'attributeStoreDeleteUrl' => 'http://devel06:9561/attributestore/services/store/',
        //'attributeStorePrefix' => 'sbmobile.cas',
        //'expireInMinutes' => 1, //default 1 minute
    ),

    'attrname' => 'eduPersonPrincipalName', // 'eduPersonPrincipalName',
    'attributes' => TRUE, // enable transfer of attributes

    'authsource' => 'casserver',
    'base64attributes' => true,
);

?>
