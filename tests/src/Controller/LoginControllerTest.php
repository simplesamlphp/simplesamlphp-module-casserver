<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Compat\SspContainer;
use SimpleSAML\Configuration;
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
        $this->authSimpleMock->expects($this->once())->method('login')->with($loginParameters);
        $sessionId = session_create_id();
        $this->sessionMock->expects($this->once())->method('getSessionId')->willReturn($sessionId);

        $controllerMock->login($loginRequest, ...$requestParameters);
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
        $this->httpUtils->expects($this->once())->method('redirectTrustedURL')
            ->with('http://localhost/module.php/casserver/loggedIn?');
        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: [],
        );

        $controllerMock->login($loginRequest);
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
        $this->httpUtils->expects($this->once())->method('redirectTrustedURL')
            ->withAnyParameters()
            ->willReturnCallback(function ($url) use ($redirectURL) {
                $this->assertStringStartsWith(
                    $redirectURL,
                    $url,
                    'Ticket should be part of the redirect.',
                );
            });
        $queryParameters = [$serviceParam => 'https://example.com/ssp/module.php/cas/linkback.php'];
        $loginRequest = Request::create(
            uri:        Module::getModuleURL('casserver/login'),
            parameters: $queryParameters,
        );

        /** @psalm-suppress InvalidArgument */
        $controllerMock->login($loginRequest, ...$queryParameters);
    }
}
