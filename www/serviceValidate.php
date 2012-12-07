<?php
/*
 * Incomming parameters:
 *  service
 *  renew
 *  ticket
 *
 */

if (!array_key_exists('service', $_GET))
    throw new Exception('Required URL query parameter [service] not provided. (CAS Server)');

$service = $_GET['service'];

if (!array_key_exists('ticket', $_GET))
    throw new Exception('Required URL query parameter [ticket] not provided. (CAS Server)');

$ticket = $_GET['ticket'];

$renew = FALSE;

if (array_key_exists('renew', $_GET)) {
    $renew = TRUE;
}

try {
    /* Load simpleSAMLphp, configuration and metadata */
    $casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

    /* Instantiate ticket store */
    $ticketStoreConfig = $casconfig->getValue('ticketstore');
    $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_TicketStore');
    $ticketStore = new $ticketStoreClass($casconfig);

    $base64encodeQ = $casconfig->getValue('base64attributes', false);

    $ticketcontent = $ticketStore->getTicket($ticket);

    if (!is_null($ticketcontent)) {
        $ticketStore->removeTicket($ticket);

        $usernamefield = $casconfig->getValue('attrname', 'eduPersonPrincipalName');
        $dosendattributes = $casconfig->getValue('attributes', FALSE);

        if (array_key_exists($usernamefield, $ticketcontent)) {
            echo workAroundForBuggyJasigXmlParser(generateCas20SuccessContent($ticketcontent[$usernamefield][0], $dosendattributes ? $ticketcontent : array(), $base64encodeQ)->saveXML());
        } else {
            echo workAroundForBuggyJasigXmlParser(generateCas20FailureContent('INTERNAL_ERROR', 'Missing user name, attribute: ' . $usernamefield . ' not found.')->saveXML());
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

function generateCas20SuccessContent($userName, $attributes, $base64encode)
{
    $xmlDocument = new DOMDocument("1.0");

    $root = $xmlDocument->createElement("cas:serviceResponse");
    $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cas', 'http://www.yale.edu/tp/cas');

    $casUser = $xmlDocument->createElement('cas:user', $userName);

    $casSuccess = $xmlDocument->createElement('cas:authenticationSuccess');
    $casSuccess->appendChild($casUser);

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

