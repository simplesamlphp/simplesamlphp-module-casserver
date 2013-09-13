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
*  service
*  renew
*  gateway
*  entityId
 * language
*/

require_once 'utility/urlUtils.php';

if (!array_key_exists('service', $_GET))
    throw new Exception('Required URL query parameter [service] not provided. (CAS Server)');

$service = sanitize($_GET['service']);

$forceAuthn = isset($_GET['renew']) && sanitize($_GET['renew']);
$isPassive = isset($_GET['gateway']) && sanitize($_GET['gateway']);

$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

$legal_service_urls = $casconfig->getValue('legal_service_urls');

if (!checkServiceURL($service, $legal_service_urls))
    throw new Exception('Service parameter provided to CAS server is not listed as a legal service: [service] = ' . $service);

$as = new SimpleSAML_Auth_Simple($casconfig->getValue('authsource'));

$session = SimpleSAML_Session::getInstance();

if (array_key_exists('language', $_GET)) {
    $oldLanguagePreferred = SimpleSAML_XHTML_Template::getLanguageCookie();

    if (!isset($oldLanguagePreferred)) {
        SimpleSAML_XHTML_Template::setLanguageCookie($_GET['language']);

        $language = $_GET['language'];
    } else {
        $language = $oldLanguagePreferred;
    }
}

$ticketStoreConfig = $casconfig->getValue('ticketstore', array('class' => 'sbcasserver:FileSystemTicketStore'));
$ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_Ticket');
$ticketStore = new $ticketStoreClass($casconfig);

$ticketFactoryClass = SimpleSAML_Module::resolveClass('sbcasserver:TicketFactory', 'Cas_Ticket');
$ticketFactory = new $ticketFactoryClass($casconfig);

$sessionTicket = $ticketStore->getTicket($session->getSessionId());
$sessionRenewId = $sessionTicket ? $sessionTicket['renewId'] : NULL;
$requestRenewId = isset($_REQUEST['renewId']) ? $_REQUEST['renewId'] : NULL;

if (!$as->isAuthenticated() || ($forceAuthn && $sessionRenewId != $requestRenewId)) {
    $query = array();

    if ($sessionRenewId && $forceAuthn) {
        $query['renewId'] = $sessionRenewId;
    }

    if (isset($_REQUEST['service'])) {
        $query['service'] = $_REQUEST['service'];
    }

    if (isset($_REQUEST['renew'])) {
        $query['renew'] = $_REQUEST['renew'];
    }

    if (isset($_REQUEST['gateway'])) {
        $query['gateway'] = $_REQUEST['gateway'];
    }

    if (isset($language)) {
        $query['language'] = $language;
    }

    $returnUrl = SimpleSAML_Utilities::selfURLNoQuery() . '?' . http_build_query($query);

    $params = array(
        'ForceAuthn' => $forceAuthn,
        'isPassive' => $isPassive,
        'ReturnTo' => $returnUrl,
    );

    if (isset($_GET['entityId'])) {
        $params['saml:idp'] = $_GET['entityId'];
    }

    $as->login($params);
}

if (!is_array($sessionTicket) || $forceAuthn) {
    $sessionTicket = $ticketFactory->createSessionTicket($session->getSessionId(), time() + $session->remainingTime());

    $ticketStore->addTicket($sessionTicket);
}

$attributes = $as->getAttributes();

$serviceTicket = $ticketFactory->createServiceTicket(array('service' => $service,
    'forceAuthn' => $forceAuthn,
    'attributes' => $attributes,
    'proxies' => array(),
    'sessionId' => $sessionTicket['id']));

$ticketStore->addTicket($serviceTicket);

$parameters = array('ticket' => $serviceTicket['id']);

if (isset($language)) {
    $parameters['language'] = $language;
}

SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::addURLparameter($_GET['service'], $parameters));
?>
