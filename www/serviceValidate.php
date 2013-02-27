<?php

/*
 * Incomming parameters:
 *  service
 *  renew
 *  ticket
 *
 */

require_once 'utility/urlUtils.php';

/* Load simpleSAMLphp, configuration and metadata */
$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

/* Instantiate protocol handler */
$protocolClass = SimpleSAML_Module::resolveClass('sbcasserver:Cas20', 'Cas_Protocol');
$protocol = new $protocolClass($casconfig);

if (array_key_exists('service', $_GET) && array_key_exists('ticket', $_GET)) {
    $ticketId = sanitize($_GET['ticket']);
    $service = sanitize($_GET['service']);

    $forceAuthn = isset($_GET['renew']) && sanitize($_GET['renew']);

    try {
        $ticketStoreConfig = $casconfig->getValue('ticketstore', array('class' => 'sbcasserver:FileSystemTicketStore'));
        $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_Ticket');
        $ticketStore = new $ticketStoreClass($casconfig);

        $serviceTicket = $ticketStore->getTicket($ticketId);

        if (!is_null($serviceTicket)) {
            $ticketStore->deleteTicket($ticketId);

            $usernameField = $casconfig->getValue('attrname', 'eduPersonPrincipalName');

            $attributes = $serviceTicket['attributes'];

            $ticketFactoryClass = SimpleSAML_Module::resolveClass('sbcasserver:TicketFactory', 'Cas_Ticket');
            $ticketFactory = new $ticketFactoryClass($casconfig);

            $valid = $ticketFactory->validateServiceTicket($serviceTicket);

            if ($valid['valid'] && $serviceTicket['service'] == $service && (!$forceAuthn || $serviceTicket['forceAuthn']) &&
                array_key_exists($usernameField, $attributes)
            ) {
                $protocol->setAttributes($attributes);

                if (isset($_GET['pgtUrl'])) {
                    $pgtUrl = sanitize($_GET['pgtUrl']);

                    $proxyGrantingTicket = $ticketFactory->createProxyGrantingTicket(array(
                        'attributes' => $attributes, 'forceAuthn' => false,
                        'proxies' => array_merge(array($service), $serviceTicket['proxies']),
                        'sessionId' => $serviceTicket['sessionId']));

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
                } else if ($serviceTicket['service'] != $service) {
                    echo $protocol->getFailureResponse('INVALID_SERVICE', 'Expected: ' . $serviceTicket['service'] . ' was: ' . $service);
                } else if ($serviceTicket['forceAuthn'] != $forceAuthn) {
                    echo $protocol->getFailureResponse('INVALID_TICKET', 'Service was issue from single sign on sesion: ');
                } else {
                    SimpleSAML_Logger::debug('sbcasserver:serviceValidate: internal server error. Missing user name attribute: ' .
                        var_export($usernameField, TRUE));

                    echo $protocol->getFailureResponse('INTERNAL_ERROR', 'Missing user name attribute: ' . $usernameField . ' not found.');
                }
            }
        } else {
            echo $protocol->getFailureResponse('INVALID_TICKET', 'ticket: ' . $ticketId . ' not recognized');
        }

    } catch (Exception $e) {
        SimpleSAML_Logger::debug('sbcasserver:serviceValidate: internal server error. ' .
            var_export($e->getMessage(), TRUE));

        echo $protocol->getFailureResponse('INTERNAL_ERROR', $e->getMessage());
    }
} else if (!array_key_exists('service', $_GET)) {
    echo $protocol->getFailureResponse('INVALID_REQUEST', 'Missing service request parameter');
} else {
    echo $protocol->getFailureResponse('INVALID_REQUEST', 'Missing ticket request parameter');

}
?>