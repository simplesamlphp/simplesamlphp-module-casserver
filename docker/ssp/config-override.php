<?php

declare(strict_types=1);

$config['module.enable']['exampleauth'] = true;
$config['module.enable']['casserver'] = true;
// Have preprod warning enabled (though it may not be installed) to ease authproc redirect testing
$config['module.enable']['preprodwarning'] = true;
$config['trusted.url.domains'] = [ 'main-config.example.com'];
$config = [
        'secretsalt' => 'testsalt',
        'logging.level' => 7,
    ] + $config;