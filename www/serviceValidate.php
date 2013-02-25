<?php

/*
 * Incomming parameters:
 *  service
 *  renew
 *  ticket
 *
 */

require_once 'utility/urlUtils.php';

if (array_key_exists('service', $_GET)) {
    $service = sanitize($_GET['service']);
    $ticketId = sanitize($_GET['ticket']);
    $forceAuthn = isset($_GET['renew']) && sanitize($_GET['renew']);
} else {
    throw new Exception('Required URL query parameter [service] not provided. (CAS Server)');
}

/* Load simpleSAMLphp, configuration and metadata */
$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

/* Instantiate protocol handler */
$protocolClass = SimpleSAML_Module::resolveClass('sbcasserver:Cas20', 'Cas_Protocol');
$protocol = new $protocolClass($casconfig);

try {
    $ticketStoreConfig = $casconfig->getValue('ticketstore', array('class' => 'sbcasserver:FileSystemTicketStore'));
    $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_Ticket');
    $ticketStore = new $ticketStoreClass($casconfig);

    $ticket = $ticketStore->getTicket($ticketId);

    if (!is_null($ticket)) {
        $ticketStore->deleteTicket($ticketId);

        $usernameField = $casconfig->getValue('attrname', 'eduPersonPrincipalName');

        $attributes = $ticket['attributes'];

        $ticketFactoryClass = SimpleSAML_Module::resolveClass('sbcasserver:TicketFactory', 'Cas_Ticket');
        $ticketFactory = new $ticketFactoryClass($casconfig);

        $valid = $ticketFactory->validateServiceTicket($ticket);

        if ($valid['valid'] && $ticket['service'] == $service && $ticket['forceAuthn'] == $forceAuthn &&
            array_key_exists($usernameField, $attributes)
        ) {
            $protocol->setAttributes($attributes);

            if (isset($_GET['pgtUrl'])) {
                $pgtUrl = sanitize($_GET['pgtUrl']);

                $proxyGrantingTicket = $ticketFactory->createProxyGrantingTicket(array(
                    'attributes' => $attributes, 'forceAuthn' => false,
                    'proxies' => array_merge(array($service), $ticket['proxies'])));

                try {
                    SimpleSAML_Utilities::fetch($pgtUrl . '?pgtIou=' . $proxyGrantingTicket['iou'] . '&pgtId=' . $proxyGrantingTicket['id']);

                    $protocol->setProxyGrantingTicketIOU($proxyGrantingTicket['iou']);

                    $ticketStore->addTicket($proxyGrantingTicket);
                } catch (Exception $e) {
                }
            }

            echo $protocol->getSuccessResponse($attributes[$usernameField][0]);
        } else {
            if (!$valid['valid']) {
                echo $protocol->getFailureResponse('INVALID_TICKET', $valid['reason']);
            } else if ($ticket['service'] != $service) {
                echo $protocol->getFailureResponse('INVALID_SERVICE', 'Expected: ' . $ticket['service'] . ' was: ' . $service);
            } else if ($ticket['forceAuthn'] == $forceAuthn) {
                echo $protocol->getFailureResponse('INVALID_TICKET', 'Mismatching renew. Expected: ' . $ticket['forceAuthn'] . ' was: ' . $forceAuthn);
            } else {
                echo $protocol->getFailureResponse('INTERNAL_ERROR', 'Missing user name, attribute: ' . $usernameField . ' not found.');
            }
        }
    } else {
        echo $protocol->getFailureResponse('INVALID_TICKET', 'ticket: ' . $ticketId . ' not recognized');
    }

} catch (Exception $e) {
    echo $protocol->getFailureResponse('INTERNAL_ERROR', $e->getMessage());
}
?>