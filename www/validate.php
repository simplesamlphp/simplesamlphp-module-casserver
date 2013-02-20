<?php
/*
* Incomming parameters:
*  service
*  renew
*  ticket
*
*/

require_once 'urlUtils.php';

if (!array_key_exists('service', $_GET))
    throw new Exception('Required URL query parameter [service] not provided. (CAS Server)');

$service = sanitize($_GET['service']);

if (!array_key_exists('ticket', $_GET))
    throw new Exception('Required URL query parameter [ticket] not provided. (CAS Server)');

$ticket = sanitize($_GET['ticket']);

$forceAuthn = isset($_GET['renew']) && sanitize($_GET['renew']);

try {
    /* Load simpleSAMLphp, configuration and metadata */
    $casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

    /* Instantiate ticket store */
    $ticketStoreConfig = $casconfig->getValue('ticketstore');
    $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_TicketStore');
    $ticketStore = new $ticketStoreClass($casconfig);

    $ticketcontent = $ticketStore->getTicket($ticket);

    if (!is_null($ticketcontent)) {
        $ticketStore->removeTicket($ticket);

        $usernamefield = $casconfig->getValue('attrname', 'eduPersonPrincipalName');

        if ($ticketcontent['service'] == $service && $ticketcontent['forceAuthn'] == $forceAuthn && array_key_exists($usernamefield, $ticketcontent['attributes'])) {
            echo generateCas10SuccessContent($ticketcontent['attributes'][$usernamefield][0]);
        } else {
            echo generateCas10FailureContent();
        }
    } else {
        echo generateCas10FailureContent();
    }
} catch (Exception $e) {
    echo generateCas10FailureContent();
}

function generateCas10SuccessContent($username)
{
    return "yes\n" . $username . "\n";
}

function generateCas10FailureContent()
{
    return "no\n";
}

?>
