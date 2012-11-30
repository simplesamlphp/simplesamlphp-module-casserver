<?php
/*
 * Incomming parameters:
 *  url
 *  ticket
 *
 */

if (!array_key_exists('url', $_GET))
  throw new Exception('Required URL query parameter [url] not provided. (CAS Server)');

$url = $_GET['url'];

if (!array_key_exists('ticket', $_GET))
  throw new Exception('Required URL query parameter [ticket] not provided. (CAS Server)');

$ticket = $_GET['ticket'];

/* Load simpleSAMLphp, configuration and metadata */
$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');	

/* Instantiate ticket store */
$ticketStoreConfig = $casconfig->getValue('ticketstore');
$ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'],'Cas_TicketStore');
$ticketStore = new $ticketStoreClass($casconfig);

$ticketStore->removeTicket($ticket);

$auth = $casconfig->getValue('auth', 'saml2');

if (!in_array($auth, array('saml2', 'shib13')))
  throw new Exception('CAS Service configured to use [auth] = ' . $auth . ' only [saml2,shib13] is legal.');

$as = new SimpleSAML_Auth_Simple($casconfig->getValue('authsource'));

SimpleSAML_Logger::debug('sbcasserver config'.var_export($as,TRUE));

if ($as->isAuthenticated()) {
  SimpleSAML_Logger::debug('sbcasserver logged out: real logout');

  $as->logout($url);
} else {
  SimpleSAML_Logger::debug('sbcasserver logged out: redirected');

  SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::addURLparameter($url,array()));        
}
?>

