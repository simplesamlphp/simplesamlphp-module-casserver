<?php

declare(strict_types=1);

namespace SimpleSAML\Casserver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\ServiceValidator;
use SimpleSAML\Module\casserver\Cas\TicketValidator;

/**
 * Created by PhpStorm.
 * User: patrick
 * Date: 4/8/19
 * Time: 2:58 PM
 */
class ServiceValidatorTest extends TestCase
{
    private const array CHECK_SERVICE_URL_CONFIG = [
        'legal_service_urls' => [
            // Regular prefix match
            'https://myservice.com',
            'https://anotherservice.com/',
            'https://anotherservice.com:8080/',
            'http://sub.domain.com/path/a/b/c',
            'https://query.param/secure?apple=red',
            'https://encode.com/space test/',
            'https://[2001:db8::1]:8443/',
            'com.example.app://callback',

            // Regex match
            '|^https://.*\.subdomain.com/|',
            '#^https://.*-someprefix.com/#',
            // A loose rule that also matches scheme-relative services
            '~(?:https:)?//schemeless\.example\.org/~',
            // Invalid settings don't blow up
            '|invalid-regex',
            '',
        ],
    ];


    /**
     * Test being able to override CAS configuration options per service.
     *
     * @param string $service The service url to test
     * @param array $expectedConfig The expected CAS configuration to use
     */
    #[DataProvider('overridingDataProvider')]
    public function testOverridingServiceConfig(string $service, array $expectedConfig): void
    {
        $casConfig = [
            'attrname' => 'defaultAttribute',
            'service_ticket_expire_time' => 10,
            'authproc' => [
                [
                    'class' => 'core:AttributeMap',
                    'oid2name',
                    'urn:example' => 'example',
                ],
            ],
            'legal_service_urls' => [
                'https://myservice.com',
                'http://override.config.com/' => [
                    'attrname' => 'alternateAttribute',
                ],
                // Regex match
                '|^https://.*\.subdomain.com/|',
                '@^https://override.more.com/avd@' => [
                    'service_ticket_expire_time' => 5,
                    'authproc' => [
                        [
                            'class' => 'core:AttributeMap',
                            'oid2custom',
                            'urn:example' => 'example',
                        ],
                    ],
                ],
            ],
        ];

        $serviceValidator = new ServiceValidator(Configuration::loadFromArray($casConfig));
        $check = $serviceValidator->checkServiceURL($service);
        $config = [];
        if ($check !== null) {
            $config = $check->toArray();
            unset($config['legal_service_urls']);
        }
        $this->assertEquals($expectedConfig, $config);
    }


    /**
     * @return array
     */
    public static function overridingDataProvider(): array
    {
        // The expected configuration if no overrides occur
        $defaultConfig = [
            'attrname' => 'defaultAttribute',
            'service_ticket_expire_time' => 10,
            'authproc' => [
                [
                    'class' => 'core:AttributeMap',
                    'oid2name',
                    'urn:example' => 'example',
                ],
            ],
        ];
        return [
            [
                'https://myservice.com/abcd',
                [
                    'casService' => [
                        'matchingUrl' => 'https://myservice.com',
                        'serviceUrl' => 'https://myservice.com/abcd',
                    ],
                ] + $defaultConfig,
            ],
            [
                'https://default.subdomain.com/abcd',
                [
                    'casService' => [
                        'matchingUrl' => '|^https://.*\.subdomain.com/|',
                        'serviceUrl' => 'https://default.subdomain.com/abcd',
                    ],
                ] + $defaultConfig,
            ],
            [
                'http://override.config.com/xyz',
                [
                    'attrname' => 'alternateAttribute',
                    'casService' => [
                        'matchingUrl' => 'http://override.config.com/',
                        'serviceUrl' => 'http://override.config.com/xyz',
                    ],
                ] + $defaultConfig,
            ],
            [
                'https://override.more.com/avd/qrx',
                [
                    'service_ticket_expire_time' => 5,
                    'authproc' => [
                        [
                            'class' => 'core:AttributeMap',
                            'oid2custom',
                            'urn:example' => 'example',
                        ],
                    ],
                    'casService' => [
                        'matchingUrl' => '@^https://override.more.com/avd@',
                        'serviceUrl' => 'https://override.more.com/avd/qrx',
                    ],
                ] + $defaultConfig,
            ],
        ];
    }

    /**
     * Test confirming service url matching and per service configuration
     * @param string $service the service url to check
     * @param bool $allowed is the service url allowed?
     */
    #[DataProvider('checkServiceURLProvider')]
    public function testCheckServiceURL(string $service, bool $allowed): void
    {
        $serviceValidator = new ServiceValidator(Configuration::loadFromArray(self::CHECK_SERVICE_URL_CONFIG));
        $config = $serviceValidator->checkServiceURL(urldecode($service));
        $this->assertEquals($allowed, $config != null, "$service validated wrong");
    }


    /**
     * A service checked as received gets the same answer as its decoded form when decoding leaves its authority alone.
     *
     * @param string $service the service url to check, as received
     * @param bool $allowed is the service url allowed?
     */
    #[DataProvider('checkServiceURLProvider')]
    public function testCheckRequestedServiceURLMatchesLikeCheckServiceURL(string $service, bool $allowed): void
    {
        $serviceValidator = new ServiceValidator(Configuration::loadFromArray(self::CHECK_SERVICE_URL_CONFIG));
        $config = $serviceValidator->checkRequestedServiceURL($service);
        $this->assertEquals($allowed, $config !== null, "$service validated wrong");
    }


    /**
     * Encoded parts of the path and query, and ';jsessionid', are still sanitized before matching.
     *
     * @param string $service the service url to check, as received
     */
    #[DataProvider('requestedServiceAcceptedProvider')]
    public function testCheckRequestedServiceURLAcceptsEncodedPathAndQuery(string $service): void
    {
        $serviceValidator = new ServiceValidator(Configuration::loadFromArray(self::CHECK_SERVICE_URL_CONFIG));
        $this->assertNotNull($serviceValidator->checkRequestedServiceURL($service), "$service validated wrong");
    }


    /**
     * @return array<string, array{string}>
     */
    public static function requestedServiceAcceptedProvider(): array
    {
        return [
            'jsessionid' => ['https://anotherservice.com/portal.do;jsessionid=99AC064A12?a=b'],
            'encoded query' => ['https://anotherservice.com/cb?stateId=_bd6b%3Ahttps%3A%2F%2Fanotherservice.com%2Fx'],
            'double encoded query' => ['https://anotherservice.com/cb?return=https%253A%252F%252Fother.example%252F'],
            'encoded path' => ['https://anotherservice.com:8080/a%2Fb%20c'],
            'encoded path under a path prefix' => ['http://sub.domain.com/path%2Fa%2Fb%2Fc/more'],
            'IPv6 literal with a port' => ['https://[2001:db8::1]:8443/app'],
            'custom scheme' => ['com.example.app://callback?state=a%2Fb'],
            'loose regex rule, with a scheme' => ['https://schemeless.example.org/app'],
        ];
    }


    /**
     * The decoded form of each service passes the legal service urls, but the service as received, which is what
     * would get the ticket, has another host, an authority that URL parsers read differently, or no leading
     * "scheme://" (from which browsers can still take a host).
     *
     * @param string $service the service url to check, as received
     */
    #[DataProvider('ambiguousAuthorityProvider')]
    public function testCheckRequestedServiceURLRejectsAmbiguousAuthority(string $service): void
    {
        $serviceValidator = new ServiceValidator(Configuration::loadFromArray(self::CHECK_SERVICE_URL_CONFIG));

        $this->assertNotNull(
            $serviceValidator->checkServiceURL(TicketValidator::sanitize($service)),
            "The decoded form of $service should be legal, or this case tests nothing",
        );
        $this->assertNull($serviceValidator->checkRequestedServiceURL($service), "$service validated wrong");
    }


    /**
     * @return array<string, array{string}>
     */
    public static function ambiguousAuthorityProvider(): array
    {
        return [
            'encoded slash before userinfo' => ['https://anotherservice.com%2Fx@evil.example/'],
            'lowercase encoded slash before userinfo' => ['https://anotherservice.com%2fx@evil.example/'],
            'encoded path prefix before userinfo' => ['http://sub.domain.com%2Fpath%2Fa%2Fb%2Fc@evil.example/'],
            'encoded slash before userinfo, regex rule' => ['https://x.subdomain.com%2F@evil.example/'],
            'userinfo' => ['https://myservice.com@evil.example/'],
            'userinfo with a port' => ['https://myservice.com:443@evil.example/'],
            'userinfo hidden by jsessionid' => ['https://myservice.com;jsessionid=1@evil.example/'],
            'tab before userinfo' => ["https://myservice.com\t@evil.example/"],
            'percent-encoded host' => ['https://myservice.com%2Eevil.example/'],
            'plus in the host' => ['https://myservice.com+.evil.example/'],
            'backslash in the host' => ['https://evil.example\\.subdomain.com/'],
            'backslash after the host' => ['https://myservice.com\\evil.example/'],
            'empty authority' => ['https:///evil.example/x.subdomain.com/'],
            'scheme and authority only after decoding' => ['https%3A%2F%2Fanotherservice.com%2F'],
            'scheme-relative' => ['//schemeless.example.org/'],
            'scheme-relative, encoded slash before userinfo' => ['//schemeless.example.org%2F@evil.example/'],
            'leading space' => [' https://schemeless.example.org%2F@evil.example/'],
            'tab inside the scheme' => ["ht\ttps://schemeless.example.org%2F@evil.example/"],
        ];
    }


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
}
