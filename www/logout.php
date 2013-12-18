<?php
/*
*    simpleSAMLphp-sbcasserver is a CAS 1.0 and 2.0 compliant CAS server in the form of a simpleSAMLphp module
*
*    Copyright (C) 2013  Bjorn R. Jensen
*
*    This library is free software; you can redistribute it and/or
*    modify it under the terms of the GNU Lesser General Public
*    License as published by the Free Software Foundation; either
*    version 2.1 of the License, or (at your option) any later version.
*
*    This library is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
*    Lesser General Public License for more details.
*
*    You should have received a copy of the GNU Lesser General Public
*    License along with this library; if not, write to the Free Software
*    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*
* Incoming parameters:
*  ticket
*  url     - optional if a logout page is displayed
*/

/* Load simpleSAMLphp, configuration and metadata */
$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

if (!$casconfig->getValue('enable_logout', false)) {
    SimpleSAML_Logger::debug('sbcasserver:logout: logout disabled in module_sbcasserver.php');

    throw new Exception('Logout not allowed');
}

$skipLogoutPage = $casconfig->getValue('skip_logout_page', false);

if ($skipLogoutPage && !array_key_exists('url', $_GET))
    throw new Exception('Required URL query parameter [url] not provided. (CAS Server)');

/* Load simpleSAMLphp metadata */

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
        $as->logout($_GET['url']);
    } else {
        $as->logout(SimpleSAML_Utilities::addURLparameter(SimpleSAML_Module::getModuleURL('sbcasserver/loggedOut.php'),
            array_key_exists('url', $_GET) ? array('url' => $_GET['url']) : array()));
    }
} else {
    SimpleSAML_Logger::debug('sbcasserver:logout: no session to log out of, performing redirect');

    if ($casconfig->getValue('skip_logout_page', false)) {
        SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::addURLparameter($_GET['url'], array()));
    } else {
        SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::addURLparameter(SimpleSAML_Module::getModuleURL('sbcasserver/loggedOut.php'),
            array_key_exists('url', $_GET) ? array('url' => $_GET['url']) : array()));
    }
}
?>

