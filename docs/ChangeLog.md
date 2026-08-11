# Changelog

## [7.0.5] - 2026-08-11

### Changed

- Restructure and complete the changelog file (Ioannis Igoumenos)
- Require stable SimpleSAMLphp ~2.5 instead of ~2.5@dev (Ioannis Igoumenos)

### Fixed

- Fix CI pipeline workflows, resolve PHP 8.5 test deprecations and upgrade PHPUnit schema (Ioannis Igoumenos)
- Fix GitHub Actions schema validation errors and resolve Phive installation rate limits by properly passing GITHUB_TOKEN (Ioannis Igoumenos)

## [7.0.4] - 2026-08-11

### Changed

- Pass real Source/Destination metadata into the authproc processing chain (Marko Ivančić)

### Fixed

- Fix PHPUnit notices and PHPStan reported issues (Ioannis Igoumenos)

## [7.0.3] - 2026-05-15

### Fixed

- Fix unit tests to pass on Windows environments (Tim van Dijen)

## [7.0.2] - 2026-05-07

### Changed

- Minor bug fixes and dependency updates (Ioannis Igoumenos, Tim van Dijen)

### Fixed

- Fix Random utility import to use SimpleSAML\Utils\Random (Ioannis Igoumenos)

## [7.0.1] - 2026-02-21

### Changed

- Bump dependencies

## [7.0.0] - 2026-01-15

### Added

- Add routes and controllers for SimpleSAMLphp 2.x compatibility, and migrate templates to Twig (Ioannis Igoumenos, Tim van Dijen, Patrick Radtke, David Lundgren)

### Changed

- Require PHP 8.3 and SimpleSAMLphp 2.5 (Ioannis Igoumenos, Tim van Dijen, Patrick Radtke, David Lundgren)
- Upgrade xml-libraries to their 2.x versions (using typed values), update version constraints, and require ext-ctype (Ioannis Igoumenos, Tim van Dijen, Patrick Radtke, David Lundgren)
- Improve gateway parameter handling to match protocol descriptions, allow service tickets on proxyValidate, refactor legal URL comparison, and allow SimpleSAMLphp 2.4.2 assertion usage (Ioannis Igoumenos, Tim van Dijen, Patrick Radtke, David Lundgren)
- Replace Psalm with PHPstan, replace super-linter with a reusable workflow, and resolve CodeSniffer issues (Ioannis Igoumenos, Tim van Dijen, Patrick Radtke, David Lundgren)

## [6.3.1] - 2026-01-15

### Fixed

- Fix open redirect and XSS vulnerabilities in logout template (Tim van Dijen, Patrick Radtke)

## [6.3.0] - 2024-11-18

### Added

- Initial support for \`samlValidate\` for compatibility with Banner (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)
- Add support for alternate service/ticket parameter names and preserving \`debugMode\` parameter (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)
- Add support for PHP 8.0, 8.1, and 8.3 testing (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)

### Changed

- Require PHP 8.1 and SimpleSAMLphp 2.2 (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)
- Move \`lib/\` to \`src/\` and \`www/\` to \`public/\` (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)
- Migrate templates to Twig and translations to dictionaries (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)
- Migrate code to use \`xml-cas\`, \`xml-soap\`, and \`xml-common\` libraries (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)
- Refactor stores for SimpleSAMLphp 2.0 compatibility (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)
- Implement processing chain and delegating ticket store (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)
- Modernize test-suite, migrate PHPUnit config and metadata to attributes, add return types, and fix broken tests on Windows (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)
- Replace abandoned security-checker with composer audit, update SuperLinter, add markdown-linter (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)
- Configure Dependabot and improve GitHub Actions workflows (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)

### Fixed

- Fix SOAP namespace, typo/markdown issues, and handle PDO as an optional dependency (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)
- Fix Psalm, CodeSniffer (PSR-12, namespaces, strict typing), and Codecov issues (Tim van Dijen, Patrick Radtke, Ioannis Igoumenos)

## [6.2.1] - 2026-01-15

### Fixed

- Fix open redirect and XSS vulnerabilities in logout template (Tim van Dijen, Patrick Radtke)

## [6.2.0] - 2022-02-02

### Changed

- Require PHP 7.1 and SimpleSAMLphp 1.19
- Bump dependencies

## [6.1.2] - 2022-01-06

### Changed

- Rename \`.codecov.yml\` to \`codecov.yml\` (Tim van Dijen)

### Fixed

- Fix \`.gitignore\` file and configured \`allow-plugins\` tag for Composer (Tim van Dijen)

## [6.1.1] - 2019-11-13

### Added

- Add support for optional method parameter at login, and allow services to override CAS config (Tim van Dijen, Patrick Radtke, Danny)
- Add \`debugMode\` to print ticket XML (Tim van Dijen, Patrick Radtke, Danny)

### Changed

- Require PHP 5.6 and SimpleSAMLphp 1.17.7 (Tim van Dijen, Patrick Radtke, Danny)
- Improve CAS 1.0/2.0 protocol handling and illegal attribute name handling (Tim van Dijen, Patrick Radtke, Danny)
- Improve authproc filtering and fix undefined variables (Tim van Dijen, Patrick Radtke, Danny)
- Refactor CAS ticket stores (FileSystem, MemCache, Redis, SQL) (Tim van Dijen, Patrick Radtke, Danny)
- Branch off 1.x, migrate to PHP namespaces, rationalize code, and fix PSR-2 formatting (Tim van Dijen, Patrick Radtke, Danny)
- Introduce Psalm and Scrutinizer for static analysis and fix reported issues (Tim van Dijen, Patrick Radtke, Danny)
- Improve integration and functional tests to run without requiring a separate PHP server process (Tim van Dijen, Patrick Radtke, Danny)

## [6.1.0] - 2018-07-20

### Added

- Add support for using Redis as a ticket store (Bjorn Rohde Jensen)

### Changed

- Minimum supported simplesamlphp version bumped to 1.15 (Bjorn Rohde Jensen)

### Fixed

- Fix some deprecation warnings by using namespaces to refer to the relevant classes (Bjorn Rohde Jensen)

## [6.0.0] - 2016-08-01

### Added

- Add composer file to make module installable according to simplesamlphp guidelines, requiring SimpleSAMLphp 1.14 (Bjorn Rohde Jensen)

### Changed

- Rename module from sbcasserver to casserver (Bjorn Rohde Jensen)
- Replace use of deprecated SimpleSAMLphp api's (Bjorn Rohde Jensen)

### Fixed

- Fix a bug when comparing service url parameter to ticket service url, which caused service urls with %20 encoded spaces to fail verification (Bjorn Rohde Jensen)

## [5.2.3] - 2014-10-15

### Added

- Add a missing linefeed character in the CAS 1.0 ticket validation failure response (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

## [5.2.2] - 2014-03-17

### Fixed

- Fix a bug where the sanitized forms of url parameters were used for more than validation (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)
- Fix a bug in the sanitizer logic to remove ';jsessionid' from urls (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

## [5.2.1] - 2014-02-27

### Fixed

- Fix a bug where attributes containing colons in their names caused serviceValidate to return invalid xml (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

## [5.2.0] - 2014-02-25

### Added

- Add a logged in landing page in case the client does not provide a service url (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

### Changed

- It is now possible to add an attribute indicating whether the attributes are base64 encoded or not (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

### Removed

- Remove hardcoded removal of attributes starting with urn:oid (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

## [5.1.0] - 2014-02-07

### Added

- Add support for specifying which attributes to transfer (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)
- Add support for specifying a named subset of IdPs allowing clients to restrict the list of IdPs in the wayf step (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

### Fixed

- Fix a bug where the 'url' parameter was required even when displaying a logged out page (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)
- Fix a bug where attribute transfer could not be disabled (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

## [5.0.0] - 2013-11-04

### Added

- Add support for storing tickets in a SQL database (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)
- Add support for storing tickets in memcached (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)
- Add support for proxy tickets (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)
- Add support for renewing login (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)
- Add an optional logged out landing page with a return url (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)
- Add support for specifying an idp during login thus skipping the wayf step (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)
- Add support for specifying a language hint during login (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

### Removed

- Remove support for storing tickets in proprietary key-value store AttributeStore (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

## [4.0.0] - 2013-01-29

### Added

- Add an abstract cas ticket store with concrete subclasses to store tickets locally or in the attribute store (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

### Changed

- Move all filters to a dedicated project (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)
- Move theme related code into the sbthemes project (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)
- Change cas 2.0 response generation from hand coded to using php dom document (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

### Fixed

- Fix a bug in cas 1.0 response generation, where yes/no was returned in upper case (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

## [3.3.0] - 2012-10-31

### Changed

- IPRoleMapper can now be configured to add its roles to any given attribute by setting the filter parameter 'targetAttributeName' (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

## [3.2] - 2012-10-24

### Added

- Add AttributeCollector auth filter for collecting attributes from the attribute store (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)
- Add CAS logout (Bjørn Rohde Jensen <brj@statsbiblioteket.dk>)

## [3.1] - 2012-05-22

### Fixed

- Correct a spelling bug (Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>)

## [3] - 2012-05-08

### Added

- Add the Statsbiblioteket theme from sbdisco (Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>)
- Add the IP role mapper from sbdisco (Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>)

## [2.3] - 2011-10-31

### Added

- Add a hack to keep compatibility with the eduPersonNIN attribute (Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>)

## [2.2] - 2011-03-29

### Added

- Add configurable base 64 encoding of attribute values (Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>)

### Changed

- Reintroduce ignore urn:oid attributes (Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>)
- Change the casserver to use the configured auth source (Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>)

### Removed

- Remove debug messages (Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>)

## [None] - 2011-01-18

### Changed

- Change default from disabled to enabled (Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>)

## [2.1] - 2011-01-17

### Changed

- All files are rewritten based on work by Dubravko Voncina (Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>)
- See Google Groups discussion in this thread: \`http://groups.google.com/group/simplesamlphp/browse_thread/thread/4c655d169532650a\` (Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>)
- The files in www has all been changed to use the new "namespace" (Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>)

