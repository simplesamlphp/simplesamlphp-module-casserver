<?php
/*
 * Incomming parameters:
 *  url
 *  ticket
 *
 */

if (!array_key_exists('url', $_GET))
    throw new Exception('Required URL query parameter [url] not provided. (CAS Server)');

$url = $_GET['url'];

/* Load simpleSAMLphp, configuration and metadata */
$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

if (!$casconfig->getValue('enable_logout', false)) {
    SimpleSAML_Logger::debug('sbcasserver:logout: logout disabled in module_sbcasserver.php');

    throw new Exception('Logout not allowed');
}

$as = new SimpleSAML_Auth_Simple($casconfig->getValue('authsource'));

$session = SimpleSAML_Session::getInstance();

if (!is_null($session)) {
    $ticketStoreConfig = $casconfig->getValue('ticketstore', array('class' => 'sbcasserver:FileSystemTicketStore'));
    $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_Ticket');
    $ticketStore = new $ticketStoreClass($casconfig);

    $ticketStore->deleteTicket($session->getSessionId());
}

if ($as->isAuthenticated()) {
    SimpleSAML_Logger::debug('sbcasserver:logout: performing a real logout');

    if ($casconfig->getValue('skip_logout_page', false)) {
        $as->logout($url);
    } else {
        $as->logout(SimpleSAML_Utilities::addURLparameter(SimpleSAML_Module::getModuleURL('sbcasserver/loggedOut.php'), array('url' => $url)));
    }
} else {
    SimpleSAML_Logger::debug('sbcasserver:logout: no session to log out of, performing redirect');

    if ($casconfig->getValue('skip_logout_page', false)) {
        SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::addURLparameter($url, array()));
    } else {
        SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::addURLparameter(SimpleSAML_Module::getModuleURL('sbcasserver/loggedOut.php'), array('url' => $url)));
    }
}
?>

