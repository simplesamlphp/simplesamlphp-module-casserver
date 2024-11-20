<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Tests\Controller;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Compat\SspContainer;
use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Controller\LogoutController;
use Symfony\Component\HttpFoundation\Request;

class LogoutControllerTest extends TestCase
{
    private array $moduleConfig;

    private Simple $authSimpleMock;

    private SspContainer $sspContainer;

    protected function setUp(): void
    {
        $this->authSimpleMock = $this->getMockBuilder(Simple::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['logout', 'isAuthenticated'])
            ->getMock();

        $this->sspContainer = $this->getMockBuilder(SspContainer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['redirect'])
            ->getMock();

        $this->moduleConfig = [
            'ticketstore' => [
                'class' => 'casserver:FileSystemTicketStore', //Not intended for production
                'directory' => __DIR__ . '../../../../tests/ticketcache',
            ],
        ];
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

        $controller = new LogoutController($config, $this->authSimpleMock);
        $controller->logout(Request::create('/'));
    }

    public function testLogoutNoRedirectUrlOnSkipLogout(): void
    {
        $this->moduleConfig['enable_logout'] = true;
        $this->moduleConfig['skip_logout_page'] = true;
        $config = Configuration::loadFromArray($this->moduleConfig);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required URL query parameter [url] not provided. (CAS Server)');

        $controller = new LogoutController($config, $this->authSimpleMock);
        $controller->logout(Request::create('/'));
    }

    public function testLogoutNoRedirectUrlOnNoSkipLogoutUnAuthenticated(): void
    {
        $this->moduleConfig['enable_logout'] = true;
        $this->moduleConfig['skip_logout_page'] = false;
        $config = Configuration::loadFromArray($this->moduleConfig);

        // Unauthenticated
        /** @psalm-suppress UndefinedMethod */
        $this->authSimpleMock->expects($this->once())->method('isAuthenticated')->willReturn(false);
        /** @psalm-suppress UndefinedMethod */
        $this->sspContainer->expects($this->once())->method('redirect')->with(
            $this->equalTo('http://localhost/module.php/casserver/loggedOut.php'),
            [],
        );

        $controller = new LogoutController($config, $this->authSimpleMock, $this->sspContainer);
        $controller->logout(Request::create('/'));
    }
}
