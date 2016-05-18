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
*  service
*  renew
*  ticket
*  pgtUrl
*
*/

require_once 'urlUtils.php';

/* Load simpleSAMLphp, configuration and metadata */
$casconfig = SimpleSAML_Configuration::getConfig('module_casserver.php');

/* Instantiate protocol handler */
$protocolClass = SimpleSAML_Module::resolveClass('casserver:Cas20', 'Cas_Protocol');
$protocol = new $protocolClass($casconfig);

if (array_key_exists('service', $_GET) && array_key_exists('ticket', $_GET)) {
    $forceAuthn = isset($_GET['renew']) && $_GET['renew'];

    try {
        $ticketStoreConfig = $casconfig->getValue('ticketstore', array('class' => 'casserver:FileSystemTicketStore'));
        $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_Ticket');
        $ticketStore = new $ticketStoreClass($casconfig);

        $ticketFactoryClass = SimpleSAML_Module::resolveClass('casserver:TicketFactory', 'Cas_Ticket');
        $ticketFactory = new $ticketFactoryClass($casconfig);

        $serviceTicket = $ticketStore->getTicket($_GET['ticket']);

        if (!is_null($serviceTicket) && ($ticketFactory->isServiceTicket($serviceTicket) ||
                ($ticketFactory->isProxyTicket($serviceTicket) && $method == 'proxyValidate'))
        ) {
            $ticketStore->deleteTicket($_GET['ticket']);

            $attributes = $serviceTicket['attributes'];

            if (!$ticketFactory->isExpired($serviceTicket) &&
                sanitize($serviceTicket['service']) == sanitize($_GET['service']) &&
                (!$forceAuthn || $serviceTicket['forceAuthn'])
            ) {

                $protocol->setAttributes($attributes);

                if (isset($_GET['pgtUrl'])) {
                    $sessionTicket = $ticketStore->getTicket($serviceTicket['sessionId']);

                    $pgtUrl = $_GET['pgtUrl'];

                    if (!is_null($sessionTicket) && $ticketFactory->isSessionTicket($sessionTicket) &&
                        !$ticketFactory->isExpired($sessionTicket)
                    ) {
                        $proxyGrantingTicket = $ticketFactory->createProxyGrantingTicket(array(
                            'userName' => $serviceTicket['userName'],
                            'attributes' => $attributes,
                            'forceAuthn' => false,
                            'proxies' => array_merge(array($_GET['service']), $serviceTicket['proxies']),
                            'sessionId' => $serviceTicket['sessionId']
                        ));
                        try {
                            SimpleSAML_Utilities::fetch($pgtUrl . '?pgtIou=' . $proxyGrantingTicket['iou']
                                . '&pgtId=' . $proxyGrantingTicket['id']);

                            $protocol->setProxyGrantingTicketIOU($proxyGrantingTicket['iou']);

                            $ticketStore->addTicket($proxyGrantingTicket);
                        } catch (Exception $e) {
                        }
                    }
                }

                echo $protocol->getValidateSuccessResponse($serviceTicket['userName']);
            } else {
                if ($ticketFactory->isExpired($serviceTicket)) {
                    $message = 'Ticket ' . var_export($_GET['ticket'], true) . ' has expired';

                    SimpleSAML_Logger::debug('casserver:' . $message);

                    echo $protocol->getValidateFailureResponse('INVALID_TICKET', $message);
                } else {
                    if (sanitize($serviceTicket['service']) != sanitize($_GET['service'])) {
                        $message = 'Mismatching service parameters: expected '
                            . var_export($serviceTicket['service'], true)
                            . ' but was: ' . var_export($_GET['service'], true);

                        SimpleSAML_Logger::debug('casserver:' . $message);

                        echo $protocol->getValidateFailureResponse('INVALID_SERVICE', $message);
                    } else {
                        if ($serviceTicket['forceAuthn'] != $forceAuthn) {
                            $message = 'Ticket was issue from single sign on session';

                            SimpleSAML_Logger::debug('casserver:' . $message);

                            echo $protocol->getValidateFailureResponse('INVALID_TICKET', $message);
                        } else {
                            SimpleSAML_Logger::error('casserver:' . $method . ': internal server error.');

                            echo $protocol->getValidateFailureResponse('INTERNAL_ERROR', 'Unknown internal error');
                        }
                    }
                }
            }
        } else {
            if (is_null($serviceTicket)) {
                $message = 'Ticket ' . var_export($_GET['ticket'], true) . ' not recognized';

                SimpleSAML_Logger::debug('casserver:' . $message);

                echo $protocol->getValidateFailureResponse('INVALID_TICKET', $message);
            } else {
                if ($ticketFactory->isProxyTicket($serviceTicket) && $method == 'serviceValidate') {
                    $message = 'Ticket ' . var_export($_GET['ticket'], true)
                        . ' is a proxy ticket. Use proxyValidate instead.';

                    SimpleSAML_Logger::debug('casserver:' . $message);

                    echo $protocol->getValidateFailureResponse('INVALID_TICKET', $message);
                } else {
                    $message = 'Ticket ' . var_export($_GET['ticket'], true) . ' is not a service ticket';

                    SimpleSAML_Logger::debug('casserver:' . $message);

                    echo $protocol->getValidateFailureResponse('INVALID_TICKET', $message);
                }
            }
        }

    } catch (Exception $e) {
        SimpleSAML_Logger::error('casserver:serviceValidate: internal server error. '
            . var_export($e->getMessage(), true));

        echo $protocol->getValidateFailureResponse('INTERNAL_ERROR', $e->getMessage());
    }
} else {
    if (!array_key_exists('service', $_GET)) {
        $message = 'Missing service parameter: [service]';

        SimpleSAML_Logger::debug('casserver:' . $message);

        echo $protocol->getValidateFailureResponse('INVALID_REQUEST', $message);
    } else {
        $message = 'Missing ticket parameter: [ticket]';

        SimpleSAML_Logger::debug('casserver:' . $message);

        echo $protocol->getValidateFailureResponse('INVALID_REQUEST', $message);
    }
}
