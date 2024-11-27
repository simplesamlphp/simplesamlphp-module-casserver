<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleSAML\CAS\Constants as C;
use SimpleSAML\Configuration;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\Factories\TicketFactory;
use SimpleSAML\Module\casserver\Cas\Ticket\FileSystemTicketStore;
use SimpleSAML\Module\casserver\Cas\TicketValidator;
use SimpleSAML\Module\casserver\Controller\Cas20Controller;
use SimpleSAML\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Cas20ControllerTest extends TestCase
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
            'id'          => 'ST-' . $this->sessionId,
            'validBefore' => 9999999999,
            'service'     => 'https://myservice.com/abcd',
            'forceAuthn'  => false,
            'userName'    => 'username@google.com',
            'attributes'  =>
                [
                    'eduPersonPrincipalName' =>
                        [
                            0 => 'eduPersonPrincipalName@google.com',
                        ],
                ],
            'proxies'     =>
                [
                ],
            'sessionId'   => $this->sessionId,
        ];
    }

    public static function queryParameterValues(): array
    {
        return [
            'Only targetService query parameter' => [
                ['targetService' => 'https://myservice.com/abcd', 'pgt' => null],
                'Missing proxy granting ticket parameter: [pgt]',
            ],
            'Only pgt query parameter' => [
                ['pgt' => '1234567', 'targetService' => null],
                'Missing target service parameter [targetService]',
            ],
            'Has Neither pgt Nor targetService query parameters' => [
                ['pgt' => null, 'targetService' => null],
                'Missing target service parameter [targetService]',
            ],
            'Target service query parameter not listed' => [
                ['pgt' => 'pgt', 'targetService' => 'https://myservice.com/abcd'],
                'Target service parameter not listed as a legal service: [targetService] = https://myservice.com/abcd',
            ],
        ];
    }

    #[DataProvider('queryParameterValues')]
    public function testProxyRequestFails(array $params, string $message): void
    {
        $casconfig = Configuration::loadFromArray($this->moduleConfig);

        $this->samlValidateRequest = Request::create(
            uri:        Module::getModuleURL('casserver/proxy'),
            parameters: $params,
        );

        $cas20Controller = new Cas20Controller(
            $this->sspConfig,
            $casconfig,
        );

        $response = $cas20Controller->proxy($this->samlValidateRequest, ...$params);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString($message, $response->getContent());
        $xml = simplexml_load_string($response->getContent());
        $xml->registerXPathNamespace('cas', 'serviceResponse');
        $this->assertEquals('serviceResponse', $xml->getName());
        $this->assertEquals(
            C::ERR_INVALID_REQUEST,
            $xml->xpath('//cas:authenticationFailure')[0]->attributes()['code'],
        );
    }

    public function testProxyRequestFailsWhenPgtNotRecognized(): void
    {
        $this->moduleConfig['legal_target_service_urls'] = ['https://myservice.com/abcd'];
        $casconfig = Configuration::loadFromArray($this->moduleConfig);

        $params = ['pgt' => 'pgt', 'targetService' => 'https://myservice.com/abcd'];
        $this->samlValidateRequest = Request::create(
            uri:        Module::getModuleURL('casserver/proxy'),
            parameters: $params,
        );

        $cas20Controller = new Cas20Controller(
            $this->sspConfig,
            $casconfig,
        );

        $response = $cas20Controller->proxy($this->samlValidateRequest, ...$params);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $xml = simplexml_load_string($response->getContent());
        $xml->registerXPathNamespace('cas', 'serviceResponse');
        $this->assertEquals('serviceResponse', $xml->getName());
        $this->assertEquals('BAD_PGT', $xml->xpath('//cas:authenticationFailure')[0]->attributes()['code']);
        $this->assertEquals(
            'Ticket pgt not recognized',
            $xml->xpath('//cas:authenticationFailure')[0],
        );
    }

    public function testProxyRequestFailsWhenPgtNotValid(): void
    {
        $this->moduleConfig['legal_target_service_urls'] = ['https://myservice.com/abcd'];
        $casconfig = Configuration::loadFromArray($this->moduleConfig);

        $params = ['pgt' => $this->ticket['id'], 'targetService' => 'https://myservice.com/abcd'];
        $this->samlValidateRequest = Request::create(
            uri:        Module::getModuleURL('casserver/proxy'),
            parameters: $params,
        );

        $this->ticketStore->addTicket(['id' => $this->ticket['id']]);

        $cas20Controller = new Cas20Controller(
            $this->sspConfig,
            $casconfig,
        );

        $response = $cas20Controller->proxy($this->samlValidateRequest, ...$params);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $xml = simplexml_load_string($response->getContent());
        $xml->registerXPathNamespace('cas', 'serviceResponse');
        $this->assertEquals('serviceResponse', $xml->getName());
        $this->assertEquals('BAD_PGT', $xml->xpath('//cas:authenticationFailure')[0]->attributes()['code']);
        $this->assertEquals(
            'Not a valid proxy granting ticket id: ' . $this->ticket['id'],
            $xml->xpath('//cas:authenticationFailure')[0],
        );
    }

    public function testProxyRequestFailsWhenPgtExpired(): void
    {
        $this->moduleConfig['legal_target_service_urls'] = ['https://myservice.com/abcd'];
        $casconfig = Configuration::loadFromArray($this->moduleConfig);
        $ticket = [
            'id'          => 'PGT-' . $this->sessionId,
            'validBefore' => 9999999999,
            'service'     => 'https://myservice.com/abcd',
            'sessionId'   => $this->sessionId,
        ];
        $params = ['pgt' => $ticket['id'], 'targetService' => 'https://myservice.com/abcd'];
        $this->samlValidateRequest = Request::create(
            uri:        Module::getModuleURL('casserver/proxy'),
            parameters: $params,
        );

        $this->ticketStore->addTicket($ticket);

        $cas20Controller = new Cas20Controller(
            $this->sspConfig,
            $casconfig,
        );

        $response = $cas20Controller->proxy($this->samlValidateRequest, ...$params);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $xml = simplexml_load_string($response->getContent());
        $xml->registerXPathNamespace('cas', 'serviceResponse');
        $this->assertEquals('serviceResponse', $xml->getName());
        $this->assertEquals('BAD_PGT', $xml->xpath('//cas:authenticationFailure')[0]->attributes()['code']);
        $this->assertEquals(
            "Ticket {$ticket['id']} has expired",
            $xml->xpath('//cas:authenticationFailure')[0],
        );
    }

    public function testProxyReturnsProxyTicket(): void
    {
        $this->moduleConfig['legal_target_service_urls'] = ['https://myservice.com/abcd'];
        $casconfig = Configuration::loadFromArray($this->moduleConfig);
        $ticket = [
            'id'          => 'PGT-' . $this->sessionId,
            'validBefore' => 9999999999,
            'service'     => 'https://myservice.com/abcd',
            'sessionId'   => $this->sessionId,
            'forceAuthn'  => false,
            'attributes'  =>
                [
                    'eduPersonPrincipalName' =>
                        [
                            0 => 'eduPersonPrincipalName@google.com',
                        ],
                ],
            'proxies'     =>
                [
                ],
        ];
        $sessionTicket = [
            'id'          => $this->sessionId,
            'validBefore' => 9999999999,
            'service'     => 'https://myservice.com/abcd',
            'sessionId'   => $this->sessionId,
        ];
        $params = ['pgt' => $ticket['id'], 'targetService' => 'https://myservice.com/abcd'];
        $this->samlValidateRequest = Request::create(
            uri:        Module::getModuleURL('casserver/proxy'),
            parameters: $params,
        );

        $this->ticketStore->addTicket($ticket);
        $this->ticketStore->addTicket($sessionTicket);
        $ticketFactory = new TicketFactory($casconfig);

        $cas20Controller = new Cas20Controller(
            $this->sspConfig,
            $casconfig,
        );

        $response = $cas20Controller->proxy($this->samlValidateRequest, ...$params);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $xml = simplexml_load_string($response->getContent());
        $xml->registerXPathNamespace('cas', 'serviceResponse');
        $this->assertEquals('serviceResponse', $xml->getName());
        $this->assertNotNull($xml->xpath('//cas:proxySuccess'));
        $ticketId = (string)$xml->xpath('//cas:proxyTicket')[0];
        $proxyTicket = $this->ticketStore->getTicket($ticketId);
        $this->assertTrue(filter_var($ticketFactory->isProxyTicket($proxyTicket), FILTER_VALIDATE_BOOLEAN));
    }
}
