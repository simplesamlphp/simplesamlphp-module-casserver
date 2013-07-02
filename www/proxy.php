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

$legal_target_service_urls = $casconfig->getValue('legal_target_service_urls', array());

if (array_key_exists('targetService', $_GET) && checkServiceURL($_GET['targetService'], $legal_target_service_urls) && array_key_exists('pgt', $_GET)) {
    $proxyGrantingTicketId = sanitize($_GET['pgt']);
    $targetService = sanitize($_GET['targetService']);

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
    echo $protocol->getProxyFailureResponse('INVALID_REQUEST', 'Missing target service parameter [targetService]');
} else if (!checkServiceURL($_GET['targetService'], $legal_target_service_urls)) {
    echo $protocol->getProxyFailureResponse('INVALID_REQUEST', 'Target service parameter not listed as a legal service: [targetService] = ' . $_GET['targetService']);
} else {
    echo $protocol->getProxyFailureResponse('INVALID_REQUEST', 'Missing proxy granting ticket parameter: [pgt]');
}
?>