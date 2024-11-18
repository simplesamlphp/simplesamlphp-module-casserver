# simplesamlphp-module-casserver changelog

Unreleased

* Allow certain authproc filters to be used in configuration
* Support method parameter at login
* Improve handling of invalid xml element names for attributes
* Minimum supported simplesamlphp version bumped to 1.17
* debugMode option to display cas ticket xml
* Allow per service overriding of configuration options for www/login

2018-07-20 Bjorn Rohde Jensen

* Release 6.1.0
* Minimum supported simplesamlphp version bumped to 1.15.
* Fixed some deprecation warnings by using namespaces to refer to the
  relevant classes.
* Added support for using Redis as a ticket store.

2016-08-01 Bjorn Rohde Jensen

* Release 6.0.0
* Renamed module from sbcasserver to casserver.
* Added composer file to make module installable according to
  simplesamlphp guidelines.
* Replaced use of deprecated SimpleSAMLphp api's.
* Fixed a bug when comparing service url parameter to ticket service url,
  which caused service urls with %20 encoded spaces to fail verification.

2014-10-15 Bjørn Rohde Jensen <brj@statsbiblioteket.dk>

* Release 5.2.3
* Added a missing linefeed character in the CAS 1.0 ticket validation
  failure response.

2014-03-17 Bjørn Rohde Jensen <brj@statsbiblioteket.dk>

* Release 5.2.2
* Fixed a bug where the sanitized forms of url parameters were used for
  more than validation.
* Fixed a bug in the sanitizer logic to remove ';jsessionid' from urls.

2014-02-27 Bjørn Rohde Jensen <brj@statsbiblioteket.dk>

* Release 5.2.1
* Fixed a bug where attributes containing colons in their names caused
  serviceValidate to return invalid xml.

2014-02-25 Bjørn Rohde Jensen <brj@statsbiblioteket.dk>

* Release 5.2.0
* Removed hardcoded removal of attributes starting with urn:oid.
* Added a logged in landing page in case the client does not provide
  a service url.
* It is now possible to add an attribute indicating whether the attributes
  are base64 encoded or not.

2014-02-07 Bjørn Rohde Jensen <brj@statsbiblioteket.dk>

* Release 5.1.0
* Fixed a bug where the 'url' parameter was required even when displaying
  a logged out page.
* Fixed a bug where attribute transfer could not be disabled.
* Added support for specifying which attributes to transfer.
* Added support for specifying a named subset of IdPs allowing clients to
  restrict the list of IdPs in the wayf step.

2013-11-04 Bjørn Rohde Jensen <brj@statsbiblioteket.dk>

* Release 5.0.0
* Removed support for storing tickets in proprietary key-value
  store AttributeStore.
* Added support for storing tickets in a SQL database.
* Added support for storing tickets in memcached.
* Added support for proxy tickets.
* Added support for renewing login.
* Added an optional logged out landing page with a return url.
* Added support for specifying an idp during login thus skipping the
  wayf step.
* Added support for specifying a language hint during login.

2013-01-29 Bjørn Rohde Jensen <brj@statsbiblioteket.dk>

* Release 4.0.0
* Moved all filters to a dedicated project.
* Moved theme related code into the sbthemes project.
* Added an abstract cas ticket store with concrete subclasses to store
  tickets locally or in the attribute store.
* Changed cas 2.0 response generation from hand coded to using
  php dom document.
* Fixed a bug in cas 1.0 response generation, where yes/no was
  returned in upper case.

2012-10-31 Bjørn Rohde Jensen <brj@statsbiblioteket.dk>

* release 3.3.0
* IPRoleMapper can now be configured to add its roles to any given attribute
  by setting the filter parameter 'targetAttributeName'.

2012-10-24 Bjørn Rohde Jensen <brj@statsbiblioteket.dk>

* release 3.2
* Added AttributeCollector auth filter for collecting attributes from
  the attribute store.
* Added CAS logout

2012-05-22  Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>

* release 3.1
* Corrected a spelling bug

2012-05-08  Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>

* Release 3
* Added the Statsbiblioteket theme from sbdisco
* Added the IP role mapper from sbdisco

2011-10-31  Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>

* Release 2.3
* Added a hack to keep compatibility with the eduPersonNIN attribute.

2011-03-29  Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>

* Release 2.2
* Reintroduced ignore urn:oid attributes.
* Added configurable base 64 encoding of attribute values.
* Removed debug messages.
* Changed the casserver to use the configured auth source.

2011-01-18  Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>

* Changed default from disabled to enabled

2011-01-17  Per Møldrup-Dalum  <pdj@statsbiblioteket.dk>

* Creating release 2.0
* All files are rewritten based on work by Dubravko Voncina. See Google
  Groups discussion in this thread:
  `http://groups.google.com/group/simplesamlphp/browse_thread/thread/4c655d169532650a`
* Creating release 2.1
  The sbcasserver module now uses its own "namespace" instead of
  hi-jacking the casserver "namespace". Therefore the
  `config-templates/module_casserver.php` has been renamed to
  module_sbcasserver.php
* The files in www has all been changed to use the new "namespace".
