<?php

/**
 * Config file to use during integration testing
 */

use SimpleSAML\Logger;

$config = [
    'baseurlpath' => '/',
    'tempdir' => '/tmp/simplesaml',
    'loggingdir' => '/tmp/simplesaml',
    'secretsalt' => 'salty',

    'metadata.sources' => [
        ['type' => 'flatfile', 'directory' =>  dirname(__DIR__) . '/metadata'],
    ],

    'module.enable' => [
        'casserver' => true,
        'exampleauth' => true
    ],

    'debug' => true,
    'logging.level' => Logger::DEBUG,
    'logging.handler' => 'errorlog',
];
