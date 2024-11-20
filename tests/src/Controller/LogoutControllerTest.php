<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Module\casserver\Controller\LogoutController;
use Symfony\Component\HttpFoundation\Request;

class LogoutControllerTest extends TestCase
{
    /** @var LogoutController */
    private $controller;

    protected function setUp(): void
    {
        $this->controller = new LogoutController();
    }

    public static function requestParameters(): array
    {
        return [
            'no redirect url' => [''],
            'with redirect url' => ['http://example.com/redirect'],
        ];
    }

    #[DataProvider('requestParameters')]
    public function testLogout(string $redirectUrl): void
    {
        $request = Request::create(
            uri: 'https://localhost/casserver/logout',
            parameters: ['url' => $redirectUrl],
        );
    }
}