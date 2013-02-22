<?php
/*
 * Incomming parameters:
 *  service
 *  renew
 *  gateway
 *  
 */

require_once 'utility/urlUtils.php';

if (!array_key_exists('service', $_GET))
    throw new Exception('Required URL query parameter [service] not provided. (CAS Server)');

$service = sanitize($_GET['service']);

$forceAuthn = isset($_GET['renew']) && sanitize($_GET['renew']);
$isPassive = isset($_GET['gateway']) && sanitize($_GET['gateway']);

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

$ticketFactoryClass = SimpleSAML_Module::resolveClass('sbcasserver:TicketFactory', 'Cas_Ticket');
$ticketFactory = new $ticketFactoryClass($casconfig);

$ticket = $ticketFactory->createServiceTicket(array('service' => $service,
    'forceAuthn' => $forceAuthn,
    'attributes' => $attributes,
    'proxies' => array()));

$ticketStoreConfig = $casconfig->getValue('ticketstore', array('class' => 'sbcasserver:FileSystemTicketStore'));
$ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_Ticket');
$ticketStore = new $ticketStoreClass($casconfig);

$ticketStore->addTicket($ticket);

SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::addURLparameter($_GET['service'], array('ticket' => $ticket['id'])));

?>
