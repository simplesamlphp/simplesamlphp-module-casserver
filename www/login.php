<?php
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

$forceAuthn =isset($_GET['renew']) && $_GET['renew'];
$isPassive = isset($_GET['gateway']) && $_GET['gateway'];

$config = SimpleSAML_Configuration::getInstance();
$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

$legal_service_urls = $casconfig->getValue('legal_service_urls');

if (!checkServiceURL($service, $legal_service_urls))
	throw new Exception('Service parameter provided to CAS server is not listed as a legal service: [service] = ' . $service);

$as = new SimpleSAML_Auth_Simple($casconfig->getValue('authsource'));

if (!$as->isAuthenticated()) {
	$params = array(
		'ForceAuthn' => $forceAuthn,
		'isPassive' => $isPassive,
	);
	$as->login($params);
}

$attributes = $as->getAttributes();

$ticketStoreConfig = $casconfig->getValue('ticketstore');
$ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_TicketStore');
$ticketStore = new $ticketStoreClass($casconfig);

$ticket = $ticketStore->createTicket(array('service' => $service,
        'forceAuthn' => $forceAuthn,
        'attributes' => $attributes,
        'proxies' => array(),
        'validbefore' => time() + 5));

SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::addURLparameter($service, array('ticket' => $ticket)));

function checkServiceURL($service, array $legal_service_urls)
{
    foreach ($legal_service_urls AS $legalurl) {
        if (strpos($service, $legalurl) === 0) return TRUE;
    }

    return FALSE;
}

?>
