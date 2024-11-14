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
 *  gateway
 *  entityId
 *  scope
 *  language
 */

declare(strict_types=1);

use SimpleSAML\Auth\ProcessingChain;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\Locale\Language;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\AttributeExtractor;
use SimpleSAML\Module\casserver\Cas\Protocol\SamlValidateResponder;
use SimpleSAML\Module\casserver\Cas\ServiceValidator;
use SimpleSAML\Session;
use SimpleSAML\Utils;

require_once('utility/urlUtils.php');

$forceAuthn = isset($_GET['renew']) && $_GET['renew'];
$isPassive = isset($_GET['gateway']) && $_GET['gateway'];
$authProcId = $_GET[ProcessingChain::AUTHPARAM] ?? null;
// Determine if the client wants us to post or redirect the response. Default is redirect.
$redirect = !(isset($_GET['method']) && $_GET['method'] === 'POST');
$serviceUrl = $_GET['service'] ?? $_GET['TARGET'] ?? null;

$casconfig = Configuration::getConfig('module_casserver.php');
$serviceValidator = new ServiceValidator($casconfig);

if (isset($serviceUrl)) {
    $serviceCasConfig = $serviceValidator->checkServiceURL(sanitize($serviceUrl));
    if (isset($serviceCasConfig)) {
        // Override the cas configuration to use for this service
        $casconfig = $serviceCasConfig;
    } else {
        $message = 'Service parameter provided to CAS server is not listed as a legal service: [service] = ' .
            var_export($serviceUrl, true);
        Logger::debug('casserver:' . $message);

        throw new \Exception($message);
    }
}

if (array_key_exists('scope', $_GET) && is_string($_GET['scope'])) {
    $scopes = $casconfig->getOptionalValue('scopes', []);

    if (array_key_exists($_GET['scope'], $scopes)) {
        $idpList = $scopes[$_GET['scope']];
    } else {
        $message = 'Scope parameter provided to CAS server is not listed as legal scope: [scope] = ' .
            var_export($_GET['scope'], true);
        Logger::debug('casserver:' . $message);

        throw new \Exception($message);
    }
}

if (array_key_exists('language', $_GET) && is_string($_GET['language'])) {
    Language::setLanguageCookie($_GET['language']);
}

/** Initializations */

// AuthSource Simple
$as = new Simple($casconfig->getValue('authsource'));

// Ticket Store
$ticketStoreConfig = $casconfig->getOptionalValue('ticketstore', ['class' => 'casserver:FileSystemTicketStore']);
$ticketStoreClass = Module::resolveClass($ticketStoreConfig['class'], 'Cas\Ticket');
/** @var $ticketStore TicketStore */
/** @psalm-suppress InvalidStringClass */
$ticketStore = new $ticketStoreClass($casconfig);

// Ticket Factory
$ticketFactoryClass = Module::resolveClass('casserver:TicketFactory', 'Cas\Factories');
/** @var $ticketFactory TicketFactory */
/** @psalm-suppress InvalidStringClass */
$ticketFactory = new $ticketFactoryClass($casconfig);

// Processing Chain Factory
$processingChaingFactoryClass = Module::resolveClass('casserver:ProcessingChainFactory', 'Cas\Factories');
/** @var $processingChainFactory ProcessingChainFactory */
/** @psalm-suppress InvalidStringClass */
$processingChainFactory = new $processingChaingFactoryClass($casconfig);

// Attribute Extractor
$attributeExtractor = new AttributeExtractor($casconfig, $processingChainFactory);

// HTTP Utils
$httpUtils = new Utils\HTTP();
$session = Session::getSessionFromRequest();

$sessionTicket = $ticketStore->getTicket($session->getSessionId());
$sessionRenewId = $sessionTicket ? $sessionTicket['renewId'] : null;
$requestRenewId = $_REQUEST['renewId'] ?? null;
// Parse the query parameters and return them in an array
$query = parseQueryParameters($sessionTicket);
// Construct the ReturnTo URL
$returnUrl = $httpUtils->getSelfURLNoQuery() . '?' . http_build_query($query);

// Authenticate
if (
    !$as->isAuthenticated() || ($forceAuthn && $sessionRenewId != $requestRenewId)
) {
    $params = [
        'ForceAuthn' => $forceAuthn,
        'isPassive' => $isPassive,
        'ReturnTo' => $returnUrl,
    ];

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

$sessionExpiry = $as->getAuthData('Expire');

if (!is_array($sessionTicket) || $forceAuthn) {
    $sessionTicket = $ticketFactory->createSessionTicket($session->getSessionId(), $sessionExpiry);
    $ticketStore->addTicket($sessionTicket);
}

$parameters = [];

if (array_key_exists('language', $_GET)) {
    $oldLanguagePreferred = Language::getLanguageCookie();

    if (isset($oldLanguagePreferred)) {
        $parameters['language'] = $oldLanguagePreferred;
    } elseif (is_string($_GET['language'])) {
        $parameters['language'] = $_GET['language'];
    }
}

// I am already logged in. Redirect to the logged in endpoint
if (!isset($serviceUrl) && $authProcId === null) {
    // LOGGED IN
    $httpUtils->redirectTrustedURL(
        $httpUtils->addURLParameters(Module::getModuleURL('casserver/loggedIn.php'), $parameters),
    );
}

$defaultTicketName = isset($_GET['service']) ? 'ticket' : 'SAMLart';
$ticketName = $casconfig->getOptionalValue('ticketName', $defaultTicketName);

// Get the state.
// If we come from an authproc filter, we will load the state from the stateId.
// If not, we will get the state from the AuthSource Data
try {
    $state = $authProcId !== null ?
        $attributeExtractor->manageState($authProcId) :
        $as->getAuthDataArray();
} catch (\SimpleSAML\Error\NoState $e) {
    var_export($e, true);
    die();
}
// Attribute Handler
$state['ReturnTo'] = $returnUrl;
if ($authProcId !== null) {
    $state[ProcessingChain::AUTHPARAM] = $authProcId;
}
try {
    $mappedAttributes = $attributeExtractor->extractUserAndAttributes($state);
} catch (\SimpleSAML\Error\Exception $e) {
    var_export($e, true);
    die();
}

$serviceTicket = $ticketFactory->createServiceTicket([
                                                         'service' => $serviceUrl,
                                                         'forceAuthn' => $forceAuthn,
                                                         'userName' => $mappedAttributes['user'],
                                                         'attributes' => $mappedAttributes['attributes'],
                                                         'proxies' => [],
                                                         'sessionId' => $sessionTicket['id'],
                                                     ]);

$ticketStore->addTicket($serviceTicket);

$parameters[$ticketName] = $serviceTicket['id'];

$validDebugModes = ['true', 'samlValidate'];

// DEBUG MODE
if (
    array_key_exists('debugMode', $_GET) &&
    in_array($_GET['debugMode'], $validDebugModes, true) &&
    $casconfig->getOptionalBoolean('debugMode', false)
) {
    if ($_GET['debugMode'] === 'samlValidate') {
        $samlValidate = new SamlValidateResponder();
        $samlResponse = $samlValidate->convertToSaml($serviceTicket);
        $soap = $samlValidate->wrapInSoap($samlResponse);
        echo '<pre>' . htmlspecialchars(strval($soap)) . '</pre>';
    } else {
        $method = 'serviceValidate';
        // Fake some options for validateTicket
        $_GET[$ticketName] = $serviceTicket['id'];
        // We want to capture the output from echo used in validateTicket
        ob_start();
        require_once 'utility/validateTicket.php';
        $casResponse = ob_get_contents();
        ob_end_clean();
        echo '<pre>' . htmlspecialchars($casResponse) . '</pre>';
    }
} elseif ($redirect) {
    // GET
    $httpUtils->redirectTrustedURL($httpUtils->addURLParameters($serviceUrl, $parameters));
} else {
    // POST
    try {
        $httpUtils->submitPOSTData($serviceUrl, $parameters);
    } catch (\SimpleSAML\Error\Exception $e) {
        var_export($e, true);
        die();
    }
}
