<?php
/*
*    simpleSAMLphp-casserver is a CAS 1.0 and 2.0 compliant CAS server in the form of a simpleSAMLphp module
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
*  targetService
*  pgt
*
*/

require_once 'utility/urlUtils.php';

$casconfig = SimpleSAML_Configuration::getConfig('module_casserver.php');

/* Instantiate protocol handler */
$protocolClass = SimpleSAML\Module::resolveClass('casserver:Cas20', 'Cas_Protocol');
$protocol = new $protocolClass($casconfig);

$legal_target_service_urls = $casconfig->getValue('legal_target_service_urls', array());

if (array_key_exists('targetService', $_GET) &&
    checkServiceURL(sanitize($_GET['targetService']), $legal_target_service_urls) && array_key_exists('pgt', $_GET)
) {

    $ticketStoreConfig = $casconfig->getValue('ticketstore', array('class' => 'casserver:FileSystemTicketStore'));
    $ticketStoreClass = SimpleSAML\Module::resolveClass($ticketStoreConfig['class'], 'Cas_Ticket');
    $ticketStore = new $ticketStoreClass($casconfig);

    $ticketFactoryClass = SimpleSAML\Module::resolveClass('casserver:TicketFactory', 'Cas_Ticket');
    $ticketFactory = new $ticketFactoryClass($casconfig);

    $proxyGrantingTicket = $ticketStore->getTicket($_GET['pgt']);

    if (!is_null($proxyGrantingTicket) && $ticketFactory->isProxyGrantingTicket($proxyGrantingTicket)) {
        $sessionTicket = $ticketStore->getTicket($proxyGrantingTicket['sessionId']);

        if (!is_null($sessionTicket) && $ticketFactory->isSessionTicket($sessionTicket) &&
            !$ticketFactory->isExpired($sessionTicket)
        ) {
            $proxyTicket = $ticketFactory->createProxyTicket(array('service' => $_GET['targetService'],
                'forceAuthn' => $proxyGrantingTicket['forceAuthn'],
                'attributes' => $proxyGrantingTicket['attributes'],
                'proxies' => $proxyGrantingTicket['proxies'],
                'sessionId' => $proxyGrantingTicket['sessionId']));

            $ticketStore->addTicket($proxyTicket);

            echo $protocol->getProxySuccessResponse($proxyTicket['id']);
        } else {
            $message = 'Ticket ' . var_export($_GET['pgt'], true) . ' has expired';

            SimpleSAML\Logger::debug('casserver:' . $message);

            echo $protocol->getProxyFailureResponse('BAD_PGT', $message);
        }
    } elseif (!$ticketFactory->isProxyGrantingTicket($proxyGrantingTicket)) {
        $message = 'Not a valid proxy granting ticket id: ' . var_export($_GET['pgt'], true);

        SimpleSAML\Logger::debug('casserver:' . $message);

        echo $protocol->getProxyFailureResponse('BAD_PGT', $message);
    } else {
        $message = 'Ticket ' . var_export($_GET['pgt'], true) . ' not recognized';

        SimpleSAML\Logger::debug('casserver:' . $message);

        echo $protocol->getProxyFailureResponse('BAD_PGT', $message);
    }
} elseif (!array_key_exists('targetService', $_GET)) {
    $message = 'Missing target service parameter [targetService]';

    SimpleSAML\Logger::debug('casserver:' . $message);

    echo $protocol->getProxyFailureResponse('INVALID_REQUEST', $message);
} elseif (!checkServiceURL(sanitize($_GET['targetService']), $legal_target_service_urls)) {
    $message = 'Target service parameter not listed as a legal service: [targetService] = '
        . var_export($_GET['targetService'], true);

    SimpleSAML\Logger::debug('casserver:' . $message);

    echo $protocol->getProxyFailureResponse('INVALID_REQUEST', $message);
} else {
    $message = 'Missing proxy granting ticket parameter: [pgt]';

    SimpleSAML\Logger::debug('casserver:' . $message);

    echo $protocol->getProxyFailureResponse('INVALID_REQUEST', $message);
}
