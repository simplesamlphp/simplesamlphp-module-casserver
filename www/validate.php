<?php
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
    $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_TicketStore');
    $ticketStore = new $ticketStoreClass($casconfig);

    $ticketcontent = $ticketStore->getTicket($ticket);

    if (!is_null($ticketcontent)) {
        $ticketStore->removeTicket($ticket);

        $usernamefield = $casconfig->getValue('attrname', 'eduPersonPrincipalName');

        if (array_key_exists($usernamefield, $ticketcontent)) {
            echo generateCas10SuccessContent('yes', $ticketcontent[$usernamefield][0]);
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
