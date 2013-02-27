<?php

/*
 * Incomming parameters:
 *  targetService
 *  pgt
 *  
 */

require_once 'utility/urlUtils.php';

$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

/* Instantiate protocol handler */
$protocolClass = SimpleSAML_Module::resolveClass('sbcasserver:Cas20', 'Cas_Protocol');
$protocol = new $protocolClass($casconfig);

if (array_key_exists('targetService', $_GET) && array_key_exists('pgt', $_GET)) {
    $proxyGrantingTicketId = sanitize($_GET['pgt']);
    $targetService = sanitize($_GET['targetService']);

    $legal_service_urls = $casconfig->getValue('legal_service_urls');

    if (!checkServiceURL($targetService, $legal_service_urls))
        throw new Exception('Service parameter provided to CAS server is not listed as a legal service: [targetService] = ' . $targetService);

    $ticketStoreConfig = $casconfig->getValue('ticketstore', array('class' => 'sbcasserver:FileSystemTicketStore'));
    $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_Ticket');
    $ticketStore = new $ticketStoreClass($casconfig);

    $ticketFactoryClass = SimpleSAML_Module::resolveClass('sbcasserver:TicketFactory', 'Cas_Ticket');
    $ticketFactory = new $ticketFactoryClass($casconfig);

    $proxyGrantingTicket = $ticketStore->getTicket($proxyGrantingTicketId);

    if (!is_null($proxyGrantingTicket) && $ticketFactory->isProxyGrantingTicket($proxyGrantingTicket)) {
        $sessionTicket = $ticketStore->getTicket($proxyGrantingTicket['sessionId']);

        if (!is_null($sessionTicket) && $ticketFactory->isSessionTicket($sessionTicket) && !$ticketFactory->isExpired($sessionTicket)) {
            $proxyTicket = $ticketFactory->createProxyTicket(array('service' => $targetService,
                'forceAuthn' => $proxyGrantingTicket['forceAuthn'],
                'attributes' => $proxyGrantingTicket['attributes'],
                'proxies' => $proxyGrantingTicket['proxies'],
                'sessionId' => $proxyGrantingTicket['sessionId']));

            $ticketStore->addTicket($proxyTicket);

            echo $protocol->getProxySuccessResponse($proxyTicket['id']);
        } else {
            echo $protocol->getProxyFailureResponse('BAD_PGT', 'Ticket: ' . $proxyGrantingTicketId . ' has expired');
        }
    } else if (!$ticketFactory->isProxyGrantingTicket($proxyGrantingTicket)) {
        echo $protocol->getProxyFailureResponse('BAD_PGT', 'Not a valid proxy granting ticket id: ' . $proxyGrantingTicketId);
    } else {
        echo $protocol->getProxyFailureResponse('BAD_PGT', 'Ticket: ' . $proxyGrantingTicketId . ' not recognized');
    }
} else if (!array_key_exists('targetService', $_GET)) {
    echo $protocol->getProxyFailureResponse('INVALID_REQUEST', 'Missing targetService request parameter');
} else {
    echo $protocol->getProxyFailureResponse('INVALID_REQUEST', 'Missing pgt request parameter');
}
?>