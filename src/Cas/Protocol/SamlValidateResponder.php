<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Cas\Protocol;

use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Shib13\AuthnResponse;
use SimpleSAML\SOAP11\XML\env\Body;
use SimpleSAML\SOAP11\XML\env\Envelope;
use SimpleSAML\XML\Chunk;
use SimpleSAML\XML\DOMDocumentFactory;
use SimpleSAML\XML\SerializableElementInterface;

class SamlValidateResponder
{
    /**
     * Converts a ticket to saml1 response. Caller likely needs wrap in SOAP
     * to return to a client.
     * @param array $ticket The cas ticket
     * @return \SimpleSAML\XML\Chunk The saml 1 xml for the CAS response
     */
    public function convertToSaml(array $ticket): Chunk
    {
        $serviceUrl = $ticket['service'];
        $attributes = $ticket['attributes'];
        $user = $ticket['userName'];

        $ar = new AuthnResponse();
        $idpMetadata = [
            // CAS doesn't seem to care what this is, however SSP code requires it to be set
            'entityid' => 'localhost',
        ];
        $spMetadata = [
            'entityid' => $serviceUrl,
        ];
        $shire = $serviceUrl; //the recpient
        $authnResponseXML = $ar->generate(
            Configuration::loadFromArray($idpMetadata),
            Configuration::loadFromArray($spMetadata),
            $shire,
            $attributes,
        );

        // replace NameIdentifier with actually username
        $ret = preg_replace(
            '|<NameIdentifier(.*)>.*</NameIdentifier>|',
            '<NameIdentifier$1>' . htmlspecialchars($user) . '</NameIdentifier>',
            $authnResponseXML,
        );
        // CAS seems to prefer this type of assertiond
        $ret = str_replace('urn:oasis:names:tc:SAML:1.0:cm:bearer', 'urn:oasis:names:tc:SAML:1.0:cm:artifact', $ret);
        // CAS uses a different namespace for attributes
        $ret = str_replace(
            'urn:mace:shibboleth:1.0:attributeNamespace:uri',
            'http://www.ja-sig.org/products/cas/',
            $ret,
        );

        $doc = DOMDocumentFactory::fromString($ret);
        return new Chunk($doc->documentElement);
    }


    /**
     * @param \SimpleSAML\XML\SerializableElementInterface $samlResponse
     * @return \SimpleSAML\SOAP11\XML\env\Envelope
     */
    public function wrapInSoap(SerializableElementInterface $samlResponse): Envelope
    {
        $body = new Body([$samlResponse]);
        return new Envelope($body);
    }
}
