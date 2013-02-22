<?php
/*
* Incomming parameters:
*  service
*  renew
*  ticket
*
*/

require_once 'utility/urlUtils.php';

if (!array_key_exists('service', $_GET))
    throw new Exception('Required URL query parameter [service] not provided. (CAS Server)');

$service = sanitize($_GET['service']);

if (!array_key_exists('ticket', $_GET))
    throw new Exception('Required URL query parameter [ticket] not provided. (CAS Server)');

$ticketId = sanitize($_GET['ticket']);

$forceAuthn = isset($_GET['renew']) && sanitize($_GET['renew']);

/* Load simpleSAMLphp, configuration and metadata */
$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

/* Instantiate protocol handler */
$protocolClass = SimpleSAML_Module::resolveClass('sbcasserver:Cas10', 'Cas_Protocol');
$protocol = new $protocolClass($casconfig);

try {
    /* Instantiate ticket store */
    $ticketStoreConfig = $casconfig->getValue('ticketstore', array('class' => 'sbcasserver:FileSystemTicketStore'));
    $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_Ticket');
    $ticketStore = new $ticketStoreClass($casconfig);

    $ticket = $ticketStore->getTicket($ticketId);

    if (!is_null($ticket)) {
        $ticketFactoryClass = SimpleSAML_Module::resolveClass('sbcasserver:TicketFactory', 'Cas_Ticket');
        $ticketFactory = new $ticketFactoryClass($casconfig);

        $valid = $ticketFactory->validateServiceTicket($ticket);

        $ticketStore->deleteTicket($ticketId);

        $usernamefield = $casconfig->getValue('attrname', 'eduPersonPrincipalName');

        if ($valid['valid'] && $ticket['service'] == $service && $ticket['forceAuthn'] == $forceAuthn &&
            array_key_exists($usernamefield, $ticket['attributes'])) {
            echo $protocol->getSuccessResponse($ticket['attributes'][$usernamefield][0]);
        } else {
            echo $protocol->getFailureResponse();
        }
    } else {
        echo $protocol->getFailureResponse();
    }
} catch (Exception $e) {
    echo $protocol->getFailureResponse();
}
?>
