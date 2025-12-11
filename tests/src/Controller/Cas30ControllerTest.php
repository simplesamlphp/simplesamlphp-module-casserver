<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\Ticket\FileSystemTicketStore;
use SimpleSAML\Module\casserver\Cas\TicketValidator;
use SimpleSAML\Module\casserver\Controller\Cas30Controller;
use SimpleSAML\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Cas30ControllerTest extends TestCase
{
    private array $moduleConfig;

    private Session $sessionMock;

    private Request $samlValidateRequest;

    private string $sessionId;

    private Configuration $sspConfig;

    private FileSystemTicketStore $ticketStore;

    private TicketValidator $ticketValidatorMock;

    private array $ticket;

    /**
     * @throws \Exception
     */
    protected function setUp(): void
    {
        $this->sspConfig    = Configuration::getConfig('config.php');
        $this->sessionId    = session_create_id();
        $this->moduleConfig = [
            'ticketstore' => [
                'class'     => 'casserver:FileSystemTicketStore', //Not intended for production
                'directory' => __DIR__ . '../../../../tests/ticketcache',
            ],
        ];

        // Hard code the ticket store
        $this->ticketStore = new FileSystemTicketStore(Configuration::loadFromArray($this->moduleConfig));

        $this->ticketValidatorMock = $this->getMockBuilder(TicketValidator::class)
            ->setConstructorArgs([Configuration::loadFromArray($this->moduleConfig)])
            ->onlyMethods(['validateAndDeleteTicket'])
            ->getMock();

        $this->sessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSessionId'])
            ->getMock();

        $this->ticket = [
            'id' => 'ST-' . $this->sessionId,
            'validBefore' => 9999999999,
            'service' => 'https://myservice.com/abcd',
            'forceAuthn' => false,
            'userName' => 'username@google.com',
            'attributes' =>
                [
                    'eduPersonPrincipalName' =>
                        [
                            0 => 'eduPersonPrincipalName@google.com',
                        ],
                ],
            'proxies' =>
                [
                ],
            'sessionId' => $this->sessionId,
        ];
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testNoSoapBody(): void
    {
        $casconfig = Configuration::loadFromArray($this->moduleConfig);

        $target = 'https://comanage-ioi-dev.workbench.incommon.org/ssp/module.php/cas/linkback.php?'
            . 'stateId=_bd6b7a3d207ed26ea893f49e555515b5f839547b59%3A'
            . 'https%3A%2F%2Fcomanage-ioi-dev.workbench.incommon.org%2Fssp%2Fmodule.php%2Fadmin%2Ftest%2Fcasserver';
        $this->samlValidateRequest = Request::create(
            uri:        Module::getModuleURL('casserver/samlValidate'),
            method:     'POST',
            parameters: ['TARGET' => $target],
            content:    '',
        );

        $cas30Controller = new Cas30Controller(
            $this->sspConfig,
            $casconfig,
        );

        // Exception expected
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('samlValidate expects a soap body.');

        $cas30Controller->samlValidate($this->samlValidateRequest, $target);
    }

    public static function soapEnvelopes(): array
    {
        return [
            'Body Missing RequestID Attribute' => [
                <<<SOAP
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Header/>
    <SOAP-ENV:Body>
        <samlp:Request xmlns:samlp="urn:oasis:names:tc:SAML:1.0:protocol"
                       MajorVersion="1"
                       MinorVersion="1"
                       IssueInstant="2002-06-19T17:03:44.022Z">
            <samlp:AssertionArtifact>ST-892424614ae738153cf6fda6ea372f54489870b8eb</samlp:AssertionArtifact>
        </samlp:Request>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
SOAP,
                "Missing 'RequestID' attribute on samlp:Request.",
                'SimpleSAML\XMLSchema\Exception\MissingAttributeException',
            ],
            'Body Missing IssueInstant Attribute' => [
                <<<SOAP
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Header/>
    <SOAP-ENV:Body>
        <samlp:Request xmlns:samlp="urn:oasis:names:tc:SAML:1.0:protocol"
                       MajorVersion="1"
                       MinorVersion="1"
                       RequestID="_192.168.16.51.1024506224022">
            <samlp:AssertionArtifact>ST-892424614ae738153cf6fda6ea372f54489870b8eb</samlp:AssertionArtifact>
        </samlp:Request>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
SOAP,
                "Missing 'IssueInstant' attribute on samlp:Request.",
                'SimpleSAML\XMLSchema\Exception\MissingAttributeException',
            ],
            'Body Missing Ticket Id' => [
                <<<SOAP
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Header/>
    <SOAP-ENV:Body>
        <samlp:Request xmlns:samlp="urn:oasis:names:tc:SAML:1.0:protocol"
                       MajorVersion="1"
                       MinorVersion="1"
                       IssueInstant="2002-06-19T17:03:44.022Z"
                       RequestID="_192.168.16.51.1024506224022">
            <samlp:AssertionArtifact></samlp:AssertionArtifact>
        </samlp:Request>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
SOAP,
                '"" is not a SAML1.1-compliant string',
                'SimpleSAML\SAML11\Exception\ProtocolViolationException',
            ],
        ];
    }

    #[DataProvider('soapEnvelopes')]
    public function testSoapMessageIsInvalid(
        string $soapMessage,
        string $exceptionMessage,
        string $exceptionClassName,
    ): void {
        $casconfig = Configuration::loadFromArray($this->moduleConfig);
        $samlRequest = $soapMessage;

        $target = 'https://comanage-ioi-dev.workbench.incommon.org/ssp/module.php/cas/linkback.php?'
            . 'stateId=_bd6b7a3d207ed26ea893f49e555515b5f839547b59%3A'
            . 'https%3A%2F%2Fcomanage-ioi-dev.workbench.incommon.org%2Fssp%2Fmodule.php%2Fadmin%2Ftest%2Fcasserver';
        $this->samlValidateRequest = Request::create(
            uri:        Module::getModuleURL('casserver/samlValidate'),
            method:     'POST',
            parameters: ['TARGET' => $target],
            content:    $samlRequest,
        );

        $cas30Controller = new Cas30Controller(
            $this->sspConfig,
            $casconfig,
        );

        // Exception expected
        $this->expectException($exceptionClassName);
        $this->expectExceptionMessage($exceptionMessage);

        $cas30Controller->samlValidate($this->samlValidateRequest, $target);
    }


    /**
     * @return void
     * @throws \Exception
     */
    public function testCasValidateAndDeleteTicketThrowsException(): void
    {
        $casconfig = Configuration::loadFromArray($this->moduleConfig);
        $samlRequest = <<<SOAP
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Header/>
    <SOAP-ENV:Body>
        <samlp:Request xmlns:samlp="urn:oasis:names:tc:SAML:1.0:protocol"
                       MajorVersion="1"
                       MinorVersion="1"
                       IssueInstant="2002-06-19T17:03:44.022Z"
                       RequestID="_192.168.16.51.1024506224022">
            <samlp:AssertionArtifact>ST-892424614ae738153cf6fda6ea372f54489870b8eb</samlp:AssertionArtifact>
        </samlp:Request>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
SOAP;

        $target = 'https://comanage-ioi-dev.workbench.incommon.org/ssp/module.php/cas/linkback.php?'
            . 'stateId=_bd6b7a3d207ed26ea893f49e555515b5f839547b59%3A'
            . 'https%3A%2F%2Fcomanage-ioi-dev.workbench.incommon.org%2Fssp%2Fmodule.php%2Fadmin%2Ftest%2Fcasserver';
        $this->samlValidateRequest = Request::create(
            uri:        Module::getModuleURL('casserver/samlValidate'),
            method:     'POST',
            parameters: ['TARGET' => $target],
            content:    $samlRequest,
        );

        $this->ticketValidatorMock
            ->expects($this->once())
            ->method('validateAndDeleteTicket')
            ->willThrowException(new \RuntimeException('Cas validateAndDeleteTicket failed'));

        $cas30Controller = new Cas30Controller(
            $this->sspConfig,
            $casconfig,
            $this->ticketValidatorMock,
        );

        // Exception expected
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cas validateAndDeleteTicket failed');

        $cas30Controller->samlValidate($this->samlValidateRequest, $target);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testUnableToLoadTicket(): void
    {
        $this->ticketStore->addTicket(['id' => $this->ticket['id']]);
        // We add the ticket. We need to make

        $ticketId = $this->ticket['id'];
        $casconfig = Configuration::loadFromArray($this->moduleConfig);
        $samlRequest = <<<SOAP
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Header/>
    <SOAP-ENV:Body>
        <samlp:Request xmlns:samlp="urn:oasis:names:tc:SAML:1.0:protocol"
                       MajorVersion="1"
                       MinorVersion="1"
                       IssueInstant="2002-06-19T17:03:44.022Z"
                       RequestID="_192.168.16.51.1024506224022">
            <samlp:AssertionArtifact>$ticketId</samlp:AssertionArtifact>
        </samlp:Request>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
SOAP;

        $target = 'https://myservice.com/abcd';
        $this->samlValidateRequest = Request::create(
            uri:        Module::getModuleURL('casserver/samlValidate'),
            method:     'POST',
            parameters: ['TARGET' => $target],
            content:    $samlRequest,
        );

        $this->ticketValidatorMock
            ->expects($this->once())
            ->method('validateAndDeleteTicket')
            ->willReturn('i am a string');

        $cas30Controller = new Cas30Controller(
            $this->sspConfig,
            $casconfig,
            $this->ticketValidatorMock,
        );

        // Exception expected
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error loading ticket');

        $cas30Controller->samlValidate($this->samlValidateRequest, $target);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testSuccessfullValidation(): void
    {
        $this->ticketStore->addTicket($this->ticket);
        $ticketId = $this->ticket['id'];
        $casconfig = Configuration::loadFromArray($this->moduleConfig);
        $samlRequest = <<<SOAP
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Header/>
    <SOAP-ENV:Body>
        <samlp:Request xmlns:samlp="urn:oasis:names:tc:SAML:1.0:protocol"
                       MajorVersion="1"
                       MinorVersion="1"
                       IssueInstant="2002-06-19T17:03:44.022Z"
                       RequestID="_192.168.16.51.1024506224022">
            <samlp:AssertionArtifact>$ticketId</samlp:AssertionArtifact>
        </samlp:Request>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
SOAP;

        $target = 'https://myservice.com/abcd';
        $this->samlValidateRequest = Request::create(
            uri:        Module::getModuleURL('casserver/samlValidate'),
            method:     'POST',
            parameters: ['TARGET' => $target],
            content:    $samlRequest,
        );

        $cas30Controller = new Cas30Controller(
            $this->sspConfig,
            $casconfig,
        );

        $resp = $cas30Controller->samlValidate($this->samlValidateRequest, $target);
        $this->assertEquals($resp->getStatusCode(), Response::HTTP_OK);
        $this->assertStringContainsString(
            '<saml:AttributeValue>eduPersonPrincipalName@google.com</saml:AttributeValue>',
            $resp->getContent(),
        );
    }
}
