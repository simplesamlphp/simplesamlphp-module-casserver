<?php
require 'tickets.php';

/*
 * Incomming parameters:
 *  service
 *  renew
 *  ticket
 *
 */


if (!array_key_exists('service', $_GET))
	throw new Exception('Required URL query parameter [service] not provided. (CAS Server)');

$service = $_GET['service'];

if (!array_key_exists('ticket', $_GET))
	throw new Exception('Required URL query parameter [ticket] not provided. (CAS Server)');

$ticket = $_GET['ticket'];

$renew = FALSE;

if (array_key_exists('renew', $_GET)) {
	$renew = TRUE;
}



try {
	/* Load simpleSAMLphp, configuration and metadata */
	$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');
	
	/* Instantiate ticket store */
	$ticketStoreConfig = $casconfig->getValue('ticketstore');
	$ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'],'Cas_TicketStore');
	$ticketStore = new $ticketStoreClass($casconfig);
	
	$ticketcontent = $ticketStore->getTicket($ticket);
	$ticketStore->deleteTicket($ticket);
	
	$usernamefield = $casconfig->getValue('attrname', 'eduPersonPrincipalName');
	
	if (array_key_exists($usernamefield, $ticketcontent)) {
		returnResponse('YES', $ticketcontent[$usernamefield][0]);
	} else {
		returnResponse('NO');
	}

} catch (Exception $e) {

	returnResponse('NO');
}

function returnResponse($value, $username = '') {
	if ($value === 'YES') {
		echo 'YES' . "\n" . $username;
	} else {
		echo 'NO' . "\n";
	}
}

?>
