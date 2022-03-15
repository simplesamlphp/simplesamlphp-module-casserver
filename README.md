# SimpleSAMLphp-casserver

![Build Status](https://github.com/simplesamlphp/simplesamlphp-module-casserver/workflows/CI/badge.svg?branch=master)
[![Coverage Status](https://codecov.io/gh/simplesamlphp/simplesamlphp-module-casserver/branch/master/graph/badge.svg)](https://codecov.io/gh/simplesamlphp/simplesamlphp-module-casserver)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/simplesamlphp/simplesamlphp-module-casserver/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/simplesamlphp/simplesamlphp-module-casserver/?branch=master)
[![Type Coverage](https://shepherd.dev/github/simplesamlphp/simplesamlphp-module-casserver/coverage.svg)](https://shepherd.dev/github/simplesamlphp/simplesamlphp-module-casserver)
[![Psalm Level](https://shepherd.dev/github/simplesamlphp/simplesamlphp-module-casserver/level.svg)](https://shepherd.dev/github/simplesamlphp/simplesamlphp-module-casserver)

# Usage

## Install

Install with composer

    composer require simplesamlphp/simplesamlphp-module-casserver

## Configuration

See the `config-templates` folder for examples of configuring this module

## Debug

To aid in debugging you can print out the CAS ticket xml rather then returning a ticket id.
Enable `debugMode` in `module_casserver.php` and then add a query parameter `debugMode=true` to the CAS login url.

Logging in to https://cas.example.com/cas/login?debugMode=true&service=http://localhost/ would now print the xml for that service.

```xml
<?xml version="1.0">
<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
 <cas:authenticationSuccess>
  <cas:user>testuser@example.com</cas:user>
  <cas:attributes>
   <cas:eduPersonPrincipalName>testuser@example.com</cas:eduPersonPrincipalName>
   <cas:base64Attributes>false</cas:base64Attributes>
  </cas:attributes>
 </cas:authenticationSuccess>
</cas:serviceResponse>
```

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

