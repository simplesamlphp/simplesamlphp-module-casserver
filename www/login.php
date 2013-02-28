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

$session = SimpleSAML_Session::getInstance();

$ticketStoreConfig = $casconfig->getValue('ticketstore', array('class' => 'sbcasserver:FileSystemTicketStore'));
$ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_Ticket');
$ticketStore = new $ticketStoreClass($casconfig);

$sessionTicket = $ticketStore->getTicket($session->getSessionId());
$sessionRenewId = $sessionTicket ? $sessionTicket['renewId'] : NULL;
$requestRenewId = isset($_REQUEST['renewId']) ? $_REQUEST['renewId'] : NULL;

if (!$as->isAuthenticated() || ($forceAuthn && $sessionRenewId != $requestRenewId)) {
    $query = array();

    if ($sessionRenewId && $forceAuthn) {
        $query['renewId'] = $sessionRenewId;
    }

    if (isset($_REQUEST['service'])) {
        $query['service'] = $_REQUEST['service'];
    }

    if (isset($_REQUEST['renew'])) {
        $query['renew'] = $_REQUEST['renew'];
    }

    if (isset($_REQUEST['gateway'])) {
        $query['gateway'] = $_REQUEST['gateway'];
    }

    $returnUrl = SimpleSAML_Utilities::selfURLNoQuery() . '?' . http_build_query($query);

    $params = array(
        'ForceAuthn' => $forceAuthn,
        'isPassive' => $isPassive,
        'ReturnTo' => $returnUrl,
    );

    $as->login($params);
}

$attributes = $as->getAttributes();

$ticketFactoryClass = SimpleSAML_Module::resolveClass('sbcasserver:TicketFactory', 'Cas_Ticket');
$ticketFactory = new $ticketFactoryClass($casconfig);

$sessionTicket = $ticketFactory->createSessionTicket($session->getSessionId(), time() + $session->remainingTime());

$serviceTicket = $ticketFactory->createServiceTicket(array('service' => $service,
    'forceAuthn' => $forceAuthn,
    'attributes' => $attributes,
    'proxies' => array(),
    'sessionId' => $sessionTicket['id']));

$ticketStore->addTicket($sessionTicket);
$ticketStore->addTicket($serviceTicket);

SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::addURLparameter($_GET['service'], array('ticket' => $serviceTicket['id'])));

?>
