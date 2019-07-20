<?php
namespace Simplesamlphp\Casserver;

use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\Protocol\Cas20;

class Cas20Test extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     */
    public function testAttributeToXmlConversion()
    {
            $casConfig = Configuration::loadFromArray([
                'attributes' => true, //send all attributes
            ]);


            $userAttributes = [
                'lastName' => ['lasty'],
                'valuesAreEscaped' => [
                    '>abc<blah>',
                ],
                // too many illegal characters
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname' => ['Firsty'],
                // ':' will get turn to '_'
                'urn:oid:0.9.2342.19200300.100.1.1' => ['someValue'],
                'urn:oid:1.3.6.1.4.1.34199.1.7.1.5.2' => [
                    'CN=Some-Service,OU=Non-Privileged,OU=Groups,DC=exmple,DC=com',
                    'CN=Other Servics,OU=Non-Privileged,OU=Groups,DC=example,DC=com'
                ],

            ];

            $casProtocol = new Cas20($casConfig);
            $casProtocol->setAttributes($userAttributes);

            $xml = $casProtocol->getValidateSuccessResponse('myUser');

            $expectedXml = <<<'EOD'
<?xml version="1.0"?>
<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
<cas:authenticationSuccess>
<cas:user>myUser</cas:user>
<cas:attributes>
<cas:lastName>lasty</cas:lastName>
<cas:valuesAreEscaped>&gt;abc&lt;blah&gt;</cas:valuesAreEscaped>
<cas:urn_oid_0.9.2342.19200300.100.1.1>someValue</cas:urn_oid_0.9.2342.19200300.100.1.1>
<cas:urn_oid_1.3.6.1.4.1.34199.1.7.1.5.2>CN=Some-Service,OU=Non-Privileged,OU=Groups,DC=exmple,DC=com</cas:urn_oid_1.3.6.1.4.1.34199.1.7.1.5.2>
<cas:urn_oid_1.3.6.1.4.1.34199.1.7.1.5.2>CN=Other Servics,OU=Non-Privileged,OU=Groups,DC=example,DC=com</cas:urn_oid_1.3.6.1.4.1.34199.1.7.1.5.2>
</cas:attributes>
</cas:authenticationSuccess>
</cas:serviceResponse>
EOD;
            $this->assertEquals($expectedXml, $xml);
    }
}
