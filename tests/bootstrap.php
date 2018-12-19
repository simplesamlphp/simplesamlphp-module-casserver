<?php

$projectRoot = dirname(__DIR__);
require_once($projectRoot.'/vendor/autoload.php');

// Symlink module into ssp vendor lib so that templates and urls can resolve correctly
$linkPath = $projectRoot.'/vendor/simplesamlphp/simplesamlphp/modules/casserver';
if (file_exists($linkPath) === false) {
    print "Linking '$linkPath' to '$projectRoot'\n";
    symlink($projectRoot,$linkPath);
} else {
    if (is_link($linkPath) === false) {
        // Looks like the pre-installed casserver module is here. Lets remove it and symlink in this one
        print "Renaming pre-installed casserver module and linking '$linkPath' to '$projectRoot'\n";
        rename($linkPath, $linkPath.'-preinstalled');
        symlink($projectRoot, $linkPath);
    }
}

// Enable exampleauth for integration tests
if (touch($projectRoot.'/vendor/simplesamlphp/simplesamlphp/modules/exampleauth/enable') === false) {
    throw new \Exception("Unable to enable 'exampleauth'. Integration tests will likely fail");
}

// Ensure the directory used for the ticket cache exists
$ticketCacheDirectory = $projectRoot.'/tests/ticketcache';
if (!file_exists($ticketCacheDirectory)) {
    mkdir($ticketCacheDirectory);
}
