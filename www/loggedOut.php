<?php

session_cache_limiter('nocache');

$globalConfig = SimpleSAML_Configuration::getInstance();

$t = new SimpleSAML_XHTML_Template($globalConfig, 'sbcasserver:loggedOut.php');
$t->data['url'] = $_GET['url'];

$t->show();
