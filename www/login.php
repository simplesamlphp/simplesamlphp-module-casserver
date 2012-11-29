<?php
require 'tickets.php';

/*
 * Incomming parameters:
 *  service
 *  renew
 *  gateway
 *  
 */

if (!array_key_exists('service', $_GET))
	throw new Exception('Required URL query parameter [service] not provided. (CAS Server)');

$service = $_GET['service'];
$renew = FALSE;
$gateway = FALSE;

if (array_key_exists('renew', $_GET)) {
	$renew = TRUE;
}

if (array_key_exists('gateway', $_GET)) {
	$gateway = TRUE;
	throw new Exception('CAS gateway to SAML IsPassive: Not yet implemented properly.');
}

/* Load simpleSAMLphp, configuration and metadata */
$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

/* Instantiate ticket store */
$ticketStoreConfig = $casconfig->getValue('ticketstore');
$ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'],'Cas_TicketStore');
$ticketStore = new $ticketStoreClass($casconfig);

$as = new SimpleSAML_Auth_Simple($casconfig->getValue('authsource'));

$legal_service_urls = $casconfig->getValue('legal_service_urls');
if (!checkServiceURL($service, $legal_service_urls))
	throw new Exception('Service parameter provided to CAS server is not listed as a legal service: [service] = ' . $service);

$auth = $casconfig->getValue('auth', 'saml2');
if (!in_array($auth, array('saml2', 'shib13')))
	throw new Exception('CAS Service configured to use [auth] = ' . $auth . ' only [saml2,shib13] is legal.');

$as->requireAuth();	// added by Dubravko Voncina

$attributes = $as->getAttributes();		// added by Dubravko Voncina

$ticket = str_replace( '_', 'ST-', SimpleSAML_Utilities::generateID() );
$ticketStore->createTicket($ticket, $attributes);

SimpleSAML_Logger::debug('sbcasserver logout url'.$as->getLogoutURL());

SimpleSAML_Utilities::redirect(
	SimpleSAML_Utilities::addURLparameter($service,
		array('ticket' => $ticket)
	)
);
?>
