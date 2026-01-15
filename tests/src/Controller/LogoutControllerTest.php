<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Controller\LogoutController;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LogoutControllerTest extends TestCase
{
    private array $moduleConfig;

    private Simple|MockObject $authSimpleMock;

    private Utils\HTTP $httpUtils;

    private Configuration $sspConfig;

    protected function setUp(): void
    {
        $this->authSimpleMock = $this->getMockBuilder(Simple::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['logout', 'isAuthenticated'])
            ->getMock();

        $this->moduleConfig = [
            'ticketstore' => [
                'class' => 'casserver:FileSystemTicketStore', //Not intended for production
                'directory' => __DIR__ . '../../../../tests/ticketcache',
            ],
            'legal_service_urls' => [
                'https://example.com/',
                'https://valid.edu',
            ],
        ];

        $this->httpUtils = new Utils\HTTP();
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

    public static function crossProtocolLogoutReturnUrlValidatedProvider(): array
    {
        return [
          'validV3' => [
              true,
              'https://valid.edu/v3',
              true,
          ],
            'validV2' => [
                false,
                'https://valid.edu/v2',
                true,
            ],
            'invalidV3' => [
                true,
                'https://invalid.edu/v3',
                false,
            ],
            'invalidV2' => [
                false,
                'https://invalid.edu/v2',
                false,
            ],
        ];
    }

    /**
     * @param bool $isV3 query param is 'service' for v3, 'url' for v2
     * @param string $queryValue the value for the query param
     * @param bool $isValid
     * @return void
     * @throws \Exception
     */
    #[DataProvider('crossProtocolLogoutReturnUrlValidatedProvider')]
    public function testCrossProtocolLogoutReturnUrlValidated(bool $isV3, string $queryValue, bool $isValid): void
    {
        $this->moduleConfig['enable_logout'] = true;
        // CAS v3 treats this as always enabled
        $this->moduleConfig['skip_logout_page'] = false;
        $config = Configuration::loadFromArray($this->moduleConfig);

        // Unauthenticated
        $this->authSimpleMock->expects($this->once())->method('isAuthenticated')->willReturn(false);

        $controller = new LogoutController($this->sspConfig, $config, $this->authSimpleMock, $this->httpUtils);

        $logoutUrl = Module::getModuleURL('casserver/logout.php');

        $request = Request::create(
            uri: $logoutUrl,
            parameters: [ $isV3 ? 'service' : 'url' => $queryValue],
        );
        $response = $controller->logout($request, $isV3 ? null : $queryValue, $isV3 ? $queryValue : null);

        $this->validateLogoutResponse($response, $isValid ? $queryValue : null, !$isValid || !$isV3);
    }

    public function testLogoutNotAllowed(): void
    {
        $this->moduleConfig['enable_logout'] = false;
        $config = Configuration::loadFromArray($this->moduleConfig);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Logout not allowed');

        $controller = new LogoutController($this->sspConfig, $config, $this->authSimpleMock);
        $controller->logout(Request::create('/'));
    }

    public function testLogoutNoRedirectUrlOnSkipLogout(): void
    {
        $this->moduleConfig['enable_logout'] = true;
        $this->moduleConfig['skip_logout_page'] = true;
        $config = Configuration::loadFromArray($this->moduleConfig);

        $controller = new LogoutController($this->sspConfig, $config, $this->authSimpleMock);
        $response = $controller->logout(Request::create('/'));

        $this->validateLogoutResponse($response);
    }

    public function testLogoutWithRedirectUrlOnSkipLogout(): void
    {
        $this->moduleConfig['enable_logout'] = true;
        $this->moduleConfig['skip_logout_page'] = true;
        $config = Configuration::loadFromArray($this->moduleConfig);
        $urlParam = 'https://example.com/test';

        // Unauthenticated
        $this->authSimpleMock->expects($this->once())->method('isAuthenticated')->willReturn(false);

        $controller = new LogoutController($this->sspConfig, $config, $this->authSimpleMock, $this->httpUtils);

        $logoutUrl = Module::getModuleURL('casserver/logout.php');

        $request = Request::create(
            uri: $logoutUrl,
            parameters: ['url' => $urlParam],
        );
        $response = $controller->logout($request, $urlParam);
        $this->validateLogoutResponse($response, $urlParam, false);
    }

    public function testLogoutNoRedirectUrlOnNoSkipLogoutUnAuthenticated(): void
    {
        $this->moduleConfig['enable_logout'] = true;
        $this->moduleConfig['skip_logout_page'] = false;
        $config = Configuration::loadFromArray($this->moduleConfig);

        // Unauthenticated
        $this->authSimpleMock->expects($this->once())->method('isAuthenticated')->willReturn(false);

        $controller = new LogoutController($this->sspConfig, $config, $this->authSimpleMock, $this->httpUtils);
        $response = $controller->logout(Request::create('/'));
        $this->validateLogoutResponse($response);
    }

    public function testLogoutWithRedirectUrlOnNoSkipLogoutUnAuthenticated(): void
    {
        $this->moduleConfig['enable_logout'] = true;
        $this->moduleConfig['skip_logout_page'] = false;
        $config = Configuration::loadFromArray($this->moduleConfig);
        $urlParam = 'https://example.com/test';
        $logoutUrl = Module::getModuleURL('casserver/loggedOut');

        // Unauthenticated
        $this->authSimpleMock->expects($this->once())->method('isAuthenticated')->willReturn(false);

        $controller = new LogoutController($this->sspConfig, $config, $this->authSimpleMock, $this->httpUtils);
        $request = Request::create(
            uri: $logoutUrl,
            parameters: ['url' => $urlParam],
        );
        $response = $controller->logout($request, $urlParam);
        $this->validateLogoutResponse($response, $urlParam);
    }

    public function testLogoutNoRedirectUrlOnNoSkipLogoutAuthenticated(): void
    {
        $this->moduleConfig['enable_logout'] = true;
        $this->moduleConfig['skip_logout_page'] = false;
        $config = Configuration::loadFromArray($this->moduleConfig);

        // Unauthenticated
        $this->authSimpleMock->expects($this->once())->method('isAuthenticated')->willReturn(true);

        $controller = new LogoutController($this->sspConfig, $config, $this->authSimpleMock, $this->httpUtils);
        $queryParameters = ['url' => 'http://localhost/module.php/casserver/loggedOut'];
        $logoutRequest = Request::create(
            uri:        Module::getModuleURL('casserver/loggedOut'),
            parameters: $queryParameters,
        );

        $response = $controller->logout($logoutRequest, ...$queryParameters);

        $this->assertInstanceOf(RunnableResponse::class, $response);
        $callable = (array)$response->getCallable();
        $this->assertEquals('logout', $callable[1] ?? '');
    }

    public function testTicketIdGetsDeletedOnLogout(): void
    {
        $this->moduleConfig['enable_logout'] = true;
        $this->moduleConfig['skip_logout_page'] = false;
        $config = Configuration::loadFromArray($this->moduleConfig);

        $controllerMock = $this->getMockBuilder(LogoutController::class)
            ->setConstructorArgs([$this->sspConfig, $config, $this->authSimpleMock, $this->httpUtils])
            ->onlyMethods(['getSession'])
            ->getMock();

        $ticketStore = $controllerMock->getTicketStore();
        $sessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSessionId'])
            ->getMock();

        $sessionId = session_create_id();
        $sessionMock->expects($this->once())->method('getSessionId')->willReturn($sessionId);

        $ticket = [
            'id' => $sessionId,
            // phpcs:ignore Generic.Files.LineLength.TooLong
            'service' => 'https://localhost/ssp/module.php/cas/linkback.php?stateId=_332b2b157041f4fc70dd290339a05a4e915674c1f2%3Ahttps%3A%2F%2Flocalhost%2Fssp%2Fmodule.php%2Fadmin%2Ftest%2Fcasserver',
            'forceAuthn' => false,
            'userName' => 'test1@google.com',
            'attributes' =>
                [
                    'eduPersonPrincipalName' =>
                        [
                            0 => 'test@google.com',
                        ],
                ],
            'proxies' =>
                [
                ],
        ];

        $ticketStore->addTicket($ticket);
        $controllerMock->expects($this->once())->method('getSession')->willReturn($sessionMock);

        $controllerMock->logout(Request::create('/'));
        // The Ticket has been successfully deleted
        $this->assertEquals(null, $ticketStore->getTicket($ticket['id']));
    }

    /**
     * Validates common things in the logout response
     * @param Response $response The response from logout
     * @param string|null $redirectUrl The intended redirect url
     * @param bool $isShowPage If a logout page should be shown with a link to the url
     * @return void
     */
    public function validateLogoutResponse(
        Response $response,
        ?string $redirectUrl = null,
        bool $isShowPage = true,
    ): void {


        if ($isShowPage) {
            $this->assertInstanceOf(Template::class, $response);
            if (is_null($redirectUrl)) {
                $this->assertArrayNotHasKey('url', $response->data);
            } else {
                $this->assertEquals($redirectUrl, $response->data['url']);
            }
        } else {
            $this->assertInstanceOf(RunnableResponse::class, $response);
            $callable = $response->getCallable();
            $method = is_array($callable) ? $callable[1] : 'unknown';
            $arguments = $response->getArguments();
            $this->assertEquals('redirectTrustedURL', $method);
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals($redirectUrl, $arguments[0]);
        }
    }
}
