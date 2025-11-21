# SimpleSAMLphp-casserver

![Build Status](https://github.com/simplesamlphp/simplesamlphp-module-casserver/actions/workflows/php.yml/badge.svg)
[![Coverage Status](https://codecov.io/gh/simplesamlphp/simplesamlphp-module-casserver/branch/master/graph/badge.svg)](https://codecov.io/gh/simplesamlphp/simplesamlphp-module-casserver)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/simplesamlphp/simplesamlphp-module-casserver/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/simplesamlphp/simplesamlphp-module-casserver/?branch=master)
[![Type Coverage](https://shepherd.dev/github/simplesamlphp/simplesamlphp-module-casserver/coverage.svg)](https://shepherd.dev/github/simplesamlphp/simplesamlphp-module-casserver)
[![Psalm Level](https://shepherd.dev/github/simplesamlphp/simplesamlphp-module-casserver/level.svg)](https://shepherd.dev/github/simplesamlphp/simplesamlphp-module-casserver)

SimpleSAMLphp-casserver is a CAS 1.0 and 2.0 compliant CAS server in the form
of a SimpleSAMLphp module.

## Install

Install with composer

```bash
composer require simplesamlphp/simplesamlphp-module-casserver
```

## Configuration

Next thing you need to do is to enable the module: in `config.php`,
search for the `module.enable` key and set `casserver` to true:

```php
'module.enable' => [
    'casserver' => true,
    …
],
```

See the `config-templates` folder for examples of configuring this module

## Debug

To aid in debugging you can print out the CAS ticket xml rather then returning
a ticket id. Enable `debugMode` in `module_casserver.php` and then add a query
parameter `debugMode=true` to the CAS login url.

Logging in to
`https://cas.example.com/cas/login?debugMode=true&service=http://localhost/`
would now print the xml for that service.

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

## Development

Run `phpcs` to check code style

```shell
phpcs --standard=PSR12 lib/ tests/ www/ templates/
```

Run `phpunit` to test

```shell
./vendor/bin/phpunit
```

Use docker php image to easily test between versions

```shell
docker run -ti --rm -v "$PWD":/usr/src/myapp -w /usr/src/myapp php:7.1-cli ./vendor/bin/phpunit
```

You can auto correct some findings from phpcs. It is recommended you do this
after stage your changes (or maybe even commit) since there is a non-trivial
chance it will just mess up your code.

```shell
phpcbf --ignore=somefile.php --standard=PSR12 lib/ tests/ www/ templates/
```

### Local testing with docker

To explore the module using docker run the below command. This will run an SSP image, with the current git checkout
of the `casserver` module mounted in the container, along with some configuration files. Any code changes you make to your git checkout are
"live" in the container, allowing you to test and iterate different things.

Sometimes when working with a dev version of the module you will need a newer version of a dependency than what SSP is
locked to. In that case you can add an additional dependency to the `COMPOSER_REQUIRE` line (e.g ="simplesamlphp/assert:1.8 ")

```bash
docker run --name ssp-casserver-dev \
   --mount type=bind,source="$(pwd)",target=/var/simplesamlphp/staging-modules/casserver,readonly \
  -e STAGINGCOMPOSERREPOS=casserver \
  -e COMPOSER_REQUIRE="simplesamlphp/simplesamlphp-module-casserver:@dev simplesamlphp/simplesamlphp-module-preprodwarning" \
  -e SSP_ADMIN_PASSWORD=secret1 \
  --mount type=bind,source="$(pwd)/docker/ssp/module_casserver.php",target=/var/simplesamlphp/config/module_casserver.php,readonly \
  --mount type=bind,source="$(pwd)/docker/ssp/authsources.php",target=/var/simplesamlphp/config/authsources.php,readonly \
  --mount type=bind,source="$(pwd)/docker/ssp/config-override.php",target=/var/simplesamlphp/config/config-override.php,readonly \
  --mount type=bind,source="$(pwd)/docker/apache-override.cf",target=/etc/apache2/sites-enabled/ssp-override.cf,readonly \
   -p 443:443 cirrusid/simplesamlphp:v2.4.2
```

Visit [https://localhost/simplesaml/](https://localhost/simplesaml/) and confirm you get the default page.
Then navigate to [casserver debug](https://localhost/cas/login?service=http://host1.domain:1234/path1&debugMode=true), authenticate and confirm
use see what a ticket would look like. To see what a CAS v1 saml response looks like set [debugMode=samlValidate](https://localhost/cas/login?service=http://host1.domain:1234/path1&debugMode=samlValidate)

## History

CAS 1.0 and 2.0 compliant CAS server module for simpleSAMLphp

This is the simpleSAMLphp CAS server module developed at the State and
University Library in Aarhus Denmark. The module is a fork of an old version
of the CAS module shipped with simpleSAMLphp which has undergone a couple of
iterations of refactoring, bugfixes and enhancements.
For details see the ChangeLog in the doc directory.

All files are rewritten based on work by Dubravko Voncina.
See Google Groups discussion in [this thread][1].

[1]: http://groups.google.com/group/simplesamlphp/browse_thread/thread/4c655d169532650a

### License

This work is licensed under a Creative Commons GNU Lesser General Public
License License.
