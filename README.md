simpleSAMLphp-casserver
=========================

# Usage

## Install

Install with composer

    composer require simplesamlphp/simplesamlphp-module-casserver

## Configuration

See the `config-templates` folder for examples of configuring this module

# Development

Run `phpcs` to check code style

    phpcs --standard=PSR2 lib/ tests/ www/ templates/

Run `phpunit` to test

    ./vendor/bin/phpunit
    
Use docker php image to easily test between versions

    docker run -ti --rm -v "$PWD":/usr/src/myapp  -w /usr/src/myapp php:7.1-cli ./vendor/bin/phpunit


You can auto correct some findings from phpcs. It is recommended you do this after stage your changes (or maybe even commit) since there is a non-trivial chance it will just mess up your code.

    phpcbf --ignore=somefile.php --standard=PSR2 lib/ tests/ www/ templates/
    
# History
CAS 1.0 and 2.0 compliant CAS server module for simpleSAMLphp

This is the simpleSAMLphp CAS server module developed at the State and University Library in Aarhus Denmark.
The module is a fork of an old version of the CAS module shipped with simpleSAMLphp which has undergone a couple of
iterations of refactoring, bugfixes and enhancements. For details see the ChangeLog in the doc directory.

All files are rewritten based on work by Dubravko Voncina. See Google Groups discussion in this thread:
http://groups.google.com/group/simplesamlphp/browse_thread/thread/4c655d169532650a

# License
This work is licensed under a Creative Commons GNU Lesser General Public License License.

