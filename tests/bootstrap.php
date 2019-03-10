<?php

$projectRoot = dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
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
