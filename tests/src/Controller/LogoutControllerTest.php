<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Tests\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Controller\LogoutController;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use Symfony\Component\HttpFoundation\Request;

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

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required URL query parameter [url] not provided. (CAS Server)');

        $controller = new LogoutController($this->sspConfig, $config, $this->authSimpleMock);
        $controller->logout(Request::create('/'));
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

        $callable = $response->getCallable();
        $method = is_array($callable) ? $callable[1] : 'unknown';
        $arguments = $response->getArguments();
        $this->assertEquals('redirectTrustedURL', $method);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($urlParam, $arguments[0]);
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

        $callable = $response->getCallable();
        $method = is_array($callable) ? $callable[1] : 'unknown';
        $arguments = $response->getArguments();
        $this->assertEquals('redirectTrustedURL', $method);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('http://localhost/module.php/casserver/loggedOut', $arguments[0]);
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

        $callable = $response->getCallable();
        $method = is_array($callable) ? $callable[1] : 'unknown';
        $arguments = $response->getArguments();
        $this->assertEquals('redirectTrustedURL', $method);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('http://localhost/module.php/casserver/loggedOut', $arguments[0]);
    }

    public function testLogoutNoRedirectUrlOnNoSkipLogoutAuthenticated(): void
    {
        $this->moduleConfig['enable_logout'] = true;
        $this->moduleConfig['skip_logout_page'] = false;
        $config = Configuration::loadFromArray($this->moduleConfig);

        // Unauthenticated
        $this->authSimpleMock->expects($this->once())->method('isAuthenticated')->willReturn(true);
        $this->authSimpleMock->expects($this->once())->method('logout')
            ->with('http://localhost/module.php/casserver/loggedOut');

        $controller = new LogoutController($this->sspConfig, $config, $this->authSimpleMock, $this->httpUtils);
        $controller->logout(Request::create('/'));
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
}
