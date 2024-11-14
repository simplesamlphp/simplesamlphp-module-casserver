<?php

declare(strict_types=1);

namespace SimpleSAML\Casserver;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Module\casserver\Cas\Protocol\SamlValidateResponder;
use SimpleSAML\SOAP\XML\env_200305\Envelope;

class SamlValidateTest extends TestCase
{
    /**
     */
    public function testSamlValidateXmlGeneration(): void
    {
        $serviceUrl = 'http://jellyfish.greatvalleyu.com:7777/ssomanager/c/SSB';
        $udcValue = '2F10C881AC7D55942329E149405DC2F5';
        $ticket = [
            'userName' => 'saisusr',
            'attributes' => [
                'UDC_IDENTIFIER' => [$udcValue],
            ],
            'service' => $serviceUrl,
        ];

        $samlValidate = new SamlValidateResponder();
        $xmlString = $samlValidate->convertToSaml($ticket);

        $p = xml_parser_create();
        xml_parse_into_struct($p, \strval($xmlString), $vals, $index);
        xml_parser_free($p);

        /** @psalm-suppress PossiblyNullPropertyFetch */
        $this->assertEquals(
            $serviceUrl,
            $vals[$index['RESPONSE'][0]]['attributes']['RECIPIENT'],
        );
        $this->assertEquals(
            'samlp:Success',
            $vals[$index['STATUSCODE'][0]]['attributes']['VALUE'],
        );

        $this->assertEquals(
            'localhost',
            $vals[$index['SAML:ASSERTION'][0]]['attributes']['ISSUER'],
        );

        $this->assertEquals($serviceUrl, $vals[$index['SAML:AUDIENCE'][0]]['value']);

        $this->assertEquals('saisusr', $vals[$index['SAML:NAMEIDENTIFIER'][0]]['value']);
        $this->assertEquals(
            'urn:oasis:names:tc:SAML:1.0:cm:artifact',
            $vals[$index['SAML:CONFIRMATIONMETHOD'][0]]['value'],
        );

        $this->assertEquals(
            'UDC_IDENTIFIER',
            $vals[$index['SAML:ATTRIBUTE'][0]]['attributes']['ATTRIBUTENAME'],
        );
        $this->assertEquals(
            'http://www.ja-sig.org/products/cas/',
            $vals[$index['SAML:ATTRIBUTE'][0]]['attributes']['ATTRIBUTENAMESPACE'],
        );
        $this->assertEquals($udcValue, $vals[$index['SAML:ATTRIBUTEVALUE'][0]]['value']);

        $asSoap = $samlValidate->wrapInSoap($xmlString);

        $this->assertInstanceOf(Envelope::class, $asSoap);
        $this->assertNull($asSoap->getHeader());
        $this->assertNotEmpty($asSoap->getBody());
    }
}
