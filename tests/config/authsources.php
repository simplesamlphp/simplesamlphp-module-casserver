<?php

$config = array(
    'admin' => array(
        'core:AdminPassword',
    ),
    'casserver' => array(
        'exampleauth:Static',
        'uid' => array('testuser'),
        'eduPersonPrincipalName' => array('testuser@example.com'),
        'eduPersonAffiliation' => array('member', 'employee'),
        'cn' => array('Test User'),
    ),
);
