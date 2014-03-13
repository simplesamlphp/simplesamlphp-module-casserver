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
*  service
*  renew
*  ticket
*
*/

require_once 'utility/urlUtils.php';

/* Load simpleSAMLphp, configuration and metadata */
$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

/* Instantiate protocol handler */
$protocolClass = SimpleSAML_Module::resolveClass('sbcasserver:Cas10', 'Cas_Protocol');
$protocol = new $protocolClass($casconfig);

if (array_key_exists('service', $_GET) && array_key_exists('ticket', $_GET)) {
    $forceAuthn = isset($_GET['renew']) && $_GET['renew'];

    try {
        /* Instantiate ticket store */
        $ticketStoreConfig = $casconfig->getValue('ticketstore', array('class' => 'sbcasserver:FileSystemTicketStore'));
        $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_Ticket');
        $ticketStore = new $ticketStoreClass($casconfig);

        $ticketFactoryClass = SimpleSAML_Module::resolveClass('sbcasserver:TicketFactory', 'Cas_Ticket');
        $ticketFactory = new $ticketFactoryClass($casconfig);

        $serviceTicket = $ticketStore->getTicket($_GET['ticket']);

        if (!is_null($serviceTicket) && $ticketFactory->isServiceTicket($serviceTicket)) {

            $ticketStore->deleteTicket($_GET['ticket']);

            $usernameField = $casconfig->getValue('attrname', 'eduPersonPrincipalName');

            if (!$ticketFactory->isExpired($serviceTicket) &&
                sanitize($serviceTicket['service']) == sanitize($_GET['service']) &&
                (!$forceAuthn || $serviceTicket['forceAuthn']) &&
                array_key_exists($usernameField, $serviceTicket['attributes'])
            ) {
                echo $protocol->getValidateSuccessResponse($serviceTicket['attributes'][$usernameField][0]);
            } else if (!array_key_exists($usernameField, $serviceTicket['attributes'])) {
                SimpleSAML_Logger::debug('sbcasserver:validate: internal server error. Missing user name attribute: ' .
                var_export($usernameField, TRUE));

                echo $protocol->getValidateFailureResponse();
            } else {
                echo $protocol->getValidateFailureResponse();
            }
        } else {
            echo $protocol->getValidateFailureResponse();
        }
    } catch (Exception $e) {
        SimpleSAML_Logger::debug('sbcasserver:validate: internal server error. ' . var_export($e->getMessage(), TRUE));

        echo $protocol->getValidateFailureResponse();
    }
} else {
    echo $protocol->getValidateFailureResponse();
}
