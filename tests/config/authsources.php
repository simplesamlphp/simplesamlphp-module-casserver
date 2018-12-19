<?php

$config = [
    'admin' => [
        'core:AdminPassword',
    ],
    'casserver' => [
        'exampleauth:Static',
        'uid' => ['testuser'],
        'eduPersonPrincipalName' => ['testuser@example.com'],
        'eduPersonAffiliation' => ['member', 'employee'],
        'cn' => ['Test User'],
    ],
];
