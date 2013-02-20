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
    $ticket = sanitize($_GET['ticket']);
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
    $ticketStoreConfig = $casconfig->getValue('ticketstore');
    $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_TicketStore');
    $ticketStore = new $ticketStoreClass($casconfig);

    $ticketcontent = $ticketStore->getTicket($ticket);

    if (!is_null($ticketcontent)) {
        $ticketStore->removeTicket($ticket);

        $usernamefield = $casconfig->getValue('attrname', 'eduPersonPrincipalName');

        $attributes = $ticketcontent['attributes'];

        if ($ticketcontent['service'] == $service && $ticketcontent['forceAuthn'] == $forceAuthn && array_key_exists($usernamefield, $attributes)) {
            $protocol->setAttributes($attributes);

            if (isset($_GET['pgtUrl'])) {
                $pgtUrl = sanitize($_GET['pgtUrl']);

                $proxyGrantingTicket = array(
                    'attributes' => $attributes,
                    'forceAuthn' => false,
                    'proxies' => array_merge(array($service), $ticketcontent['proxies']),
                    'validbefore' => time() + 60);

                $proxyGrantingTicketId = $ticketStore->createProxyGrantingTicket($proxyGrantingTicket);

                try {
                    SimpleSAML_Utilities::fetch($pgtUrl . '?pgtIou=' . $proxyGrantingTicketId['iou'] . '&pgtId=' . $proxyGrantingTicketId['id']);

                    $protocol->setProxyGrantingTicketIOU($proxyGrantingTicketId['iou']);
                } catch (Exception $e) {
                    $ticketStore->removeTicket($proxyGrantingTicketId['id']);
                }
            }

            echo $protocol->getSuccessResponse($attributes[$usernamefield][0]);
        } else {
            if ($ticketcontent['service'] != $service) {
                echo $protocol->getFailureResponse('INVALID_SERVICE', 'Expected: ' .$ticketcontent['service'] . ' was: ' . $service);
            } else if ($ticketcontent['forceAuthn'] == $forceAuthn) {
                echo $protocol->getFailureResponse('INVALID_TICKET', 'Mismatching renew. Expected: ' . $ticketcontent['forceAuthn'] . ' was: ' . $forceAuthn);
            } else {
                echo $protocol->getFailureResponse('INTERNAL_ERROR', 'Missing user name, attribute: ' . $usernamefield . ' not found.');
            }
        }
    } else {
        echo $protocol->getFailureResponse('INVALID_TICKET', 'ticket: ' . $ticket . ' not recognized');
    }

} catch (Exception $e) {
    echo $protocol->getFailureResponse('INTERNAL_ERROR', $e->getMessage());
}
?>