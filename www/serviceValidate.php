<?php

/*
 * Incomming parameters:
 *  service
 *  renew
 *  ticket
 *
 */

if (array_key_exists('service', $_GET)) {
    $service = preg_replace('/;jsessionid=.*/','',$_GET['service']);
    $ticket = $_GET['ticket'];
    $forceAuthn = isset($_GET['renew']) && $_GET['renew'];
} else {
    throw new Exception('Required URL query parameter [service] not provided. (CAS Server)');
}

try {
    /* Load simpleSAMLphp, configuration and metadata */
    $casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

    $ticketStoreConfig = $casconfig->getValue('ticketstore');
    $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_TicketStore');
    $ticketStore = new $ticketStoreClass($casconfig);

    $ticketcontent = $ticketStore->getTicket($ticket);

    if (!is_null($ticketcontent)) {
        $ticketStore->removeTicket($ticket);

        $usernamefield = $casconfig->getValue('attrname', 'eduPersonPrincipalName');
        $dosendattributes = $casconfig->getValue('attributes', FALSE);

        $attributes = $ticketcontent['attributes'];

        if ($ticketcontent['service'] == $service && $ticketcontent['forceAuthn'] == $forceAuthn && array_key_exists($usernamefield, $attributes)) {
            $base64encodeQ = $casconfig->getValue('base64attributes', false);

            if (isset($_GET['pgtUrl'])) {
                $pgtUrl = $_GET['pgtUrl'];

                $proxyGrantingTicket = array(
                    'attributes' => $attributes,
                    'forceAuthn' => false,
                    'proxies' => array_merge(array($service), $ticketcontent['proxies']),
                    'validbefore' => time() + 60);

                $proxyGrantingTicketId = $ticketStore->createProxyGrantingTicket($proxyGrantingTicket);

                try {
                    SimpleSAML_Utilities::fetch($pgtUrl . '?pgtIou=' . $proxyGrantingTicketId['iou'] . '&pgtId=' . $proxyGrantingTicketId['id']);
                } catch (Exception $e) {
                    $ticketStore->removeTicket($proxyGrantingTicketId['id']);
                }
            }

            echo workAroundForBuggyJasigXmlParser(generateCas20SuccessContent($attributes[$usernamefield][0],
                isset($proxyGrantingTicketId) ? $proxyGrantingTicketId['iou'] : null,
                $dosendattributes ? $attributes : array(), $base64encodeQ)->saveXML());
        } else {
            if ($ticketcontent['service'] != $service) {
                echo workAroundForBuggyJasigXmlParser(generateCas20FailureContent('INVALID_SERVICE', 'Expected: ' .
                    $ticketcontent['service'] . ' was: ' . $service)->saveXML());
            } else if ($ticketcontent['forceAuthn'] == $forceAuthn) {
                echo workAroundForBuggyJasigXmlParser(generateCas20FailureContent('INVALID_TICKET', 'Mismatching renew. Expected: ' .
                    $ticketcontent['forceAuthn'] . ' was: ' . $forceAuthn)->saveXML());
            } else {
                echo workAroundForBuggyJasigXmlParser(generateCas20FailureContent('INTERNAL_ERROR', 'Missing user name, attribute: ' .
                    $usernamefield . ' not found.')->saveXML());
            }
        }
    } else {
        echo workAroundForBuggyJasigXmlParser(generateCas20FailureContent('INVALID_TICKET', 'ticket: ' . $ticket . ' not recognized')->saveXML());
    }

} catch (Exception $e) {
    echo workAroundForBuggyJasigXmlParser(generateCas20FailureContent('INTERNAL_ERROR', $e->getMessage())->saveXML());
}

function workAroundForBuggyJasigXmlParser($xmlString)
{ // when will people stop hand coding xml handling....?
    return str_replace('><', '>' . PHP_EOL . '<', str_replace(PHP_EOL, '', $xmlString));
}

function generateCas20Attribute($xmlDocument, $attributeName, $attributeValue, $base64encode)
{
    return $xmlDocument->createElement('cas:' . $attributeName, $base64encode ? base64_encode($attributeValue) : $attributeValue);
}

function generateCas20SuccessContent($userName, $proxyGrantingTicketIOU, $attributes, $base64encode)
{
    $xmlDocument = new DOMDocument("1.0");

    $root = $xmlDocument->createElement("cas:serviceResponse");
    $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cas', 'http://www.yale.edu/tp/cas');

    $casUser = $xmlDocument->createElement('cas:user', $userName);

    $casSuccess = $xmlDocument->createElement('cas:authenticationSuccess');
    $casSuccess->appendChild($casUser);

    if (is_string($proxyGrantingTicketIOU)) {
        $casSuccess->appendChild($xmlDocument->createElement("cas:proxyGrantingTicket", $proxyGrantingTicketIOU));
    }

    if (count($attributes) > 0) {
        $casAttributes = $xmlDocument->createElement('cas:attributes');

        foreach ($attributes as $name => $values) {
            if (!preg_match('/urn:oid/', $name)) {
                foreach ($values as $value) {
                    $casAttributes->appendChild(generateCas20Attribute($xmlDocument, $name, $value, $base64encode));
                }
            }
        }

        $casSuccess->appendChild($casAttributes);
    }

    $root->appendChild($casSuccess);
    $xmlDocument->appendChild($root);

    return $xmlDocument;
}

function generateCas20FailureContent($errorCode, $explanation)
{
    $xmlDocument = new DOMDocument("1.0");

    $root = $xmlDocument->createElement("cas:serviceResponse");
    $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cas', 'http://www.yale.edu/tp/cas');

    $casFailureCode = $xmlDocument->createAttribute('code');
    $casFailureCode->value = $errorCode;

    $casFailure = $xmlDocument->createElement('cas:authenticationFailure', $explanation);
    $casFailure->appendChild($casFailureCode);

    $root->appendChild($casFailure);

    $xmlDocument->appendChild($root);

    return $xmlDocument;
}
?>