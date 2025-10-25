<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Compat\SspContainer;
use SimpleSAML\Configuration;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Controller\LoginController;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use Symfony\Component\HttpFoundation\Request;

class LoginControllerTest extends TestCase
{
    private array $moduleConfig;

    private Simple|MockObject $authSimpleMock;

    private SspContainer|MockObject $sspContainer;

    private Configuration $sspConfig;

    private Utils\HTTP|MockObject $httpUtils;

    private Session|MockObject $sessionMock;

    protected function setUp(): void
    {
        $this->authSimpleMock = $this->getMockBuilder(Simple::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuthData', 'isAuthenticated', 'login', 'getAuthDataArray'])
            ->getMock();

        $this->sspContainer = $this->getMockBuilder(SspContainer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['redirect'])
            ->getMock();

        $this->httpUtils = $this->getMockBuilder(Utils\HTTP::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['redirectTrustedURL'])
            ->getMock();

        $this->sessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSessionId'])
            ->getMock();

        $this->moduleConfig = [
            'ticketstore' => [
                'class' => 'casserver:FileSystemTicketStore', //Not intended for production
                'directory' => __DIR__ . '../../../../tests/ticketcache',
            ],
            'authsource' => 'sso',
            'legal_service_urls' => [
                'https://example.com/ssp/module.php/cas/linkback.php',
            ],
            'debugMode' => false,
        ];

        $this->sspConfig = Configuration::getConfig('config.php');
    }

    public static function setUpBeforeClass(): void
    {
        // Some of the constructs in this test cause a Configuration to be created prior to us
        // setting the one we want to use for the test.
        Configuration::clearInternalState();

        // To make lib/SimpleSAML/Utils/HTTP::getSelfURL() work...
        global $_SERVER;
        $_SERVER['REQUEST_URI'] = '/';
    }

    public static function loginParameters(): array
    {
        return [
            'Wrong Service Url' => [
                ['service' => 'http://not-legal'],
                // phpcs:ignore Generic.Files.LineLength.TooLong
                "Service parameter provided to CAS server is not listed as a legal service: [service] = 'http://not-legal'",
            ],
            'Invalid Scope' => [
                [
                    'service' => 'https://example.com/ssp/module.php/cas/linkback.php',
                    'scope' => 'illegalscope',
                ],
                "Scope parameter provided to CAS server is not listed as legal scope: [scope] = 'illegalscope'",

            ],
        ];
    }

    /**
     * Test incorrect service url
     * @throws \Exception
     */
    #[DataProvider('loginParameters')]
    public function testLoginFails(array $params, string $message): void
    {
        $casconfig = Configuration::loadFromArray($this->moduleConfig);

        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: $params,
        );

        $loginController = new LoginController(
            $this->sspConfig,
            $casconfig,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);

        $loginController->login($loginRequest, ...$params);
    }

    public static function loginOnAuthenticateParameters(): array
    {
        return [
            'No EntityId Query Parameter' => [
                ['service' => 'https://example.com/ssp/module.php/cas/linkback.php'],
                [
                    'ForceAuthn' => false,
                    'isPassive' => false,
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'ReturnTo' => 'http://localhost/?service=https%3A%2F%2Fexample.com%2Fssp%2Fmodule.php%2Fcas%2Flinkback.php',
                ],
                [],
            ],
            'With EntityId Set' => [
                [
                    'service' => 'https://example.com/ssp/module.php/cas/linkback.php',
                    'entityId' => 'http://localhost/entityId/sso',
                ],
                [
                    'saml:idp' => 'http://localhost/entityId/sso',
                    'ForceAuthn' => false,
                    'isPassive' => false,
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'ReturnTo' => 'http://localhost/?service=https%3A%2F%2Fexample.com%2Fssp%2Fmodule.php%2Fcas%2Flinkback.php&entityId=http%3A%2F%2Flocalhost%2FentityId%2Fsso',
                ],
                [],
            ],
            'With Valid Scope List Set - More than 1 items' => [
                [
                    'service' => 'https://example.com/ssp/module.php/cas/linkback.php',
                    'scope' => 'desktop',
                ],
                [
                    'ForceAuthn' => false,
                    'isPassive' => false,
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'ReturnTo' => 'http://localhost/?service=https%3A%2F%2Fexample.com%2Fssp%2Fmodule.php%2Fcas%2Flinkback.php&scope=desktop',
                    'saml:IDPList' => [
                        'http://localhost/entityId/sso/scope/A',
                        'http://localhost/entityId/sso/scope/B',
                    ],
                ],
                [
                    'desktop' => [
                        'http://localhost/entityId/sso/scope/A',
                        'http://localhost/entityId/sso/scope/B',
                    ],
                ],

            ],
            'With Valid Scope List Set - 1 item' => [
                [
                    'service' => 'https://example.com/ssp/module.php/cas/linkback.php',
                    'scope' => 'desktop',
                ],
                [
                    'ForceAuthn' => false,
                    'isPassive' => false,
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'ReturnTo' => 'http://localhost/?service=https%3A%2F%2Fexample.com%2Fssp%2Fmodule.php%2Fcas%2Flinkback.php&scope=desktop',
                    'saml:idp' => 'http://localhost/entityId/sso/scope/A',
                ],
                [
                    'desktop' => ['http://localhost/entityId/sso/scope/A'],
                ],

            ],
        ];
    }

    #[DataProvider('loginOnAuthenticateParameters')]
    public function testAuthSourceLogin(array $requestParameters, array $loginParameters, array $scopes): void
    {
        $moduleConfig = $this->moduleConfig;
        $moduleConfig['scopes'] = $scopes;
        $casconfig = Configuration::loadFromArray($moduleConfig);
        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: $requestParameters,
        );

        $controllerMock = $this->getMockBuilder(LoginController::class)
            ->setConstructorArgs([$this->sspConfig, $casconfig, $this->authSimpleMock, $this->httpUtils])
            ->onlyMethods(['getSession'])
            ->getMock();
        $controllerMock->expects($this->once())->method('getSession')->willReturn($this->sessionMock);
        $this->authSimpleMock->expects($this->once())->method('isAuthenticated')->willReturn(false);
        $sessionId = session_create_id();
        $this->sessionMock->expects($this->once())->method('getSessionId')->willReturn($sessionId);

        $response = $controllerMock->login($loginRequest, ...$requestParameters);
        $this->assertInstanceOf(RunnableResponse::class, $response);

        // Assert we call into authSource->login
        $callable = (array)$response->getCallable();
        $this->assertEquals('login', $callable[1] ?? '');

        // Assert the interactive authenticate parameters (entityId/idpList logic)
        $arguments = $response->getArguments();
        $actualLoginParams = $arguments[0] ?? [];
        $this->assertEquals($loginParameters, $actualLoginParams);
    }

    /**
     * Check authenticated with debugMode false
     */
    public function testIsAuthenticatedRedirectsToLoggedIn(): void
    {
        $casconfig = Configuration::loadFromArray($this->moduleConfig);
        $controllerMock = $this->getMockBuilder(LoginController::class)
            ->setConstructorArgs([$this->sspConfig, $casconfig, $this->authSimpleMock, $this->httpUtils])
            ->onlyMethods(['getSession'])
            ->getMock();

        $sessionId = session_create_id();
        $this->sessionMock->expects($this->exactly(2))->method('getSessionId')->willReturn($sessionId);

        $controllerMock->expects($this->once())->method('getSession')->willReturn($this->sessionMock);
        $this->authSimpleMock->expects($this->once())->method('isAuthenticated')->willReturn(true);
        $this->authSimpleMock->expects($this->once())->method('getAuthData')->with('Expire')->willReturn(9999999999);

        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: [],
        );

        $response = $controllerMock->login($loginRequest);
        $this->assertInstanceOf(RunnableResponse::class, $response);
        $callable = (array)$response->getCallable();
        $this->assertEquals('redirectTrustedURL', $callable[1] ?? '');
    }

    public static function validServiceUrlProvider(): array
    {
        return [
            "'ticket' Query Parameter" => [
                'service',
                'https://example.com/ssp/module.php/cas/linkback.php?ticket=ST-',
                false,
            ],
            "'SAMLart' Query Parameter" => [
                'TARGET',
                'https://example.com/ssp/module.php/cas/linkback.php?SAMLart=ST-',
                false,
            ],
            "'myTicket' Query Parameter for Ticket Name Override" => [
                'TARGET',
                'https://example.com/ssp/module.php/cas/linkback.php?SAMLart=ST-',
                true,
            ],
        ];
    }

    /**
     * Test a valid service URL
     *
     * @param   string  $serviceParam  The name of the query parameter to use for the service url
     * @param   string  $redirectURL
     * @param   bool    $hasTicketNameOverride
     *
     * @throws \Exception
     */
    #[DataProvider('validServiceUrlProvider')]
    public function testValidServiceUrl(string $serviceParam, string $redirectURL, bool $hasTicketNameOverride): void
    {
        $state['Attributes'] = [
            'eduPersonPrincipalName' => ['testuser@example.com'],
            'additionalAttribute' => ['Taco Club'],
            'Expire' => 9999999999,
        ];
        $moduleConfig = $this->moduleConfig;
        if ($hasTicketNameOverride) {
            $moduleConfig['legal_service_urls']['http://changeTicketParam'] = [
                'ticketName' => 'myTicket',
            ];
        }
        $casconfig = Configuration::loadFromArray($moduleConfig);
        $controllerMock = $this->getMockBuilder(LoginController::class)
            ->setConstructorArgs([$this->sspConfig, $casconfig, $this->authSimpleMock, $this->httpUtils])
            ->onlyMethods(['getSession'])
            ->getMock();

        $sessionId = session_create_id();
        $this->sessionMock->expects($this->exactly(2))->method('getSessionId')->willReturn($sessionId);
        $this->authSimpleMock->expects($this->once())->method('getAuthData')->with('Expire')->willReturn(9999999999);
        $this->authSimpleMock->expects($this->once())->method('getAuthDataArray')->willReturn($state);

        $controllerMock->expects($this->once())->method('getSession')->willReturn($this->sessionMock);
        $this->authSimpleMock->expects($this->any())->method('isAuthenticated')->willReturn(true);
        $queryParameters = [$serviceParam => 'https://example.com/ssp/module.php/cas/linkback.php'];
        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: $queryParameters,
        );

        $response = $controllerMock->login($loginRequest, ...$queryParameters);
        $this->assertInstanceOf(RunnableResponse::class, $response);
        $arguments = $response->getArguments();
        $this->assertEquals('https://example.com/ssp/module.php/cas/linkback.php', $arguments[0]);
        $this->assertStringStartsWith('ST-', array_values($arguments[1])[0] ?? []);
        $callable = (array)$response->getCallable();
        $this->assertEquals('redirectTrustedURL', $callable[1] ?? '');
    }

    public function testGatewayPassiveDisabledRedirectsWithoutParams(): void
    {
        // enable_passive_mode disabled
        $moduleConfig = $this->moduleConfig;
        $moduleConfig['enable_passive_mode'] = false;
        $casconfig = Configuration::loadFromArray($moduleConfig);

        $params = [
            'service' => 'https://example.com/ssp/module.php/cas/linkback.php',
            'gateway' => true,
        ];
        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: $params,
        );

        $controllerMock = $this->getMockBuilder(LoginController::class)
            ->setConstructorArgs([$this->sspConfig, $casconfig, $this->authSimpleMock, $this->httpUtils])
            ->onlyMethods(['getSession'])
            ->getMock();

        // Unauthenticated so gateway path is exercised
        $this->authSimpleMock->expects($this->atLeastOnce())->method('isAuthenticated')->willReturn(false);

        // Session used to build ReturnTo
        $controllerMock->expects($this->once())->method('getSession')->willReturn($this->sessionMock);
        $this->sessionMock->expects($this->once())->method('getSessionId')->willReturn(session_create_id());

        $response = $controllerMock->login($loginRequest, ...$params);

        $this->assertInstanceOf(RunnableResponse::class, $response);
        $callable = (array)$response->getCallable();
        $this->assertEquals('redirectTrustedURL', $callable[1] ?? '');

        // Service URL unchanged and NO CAS params appended
        $arguments = $response->getArguments();
        $this->assertEquals('https://example.com/ssp/module.php/cas/linkback.php', $arguments[0]);
        $this->assertSame([], $arguments[1] ?? []);
    }

    public function testGatewayPassiveEnabledPerformsPassiveAttempt(): void
    {
        // enable_passive_mode enabled
        $moduleConfig = $this->moduleConfig;
        $moduleConfig['enable_passive_mode'] = true;
        $casconfig = Configuration::loadFromArray($moduleConfig);

        $params = [
            'service' => 'https://example.com/ssp/module.php/cas/linkback.php',
            'gateway' => true,
        ];
        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: $params,
        );

        $controllerMock = $this->getMockBuilder(LoginController::class)
            ->setConstructorArgs([$this->sspConfig, $casconfig, $this->authSimpleMock, $this->httpUtils])
            ->onlyMethods(['getSession'])
            ->getMock();

        // Unauthenticated so gateway path is exercised, first passive attempt
        $this->authSimpleMock->expects($this->atLeastOnce())->method('isAuthenticated')->willReturn(false);

        // Session used to build ReturnTo
        $controllerMock->expects($this->once())->method('getSession')->willReturn($this->sessionMock);
        $this->sessionMock->expects($this->once())->method('getSessionId')->willReturn(session_create_id());

        $response = $controllerMock->login($loginRequest, ...$params);

        $this->assertInstanceOf(RunnableResponse::class, $response);

        // Should attempt passive auth via authSource->login
        $callable = (array)$response->getCallable();
        $this->assertEquals('login', $callable[1] ?? '');

        // Verify isPassive=true, ForceAuthn=false, ReturnTo contains gatewayTried=1
        $arguments = $response->getArguments();
        $actualLoginParams = $arguments[0] ?? [];
        $this->assertArrayHasKey('ForceAuthn', $actualLoginParams);
        $this->assertFalse($actualLoginParams['ForceAuthn']);
        $this->assertArrayHasKey('isPassive', $actualLoginParams);
        $this->assertTrue($actualLoginParams['isPassive']);
        $this->assertArrayHasKey('ReturnTo', $actualLoginParams);
        $this->assertIsString($actualLoginParams['ReturnTo']);
        $this->assertStringContainsString('gatewayTried=1', $actualLoginParams['ReturnTo']);
    }

    public function testGatewayPassiveEnabledSecondPassRedirectsWithoutParams(): void
    {
        // enable_passive_mode enabled
        $moduleConfig = $this->moduleConfig;
        $moduleConfig['enable_passive_mode'] = true;
        $casconfig = Configuration::loadFromArray($moduleConfig);

        // Include gatewayTried in the Request only
        $requestParams = [
            'service' => 'https://example.com/ssp/module.php/cas/linkback.php',
            'gateway' => true,
            'gatewayTried' => '1', // simulate second pass after passive attempt
        ];
        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: $requestParams,
        );

        $controllerMock = $this->getMockBuilder(LoginController::class)
            ->setConstructorArgs([$this->sspConfig, $casconfig, $this->authSimpleMock, $this->httpUtils])
            ->onlyMethods(['getSession'])
            ->getMock();

        $this->authSimpleMock->expects($this->atLeastOnce())->method('isAuthenticated')->willReturn(false);

        $controllerMock->expects($this->once())->method('getSession')->willReturn($this->sessionMock);
        $this->sessionMock->expects($this->once())->method('getSessionId')->willReturn(session_create_id());

        // Do not pass 'gatewayTried' as named argument
        $callParams = $requestParams;
        unset($callParams['gatewayTried']);

        $response = $controllerMock->login($loginRequest, ...$callParams);

        $this->assertInstanceOf(RunnableResponse::class, $response);
        $callable = (array)$response->getCallable();
        $this->assertEquals('redirectTrustedURL', $callable[1] ?? '');

        $arguments = $response->getArguments();
        $this->assertEquals('https://example.com/ssp/module.php/cas/linkback.php', $arguments[0]);
        $this->assertSame([], $arguments[1] ?? []);
    }

    public function testGatewayNoServicePassiveDisabledFallsBackToInteractive(): void
    {
        // enable_passive_mode disabled
        $moduleConfig = $this->moduleConfig;
        $moduleConfig['enable_passive_mode'] = false;
        $casconfig = Configuration::loadFromArray($moduleConfig);

        $params = [
            'gateway' => true, // no 'service' provided
        ];
        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: $params,
        );

        $controllerMock = $this->getMockBuilder(LoginController::class)
            ->setConstructorArgs([$this->sspConfig, $casconfig, $this->authSimpleMock, $this->httpUtils])
            ->onlyMethods(['getSession'])
            ->getMock();

        $this->authSimpleMock->expects($this->atLeastOnce())->method('isAuthenticated')->willReturn(false);

        $controllerMock->expects($this->once())->method('getSession')->willReturn($this->sessionMock);
        $this->sessionMock->expects($this->once())->method('getSessionId')->willReturn(session_create_id());

        $response = $controllerMock->login($loginRequest, ...$params);

        $this->assertInstanceOf(RunnableResponse::class, $response);
        $callable = (array)$response->getCallable();
        $this->assertEquals('login', $callable[1] ?? '');

        // isPassive should be false because gateway is disabled when no service in this scenario
        $arguments = $response->getArguments();
        $loginArgs = $arguments[0] ?? [];
        $this->assertArrayHasKey('isPassive', $loginArgs);
        $this->assertFalse($loginArgs['isPassive']);
    }

    public function testRenewAndGatewayConflictDisablesPassive(): void
    {
        // enable_passive_mode enabled (doesn't matter because renew must disable passive)
        $moduleConfig = $this->moduleConfig;
        $moduleConfig['enable_passive_mode'] = true;
        $casconfig = Configuration::loadFromArray($moduleConfig);

        $params = [
            'service' => 'https://example.com/ssp/module.php/cas/linkback.php',
            'gateway' => true,
            'renew' => true,
        ];
        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: $params,
        );

        $controllerMock = $this->getMockBuilder(LoginController::class)
            ->setConstructorArgs([$this->sspConfig, $casconfig, $this->authSimpleMock, $this->httpUtils])
            ->onlyMethods(['getSession'])
            ->getMock();

        $this->authSimpleMock->expects($this->atLeastOnce())->method('isAuthenticated')->willReturn(false);

        $controllerMock->expects($this->once())->method('getSession')->willReturn($this->sessionMock);
        $this->sessionMock->expects($this->once())->method('getSessionId')->willReturn(session_create_id());

        $response = $controllerMock->login($loginRequest, ...$params);

        $this->assertInstanceOf(RunnableResponse::class, $response);
        $callable = (array)$response->getCallable();
        $this->assertEquals('login', $callable[1] ?? '');

        // isPassive must be false when renew=true (gateway disabled)
        $arguments = $response->getArguments();
        $loginArgs = $arguments[0] ?? [];
        $this->assertArrayHasKey('isPassive', $loginArgs);
        $this->assertFalse($loginArgs['isPassive']);
        $this->assertArrayHasKey('ForceAuthn', $loginArgs);
        $this->assertTrue($loginArgs['ForceAuthn']);
    }

    public function testAuthenticatedPostSubmitsViaPostWithTicket(): void
    {
        $moduleConfig = $this->moduleConfig;
        $casconfig = Configuration::loadFromArray($moduleConfig);

        $requestParams = [
            'service' => 'https://example.com/ssp/module.php/cas/linkback.php',
            'method' => 'POST',
        ];
        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: $requestParams,
        );

        // Prepare controller with mocks
        $controllerMock = $this->getMockBuilder(LoginController::class)
            ->setConstructorArgs([$this->sspConfig, $casconfig, $this->authSimpleMock, $this->httpUtils])
            ->onlyMethods(['getSession'])
            ->getMock();

        $sessionId = session_create_id();
        $controllerMock->expects($this->once())->method('getSession')->willReturn($this->sessionMock);
        $this->sessionMock->expects($this->exactly(2))->method('getSessionId')->willReturn($sessionId);

        // Simulate authenticated state and required auth data
        $this->authSimpleMock->expects($this->any())->method('isAuthenticated')->willReturn(true);
        $this->authSimpleMock->expects($this->once())->method('getAuthData')->with('Expire')->willReturn(9999999999);
        $this->authSimpleMock->expects($this->once())->method('getAuthDataArray')->willReturn([
            'Attributes' => [
                'eduPersonPrincipalName' => ['testuser@example.com'],
                'Expire' => 9999999999,
            ],
        ]);

        $response = $controllerMock->login($loginRequest, ...$requestParams);

        $this->assertInstanceOf(RunnableResponse::class, $response);
        $callable = (array)$response->getCallable();
        $this->assertEquals('submitPOSTData', $callable[1] ?? '');

        $arguments = $response->getArguments();
        $this->assertEquals('https://example.com/ssp/module.php/cas/linkback.php', $arguments[0]);
        $params = $arguments[1] ?? [];
        // default ticket name for CAS is 'ticket'
        $this->assertArrayHasKey('ticket', $params);
        $this->assertStringStartsWith('ST-', $params['ticket']);
    }

    public function testGatewayRedirectPreservesServiceQueryWithoutTicket(): void
    {
        // Passive disabled to trigger direct redirect with no ticket
        $moduleConfig = $this->moduleConfig;
        $moduleConfig['enable_passive_mode'] = false;

        // Service URL with existing query parameters
        $serviceWithQuery = 'https://example.com/ssp/module.php/cas/linkback.php?foo=1&bar=2';
        // Ensure the exact service URL (including its query string) is allowed
        $moduleConfig['legal_service_urls'] = [$serviceWithQuery];

        $casconfig = Configuration::loadFromArray($moduleConfig);

        $params = [
            'service' => $serviceWithQuery,
            'gateway' => true,
        ];

        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: $params,
        );

        $controllerMock = $this->getMockBuilder(LoginController::class)
            ->setConstructorArgs([$this->sspConfig, $casconfig, $this->authSimpleMock, $this->httpUtils])
            ->onlyMethods(['getSession'])
            ->getMock();

        // Unauthenticated so gateway path is exercised
        $this->authSimpleMock->expects($this->atLeastOnce())->method('isAuthenticated')->willReturn(false);

        // Session used to build ReturnTo
        $controllerMock->expects($this->once())->method('getSession')->willReturn($this->sessionMock);
        $this->sessionMock->expects($this->once())->method('getSessionId')->willReturn(session_create_id());

        // Execute
        $response = $controllerMock->login($loginRequest, ...$params);

        // Validate redirect with original query intact and no CAS parameters appended
        $this->assertInstanceOf(RunnableResponse::class, $response);
        $callable = (array)$response->getCallable();
        $this->assertEquals('redirectTrustedURL', $callable[1] ?? '');

        $arguments = $response->getArguments();
        // URL should be exactly the original service URL including its query string
        $this->assertEquals($serviceWithQuery, $arguments[0]);
        // No additional parameters (e.g., no ticket) should be appended by CAS
        $this->assertSame([], $arguments[1] ?? []);
    }
}
