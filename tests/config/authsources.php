<?php

$config = [
    'admin' => [
        'core:AdminPassword',
    ],
    'casserver' => [
        'exampleauth:StaticSource',
        'uid' => ['testuser'],
        'eduPersonPrincipalName' => ['testuser@example.com'],
        'eduPersonAffiliation' => ['member', 'employee'],
        'cn' => ['Test User'],
    ],
];
