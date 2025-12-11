<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SimpleSAML\CAS\Constants as C;
use SimpleSAML\Configuration;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\Factories\TicketFactory;
use SimpleSAML\Module\casserver\Cas\Ticket\FileSystemTicketStore;
use SimpleSAML\Module\casserver\Cas\TicketValidator;
use SimpleSAML\Module\casserver\Controller\Cas20Controller;
use SimpleSAML\Session;
use SimpleSAML\Utils;
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

    private Utils\HTTP|MockObject $utilsHttpMock;

    private array $ticket;

    private array $proxyTicket;


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

        $this->utilsHttpMock = $this->getMockBuilder(Utils\HTTP::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetch'])
            ->getMock();

        $this->ticket = [
            'id'          => 'ST-' . $this->sessionId,
            'validBefore' => 1731111111,
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

        $this->proxyTicket = [
            'id'          => 'PT-' . $this->sessionId,
            'validBefore' => 1731111111,
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


    public static function validateMethods(): array
    {
        return [
            'Call serviceValidate action' => [
                'ST-',
                'serviceValidate',
            ],
            'Call proxyValidate action' => [
                'PT-',
                'proxyValidate',
            ],
        ];
    }


    #[DataProvider('validateMethods')]
    public function testProxyValidatePassesTheCorrectMethodToValidate(string $prefix, string $method): void
    {
        $casconfig = Configuration::loadFromArray($this->moduleConfig);
        $requestParameters = [
            'renew' => false,
            'service' => 'https://myservice.com/abcd',
            'ticket' => $prefix . $this->sessionId,
        ];

        $request = Request::create(
            uri:        'https://myservice.com/abcd',
            parameters: $requestParameters,
        );

        $controllerMock = $this->getMockBuilder(Cas20Controller::class)
            ->setConstructorArgs([$this->sspConfig, $casconfig])
            ->onlyMethods(['validate'])
            ->getMock();

        $controllerMock->expects($this->once())
            ->method('validate')
            ->with($request, $method, false, null, $prefix . $this->sessionId, 'https://myservice.com/abcd', null);
        $controllerMock->$method($request, ...$requestParameters);
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


    public static function validateFailsForEmptyServiceTicket(): array
    {
        return [
            'Service URL & TARGET is empty' => [
                ['ticket' => 'ST-12334Q45W4563'],
                'casserver: Missing service parameter: [service]',
            ],
            'Ticket is empty but Service is not' => [
                ['service' => 'http://localhost'],
                'casserver: Missing ticket parameter: [ticket]',
            ],
            'Ticket is empty but TARGET is not' => [
                ['TARGET' => 'http://localhost'],
                'casserver: Missing ticket parameter: [ticket]',
            ],
        ];
    }


    #[DataProvider('validateFailsForEmptyServiceTicket')]
    public function testServiceValidateFailing(array $requestParams, string $message): void
    {
        $casconfig = Configuration::loadFromArray($this->moduleConfig);
        $request = Request::create(
            uri:        '/',
            parameters: $requestParams,
        );
        $cas20Controller = new Cas20Controller(
            $this->sspConfig,
            $casconfig,
        );

        $response = $cas20Controller->serviceValidate($request, ...$requestParams);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString($message, $response->getContent());
        $xml = simplexml_load_string($response->getContent());
        $xml->registerXPathNamespace('cas', 'serviceResponse');
        $this->assertEquals('serviceResponse', $xml->getName());
        $this->assertEquals(
            C::ERR_INVALID_SERVICE,
            $xml->xpath('//cas:authenticationFailure')[0]->attributes()['code'],
        );
    }


    public function testReturn500OnDeleteTicketThatThrows(): void
    {
        $config = Configuration::loadFromArray($this->moduleConfig);
        $params = [
            'ticket' => $this->sessionId,
            'service' => 'https://myservice.com/abcd',
        ];

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $params,
        );

        $ticketStore = new class ($config) extends FileSystemTicketStore {
            public function getTicket(string $ticketId): ?array
            {
                throw new \Exception();
            }
        };

        $cas20Controller = new Cas20Controller($this->sspConfig, $config, $ticketStore);
        $response = $cas20Controller->serviceValidate($request, ...$params);

        $message = 'casserver:serviceValidate: internal server error';
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringContainsString($message, $response->getContent());
        $xml = simplexml_load_string($response->getContent());
        $xml->registerXPathNamespace('cas', 'serviceResponse');
        $this->assertEquals('serviceResponse', $xml->getName());
        $this->assertEquals(
            C::ERR_INTERNAL_ERROR,
            $xml->xpath('//cas:authenticationFailure')[0]->attributes()['code'],
        );
    }


    public static function validateOnDifferentQueryParameterCombinations(): array
    {
        $sessionId = session_create_id();
        return [
            'Returns Bad Request on Ticket Not Recognised/Exists' => [
                [
                    'ticket' => $sessionId,
                    'service' => 'https://myservice.com/abcd',
                ],
                "Ticket '{$sessionId}' not recognized",
                'ST-' . $sessionId,
            ],
            'Returns Bad Request on Ticket is Proxy' => [
                [
                    'ticket' => 'PT-' . $sessionId,
                    'service' => 'https://myservice.com/abcd',
                ],
                "Ticket 'PT-{$sessionId}' is not a service ticket.",
                'PT-' . $sessionId,
            ],
            'Returns Bad Request on Ticket Expired' => [
                [
                    'ticket' => 'ST-' . $sessionId,
                    'service' => 'https://myservice.com/abcd',
                ],
                "Ticket 'ST-{$sessionId}' has expired",
                'ST-' . $sessionId,
            ],
            'Returns Bad Request on Ticket Not A Service Ticket' => [
                [
                    'ticket' => $sessionId,
                    'service' => 'https://myservice.com/abcd',
                ],
                "Ticket '{$sessionId}' is not a service ticket",
                $sessionId,
            ],
            'Returns Bad Request on Ticket Issued By Single SignOn Session' => [
                [
                    'ticket' => 'ST-' . $sessionId,
                    'service' => 'https://myservice.com/abcd',
                    'renew' => true,
                ],
                'Ticket was issued from single sign on session',
                'ST-' . $sessionId,
                9999999999,
            ],
            'Returns Success' => [
                [
                    'ticket' => 'ST-' . $sessionId,
                    'service' => 'https://myservice.com/abcd',
                ],
                'username@google.com',
                'ST-' . $sessionId,
                9999999999,
            ],
        ];
    }


    #[DataProvider('validateOnDifferentQueryParameterCombinations')]
    public function testServiceValidate(
        array $requestParams,
        string $message,
        string $ticketId,
        int $validBefore = 1111111111,
    ): void {
        $config = Configuration::loadFromArray($this->moduleConfig);

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $requestParams,
        );

        $cas20Controller = new Cas20Controller($this->sspConfig, $config);

        if (!empty($ticketId)) {
            $ticketStore = $cas20Controller->getTicketStore();
            $ticket = $this->ticket;
            $ticket['id'] = $ticketId;
            $ticket['validBefore'] = $validBefore;
            $ticketStore->addTicket($ticket);
        }

        $response = $cas20Controller->serviceValidate($request, ...$requestParams);

        if ($message === 'username@google.com') {
            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
            $this->assertStringContainsString($message, $response->getContent());
            $xml = simplexml_load_string($response->getContent());
            $xml->registerXPathNamespace('cas', 'serviceResponse');
            $this->assertEquals('serviceResponse', $xml->getName());
            $this->assertEquals(
                'username@google.com',
                $xml->xpath('//cas:authenticationSuccess/cas:user')[0][0],
            );
        } else {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
            $this->assertStringContainsString($message, $response->getContent());
            $xml = simplexml_load_string($response->getContent());
            $xml->registerXPathNamespace('cas', 'serviceResponse');
            $this->assertEquals('serviceResponse', $xml->getName());
            $this->assertEquals(
                C::ERR_INVALID_SERVICE,
                $xml->xpath('//cas:authenticationFailure')[0]->attributes()['code'],
            );
        }
    }


    public static function validateOnDifferentQueryParameterCombinationsProxyValidate(): array
    {
        $sessionId = session_create_id();
        return [
            'Returns Bad Request on Ticket Not Recognised/Exists' => [
                [
                    'ticket' => $sessionId,
                    'service' => 'https://myservice.com/abcd',
                ],
                "Ticket '{$sessionId}' not recognized",
                'PT-' . $sessionId,
            ],
            'Returns Bad Request on Ticket Expired' => [
                [
                    'ticket' => 'PT-' . $sessionId,
                    'service' => 'https://myservice.com/abcd',
                ],
                "Ticket 'PT-{$sessionId}' has expired",
                'PT-' . $sessionId,
            ],
            'Returns Bad Request on Ticket Issued By Single SignOn Session' => [
                [
                    'ticket' => 'PT-' . $sessionId,
                    'service' => 'https://myservice.com/abcd',
                    'renew' => true,
                ],
                'Ticket was issued from single sign on session',
                'PT-' . $sessionId,
                9999999999,
            ],
            'Returns Success with Proxy Ticket' => [
                [
                    'ticket' => 'PT-' . $sessionId,
                    'service' => 'https://myservice.com/abcd',
                ],
                'username@google.com',
                'PT-' . $sessionId,
                9999999999,
            ],
            'Returns Success with Service Ticket' => [
                [
                    'ticket' => 'ST-' . $sessionId,
                    'service' => 'https://myservice.com/abcd',
                ],
                'username@google.com',
                'ST-' . $sessionId,
                9999999999,
            ],
        ];
    }


    #[DataProvider('validateOnDifferentQueryParameterCombinationsProxyValidate')]
    public function testProxyValidate(
        array $requestParams,
        string $message,
        string $ticketId,
        int $validBefore = 1111111111,
    ): void {
        $config = Configuration::loadFromArray($this->moduleConfig);

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $requestParams,
        );

        $cas20Controller = new Cas20Controller($this->sspConfig, $config);

        if (!empty($ticketId)) {
            $ticketStore = $cas20Controller->getTicketStore();
            $ticket = $this->proxyTicket;
            $ticket['id'] = $ticketId;
            $ticket['validBefore'] = $validBefore;
            $ticketStore->addTicket($ticket);
        }

        $response = $cas20Controller->proxyValidate($request, ...$requestParams);

        if ($message === 'username@google.com') {
            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
            $this->assertStringContainsString($message, $response->getContent());
            $xml = simplexml_load_string($response->getContent());
            $xml->registerXPathNamespace('cas', 'serviceResponse');
            $this->assertEquals('serviceResponse', $xml->getName());
            $this->assertEquals(
                'username@google.com',
                $xml->xpath('//cas:authenticationSuccess/cas:user')[0][0],
            );
        } else {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
            $this->assertStringContainsString($message, $response->getContent());
            $xml = simplexml_load_string($response->getContent());
            $xml->registerXPathNamespace('cas', 'serviceResponse');
            $this->assertEquals('serviceResponse', $xml->getName());
            $this->assertEquals(
                C::ERR_INVALID_SERVICE,
                $xml->xpath('//cas:authenticationFailure')[0]->attributes()['code'],
            );
        }
    }


    public function testReturnBadRequestOnTicketServiceQueryAndTicketMismatch(): void
    {
        $config = Configuration::loadFromArray($this->moduleConfig);
        $params = [
            'ticket' => 'ST-' . $this->sessionId,
            'service' => 'https://myservice.com/failservice',
        ];
        $this->ticket['validBefore'] = 9999999999;
        $this->ticket['attributes'] = [];

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $params,
        );

        $cas20Controller = new Cas20Controller($this->sspConfig, $config);
        $ticketStore = $cas20Controller->getTicketStore();
        $ticketStore->addTicket($this->ticket);
        $response = $cas20Controller->serviceValidate($request, ...$params);

        $message = "Mismatching service parameters: expected 'https://myservice.com/abcd'" .
            " but was: 'https://myservice.com/failservice'";
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString($message, $response->getContent());
        $xml = simplexml_load_string($response->getContent());
        $xml->registerXPathNamespace('cas', 'serviceResponse');
        $this->assertEquals('serviceResponse', $xml->getName());
        $this->assertEquals(
            C::ERR_INVALID_SERVICE,
            $xml->xpath('//cas:authenticationFailure')[0]->attributes()['code'],
        );
    }


    public function testThrowOnProxyServiceIdentityFail(): void
    {
        $config = Configuration::loadFromArray($this->moduleConfig);
        $params = [
            'ticket' => 'ST-' . $this->sessionId,
            'service' => 'https://myservice.com/abcd',
            'pgtUrl' => 'https://myservice.com/proxy',
        ];
        $this->ticket['validBefore'] = 9999999999;
        $sessionTicket = $this->ticket;
        $sessionTicket['id'] = $this->sessionId;

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $params,
        );

        $this->utilsHttpMock->expects($this->once())
            ->method('fetch')
            ->willThrowException(new \Exception());

        $cas20Controller = new Cas20Controller(
            sspConfig: $this->sspConfig,
            casConfig: $config,
            httpUtils: $this->utilsHttpMock,
        );
        $ticketStore = $cas20Controller->getTicketStore();
        $ticketStore->addTicket($this->ticket);
        $ticketStore->addTicket($sessionTicket);
        $response = $cas20Controller->serviceValidate($request, ...$params);

        $message = 'Proxy callback url is failing.';
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString($message, $response->getContent());
        $xml = simplexml_load_string($response->getContent());
        $xml->registerXPathNamespace('cas', 'serviceResponse');
        $this->assertEquals('serviceResponse', $xml->getName());
        $this->assertEquals(
            C::ERR_INVALID_SERVICE,
            $xml->xpath('//cas:authenticationFailure')[0]->attributes()['code'],
        );
    }
}
