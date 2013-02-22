<?php

class sspmod_sbcasserver_Cas_Protocol_Cas20
{
    private $sendAttributes;
    private $base64EncodeAttributes;
    private $attributes = array();
    private $proxyGrantingTicketIOU = null;

    public function __construct($config)
    {
        $this->sendAttributes = $config->getValue('attributes', false);
        $this->base64EncodeAttributes = $config->getValue('base64attributes', false);
    }

    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function setProxyGrantingTicketIOU($proxyGrantingTicketIOU)
    {
        $this->proxyGrantingTicketIOU = $proxyGrantingTicketIOU;
    }

    public function getProxyGrantingTicketIOU()
    {
        return $this->proxyGrantingTicketIOU;
    }

    public function getSuccessResponse($username)
    {
        $xmlDocument = new DOMDocument("1.0");

        $root = $xmlDocument->createElement("cas:serviceResponse");
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cas', 'http://www.yale.edu/tp/cas');

        $casUser = $xmlDocument->createElement('cas:user', $username);

        $casSuccess = $xmlDocument->createElement('cas:authenticationSuccess');
        $casSuccess->appendChild($casUser);

        if (is_string($this->proxyGrantingTicketIOU)) {
            $casSuccess->appendChild($xmlDocument->createElement("cas:proxyGrantingTicket", $this->proxyGrantingTicketIOU));
        }

        if ($this->sendAttributes && count($this->attributes) > 0) {
            $casAttributes = $xmlDocument->createElement('cas:attributes');

            foreach ($this->attributes as $name => $values) {
                if (!preg_match('/urn:oid/', $name)) {
                    foreach ($values as $value) {
                        $casAttributes->appendChild($this->generateCas20Attribute($xmlDocument, $name, $value));
                    }
                }
            }

            $casSuccess->appendChild($casAttributes);
        }

        $root->appendChild($casSuccess);
        $xmlDocument->appendChild($root);

        return $this->workAroundForBuggyJasigXmlParser($xmlDocument->saveXML());
    }

    public function getFailureResponse($errorCode, $explanation)
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

        return $this->workAroundForBuggyJasigXmlParser($xmlDocument->saveXML());
    }

    private function workAroundForBuggyJasigXmlParser($xmlString)
    { // when will people stop hand coding xml handling....?
        return str_replace('><', '>' . PHP_EOL . '<', str_replace(PHP_EOL, '', $xmlString));
    }

    private function generateCas20Attribute($xmlDocument, $attributeName, $attributeValue)
    {
        return $xmlDocument->createElement('cas:' . $attributeName, $this->base64EncodeAttributes ?
            base64_encode($attributeValue) : $attributeValue);
    }
}

?>