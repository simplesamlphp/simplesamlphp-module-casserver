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
*  gateway
*  entityId
*  scope
*  language
*/

require_once 'utility/urlUtils.php';

if (isset($_GET['service'])) {
    $service = sanitize($_GET['service']);
}

$forceAuthn = isset($_GET['renew']) && sanitize($_GET['renew']);
$isPassive = isset($_GET['gateway']) && sanitize($_GET['gateway']);

$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

$legal_service_urls = $casconfig->getValue('legal_service_urls');

if (isset($service) && !checkServiceURL($service, $legal_service_urls))
    throw new Exception('Service parameter provided to CAS server is not listed as a legal service: [service] = ' . $service);

$as = new SimpleSAML_Auth_Simple($casconfig->getValue('authsource'));

$session = SimpleSAML_Session::getInstance();

if (array_key_exists('scope', $_GET) && is_string($_GET['scope'])) {
    $scopes = $casconfig->getValue('scopes', array());

    if (array_key_exists($_GET['scope'], $scopes)) {
        $idpList = $scopes[$_GET['scope']];
    } else {
        throw new Exception('Scope parameter provided to CAS server is not listed as legal scope: [scope] = ' . $_GET['scope']);
    }
}

if (array_key_exists('language', $_GET) && is_string($_GET['language'])) {
    SimpleSAML_XHTML_Template::setLanguageCookie($_GET['language']);
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

    if (array_key_exists('language', $_GET)) {
        $query['language'] = is_string($_GET['language']) ? $_GET['language'] : NULL;
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

    if (isset($idpList)) {
        if (sizeof($idpList) > 1) {
            $params['saml:IDPList'] = $idpList;
        } else {
            $params['saml:idp'] = $idpList[0];
        }
    }

    $as->login($params);
}

if (!is_array($sessionTicket) || $forceAuthn) {
    $sessionTicket = $ticketFactory->createSessionTicket($session->getSessionId(), time() + $session->remainingTime());

    $ticketStore->addTicket($sessionTicket);
}

$parameters = array();

if (array_key_exists('language', $_GET)) {
    $oldLanguagePreferred = SimpleSAML_XHTML_Template::getLanguageCookie();

    if (isset($oldLanguagePreferred)) {
        $parameters['language'] = $oldLanguagePreferred;
    } else {
        if (is_string($_GET['language'])) {
            $parameters['language'] = $_GET['language'];
        }
    }
}

if (isset($service)) {
    $attributes = $as->getAttributes();

    $casUsernameAttribute = $casconfig->getValue('attrname', 'eduPersonPrincipalName');

    $userName = $attributes[$casUsernameAttribute][0];

    if ($casconfig->getValue('attributes', true)) {
        $attributesToTransfer = $casconfig->getValue('attributes_to_transfer', array());

        if (sizeof($attributesToTransfer) > 0) {
            $casAttributes = array();

            foreach ($attributesToTransfer as $key) {
                if (array_key_exists($key, $attributes)) {
                    $casAttributes[$key] = $attributes[$key];
                }
            }
        } else {
            $casAttributes = $attributes;
        }
    } else {
        $casAttributes = array();
    }

    $serviceTicket = $ticketFactory->createServiceTicket(array('service' => $service,
        'forceAuthn' => $forceAuthn,
        'userName' => $userName,
        'attributes' => $casAttributes,
        'proxies' => array(),
        'sessionId' => $sessionTicket['id']));

    $ticketStore->addTicket($serviceTicket);

    $parameters['ticket'] = $serviceTicket['id'];

    SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::addURLparameter($_GET['service'], $parameters));
} else {
    SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::addURLparameter(SimpleSAML_Module::getModuleURL('sbcasserver/loggedIn.php'), $parameters));
}
?>
