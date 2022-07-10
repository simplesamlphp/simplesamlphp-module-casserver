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
 *
 */

require_once('utility/urlUtils.php');

/* Load simpleSAMLphp, configuration and metadata */
$casconfig = \SimpleSAML\Configuration::getConfig('module_casserver.php');

/* Instantiate protocol handler */
$protocolClass = \SimpleSAML\Module::resolveClass('casserver:Cas10', 'Cas\Protocol');
/** @psalm-suppress InvalidStringClass */
$protocol = new $protocolClass($casconfig);

if (array_key_exists('service', $_GET) && array_key_exists('ticket', $_GET)) {
    $forceAuthn = isset($_GET['renew']) && $_GET['renew'];

    try {
        /* Instantiate ticket store */
        $ticketStoreConfig = $casconfig->getOptionalValue(
            'ticketstore',
            ['class' => 'casserver:FileSystemTicketStore']
        );
        $ticketStoreClass = \SimpleSAML\Module::resolveClass($ticketStoreConfig['class'], 'Cas\Ticket');
        /** @psalm-suppress InvalidStringClass */
        $ticketStore = new $ticketStoreClass($casconfig);

        $ticketFactoryClass = \SimpleSAML\Module::resolveClass('casserver:TicketFactory', 'Cas\Ticket');
        /** @psalm-suppress InvalidStringClass */
        $ticketFactory = new $ticketFactoryClass($casconfig);

        $serviceTicket = $ticketStore->getTicket($_GET['ticket']);

        if (!is_null($serviceTicket) && $ticketFactory->isServiceTicket($serviceTicket)) {
            $ticketStore->deleteTicket($_GET['ticket']);

            $usernameField = $casconfig->getOptionalValue('attrname', 'eduPersonPrincipalName');

            if (
                !$ticketFactory->isExpired($serviceTicket) &&
                sanitize($serviceTicket['service']) == sanitize($_GET['service']) &&
                (!$forceAuthn || $serviceTicket['forceAuthn']) &&
                array_key_exists($usernameField, $serviceTicket['attributes'])
            ) {
                echo $protocol->getValidateSuccessResponse($serviceTicket['attributes'][$usernameField][0]);
            } else {
                if (!array_key_exists($usernameField, $serviceTicket['attributes'])) {
                    \SimpleSAML\Logger::error(sprintf(
                        'casserver:validate: internal server error. Missing user name attribute: %s',
                        var_export($usernameField, true)
                    ));

                    echo $protocol->getValidateFailureResponse();
                } else {
                    if ($ticketFactory->isExpired($serviceTicket)) {
                        $message = 'Ticket has ' . var_export($_GET['ticket'], true) . ' expired';
                    } else {
                        if (sanitize($serviceTicket['service']) == sanitize($_GET['service'])) {
                            $message = 'Mismatching service parameters: expected ' .
                                var_export($serviceTicket['service'], true) .
                                ' but was: ' . var_export($_GET['service'], true);
                        } else {
                            $message = 'Ticket was issue from single sign on session';
                        }
                    }
                    \SimpleSAML\Logger::debug('casserver:' . $message);

                    echo $protocol->getValidateFailureResponse();
                }
            }
        } else {
            if (is_null($serviceTicket)) {
                $message = 'ticket: ' . var_export($_GET['ticket'], true) . ' not recognized';
            } else {
                $message = 'ticket: ' . var_export($_GET['ticket'], true) . ' is not a service ticket';
            }

            \SimpleSAML\Logger::debug('casserver:' . $message);

            echo $protocol->getValidateFailureResponse();
        }
    } catch (\Exception $e) {
        \SimpleSAML\Logger::error('casserver:validate: internal server error. ' . var_export($e->getMessage(), true));

        echo $protocol->getValidateFailureResponse();
    }
} else {
    if (!array_key_exists('service', $_GET)) {
        $message = 'Missing service parameter: [service]';
    } else {
        $message = 'Missing ticket parameter: [ticket]';
    }

    SimpleSAML\Logger::debug('casserver:' . $message);

    echo $protocol->getValidateFailureResponse();
}
