<?php

declare(strict_types=1);

$projectRootDirectory = dirname(__DIR__);
$projectConfigDirectory = $projectRootDirectory . '/tests/config';
$simplesamlphpConfig = $projectRootDirectory . '/vendor/simplesamlphp/simplesamlphp/config';
$ticketCacheDir = $projectRootDirectory . '/tests/ticketcache';
if (!file_exists($ticketCacheDir)) {
    mkdir($ticketCacheDir);
}
$ticketCacheDirAlt = $projectRootDirectory . '/tests/ticketcacheAlt';
if (!file_exists($ticketCacheDirAlt)) {
    mkdir($ticketCacheDirAlt);
}
require_once($projectRootDirectory . '/vendor/autoload.php');

// Symlink module into ssp vendor lib so that templates and urls can resolve correctly
$linkPath = $projectRootDirectory . '/vendor/simplesamlphp/simplesamlphp/modules/casserver';
if (file_exists($linkPath) === false) {
    echo "Linking '$linkPath' to '$projectRootDirectory'\n";
    symlink($projectRootDirectory, $linkPath);
}

/**
 * Sets a link in the simplesamlphp vendor directory
 * @param string $target
 * @param string $link
 */
function symlinkModulePathInVendorDirectory(string $target, string $link): void
{
    if (file_exists($link) === false) {
        // If the link is invalid, remove it.
        if (is_link($link)) {
            unlink($link);
        }
        print "Linking '$link' to '$target'\n";
        symlink($target, $link);
    } else {
        if (is_link($link) === false) {
            // Looks like there is a directory here. Lets remove it and symlink in this one
            print "Renaming pre-installed path and linking '$link' to '$target'\n";
            rename($link, $link . '-preinstalled');
            symlink($target, $link);
        }
    }
}

symlinkModulePathInVendorDirectory($projectConfigDirectory, $simplesamlphpConfig);
