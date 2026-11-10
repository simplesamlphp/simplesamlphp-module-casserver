<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Tests\Controller\Trait;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Module\casserver\Controller\Traits\UrlTrait;
use Symfony\Component\HttpFoundation\Request;

class UrlTraitTest extends TestCase
{
    use UrlTrait;


    private const array LEGAL_SERVICES = [
        // Regular prefix match
        'https://myservice.com',
        'https://anotherservice.com/',
        'https://anotherservice.com:8080/',
        'http://sub.domain.com/path/a/b/c',
        'https://query.param/secure?apple=red',
        'https://encode.com/space test/',

        // Regex match
        '|^https://.*\.subdomain.com/|',
        '#^https://.*-someprefix.com/#',

        // Invalid settings don't blow up
        '|invalid-regex',
        '',
    ];


    /**
     * @return array
     */
    public static function checkServiceURLProvider(): array
    {
        return [
            ['no-match', false],
            ['https://myservice.com', true],
            // maybe we should warn if there is no at least a path component of /
            ['https://myservice.com.at.somedomain', true],
            ['https://anotherservice.com.nope', false],
            ['https://anotherservice.com/anypathOk', true],
            ['https://anotherservice.com:8080/anypathOk', true],
            ['https://anotherservice.com:8080/', true],
            ['https://anotherservice.com:9999/', false],

            ['http://sub.domain.com/path/a/b/c/more?query=a', true],
            // Matching less path fails
            ['http://sub.domain.com/path/a/b/less', false],

            ['https://query.param/secure?apple=red&b=g', true],
            // Future improvement: ignore query parameter order
            //['https://query.param/secure?b=g&apple=red', true],
            ['https://query.param/secure?b=g', false],

            ['https://encode.com/space test/', true],
            ['https://encode.com/space+test/', true],
            ['https://encode.com/space%20test/', true],

            ['https://any.subdomain.com/', true],
            ['https://two.any.subdomain.com/', true],
            ['https://path.subdomain.com/abc', true],
            ['https://subdomain.com/abc', false],

            ['https://anything-someprefix.com/abc', true],
            ['http://need_an_s-someprefix.com/abc', false],

            ['', false],
        ];
    }


    /**
     * @param string $service the service url to check
     * @param bool $allowed is the service url allowed?
     */
    #[DataProvider('checkServiceURLProvider')]
    public function testCheckServiceURL(string $service, bool $allowed): void
    {
        $this->assertEquals(
            $allowed,
            $this->checkServiceURL($this->sanitize($service), self::LEGAL_SERVICES),
            "$service validated wrong",
        );
    }


    /**
     * @param string $service the service url to check, as received
     * @param bool $allowed is the service url allowed?
     */
    #[DataProvider('checkServiceURLProvider')]
    public function testCheckRequestedServiceURL(string $service, bool $allowed): void
    {
        $this->assertEquals(
            $allowed,
            $this->checkRequestedServiceURL($service, self::LEGAL_SERVICES),
            "$service validated wrong",
        );
    }


    public function testCheckRequestedServiceURLRejectsEncodedSlashBeforeUserinfo(): void
    {
        $service = 'https://anotherservice.com%2Fx@evil.example/';

        // Its decoded form is legal, but the service as received has the host evil.example.
        $this->assertTrue($this->checkServiceURL($this->sanitize($service), self::LEGAL_SERVICES));
        $this->assertFalse($this->checkRequestedServiceURL($service, self::LEGAL_SERVICES));
    }


    public static function requestParameters(): array
    {
        return [
            [
                ['renew' => true, 'language' => 'Greek', 'debugMode' => true],
                ['renew' => true, 'language' => 'Greek', 'debugMode' => true],
                [],
            ],
            [
                ['renew' => true, 'language' => 'Greek', 'debugMode' => true],
                ['renew' => true, 'language' => 'Greek', 'debugMode' => true, 'renewId' => '1234'],
                [
                    'renewId' => '1234',
                ],
            ],
            [
                ['renew' => true, 'language' => 'Greek', 'debugMode' => true, 'TARGET' => 'http://localhost'],
                [
                    'renew' => true,
                    'language' => 'Greek',
                    'debugMode' => true,
                    'renewId' => '1234',
                    'TARGET' => 'http://localhost',
                ],
                [
                    'renewId' => '1234',
                ],
            ],
        ];
    }


    #[DataProvider('requestParameters')]
    public function testParseQueryParameters(array $requestParams, array $query, array $sessionTicket): void
    {
        $request = Request::create(
            uri:        '/',
            parameters: $requestParams,
        );

        $this->assertEquals($query, $this->parseQueryParameters($request, $sessionTicket));
    }
}
