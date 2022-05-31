<?php

declare(strict_types=1);

namespace SimpleSAML\Casserver;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\ServiceValidator;

/**
 * Created by PhpStorm.
 * User: patrick
 * Date: 4/8/19
 * Time: 2:58 PM
 */
class ServiceValidatorTest extends TestCase
{
    /**
     * Test being able to override CAS configuration options per service.
     *
     * @param string $service The service url to test
     * @param array $expectedConfig The expected CAS configuration to use
     * @dataProvider overridingDataProvider
     */
    public function testOverridingServiceConfig(string $service, array $expectedConfig): void
    {
        $casConfig = [
            'attrname' => 'defaultAttribute',
            'service_ticket_expire_time' => 10,
            'authproc' => [
                [
                    'class' => 'core:AttributeMap',
                    'oid2name',
                    'urn:example' => 'example'
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
                            'urn:example' => 'example'
                        ],
                    ],
                ],
            ]
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
    public function overridingDataProvider(): array
    {
        // The expected configuration if no overrides occur
        $defaultConfig = [
            'attrname' => 'defaultAttribute',
            'service_ticket_expire_time' => 10,
            'authproc' => [
                [
                    'class' => 'core:AttributeMap',
                    'oid2name',
                    'urn:example' => 'example'
                ],
            ]
        ];
        return [
            [
                'https://myservice.com/abcd',
                [
                    'casService' => [
                        'matchingUrl' => 'https://myservice.com',
                        'serviceUrl' => 'https://myservice.com/abcd'
                    ]
                ] + $defaultConfig
            ],
            [
                'https://default.subdomain.com/abcd',
                [
                    'casService' => [
                        'matchingUrl' => '|^https://.*\.subdomain.com/|',
                        'serviceUrl' => 'https://default.subdomain.com/abcd'
                    ]
                ] + $defaultConfig
            ],
            [
                'http://override.config.com/xyz',
                [
                    'attrname' => 'alternateAttribute',
                    'casService' => [
                        'matchingUrl' => 'http://override.config.com/',
                        'serviceUrl' => 'http://override.config.com/xyz'
                    ]
                ] + $defaultConfig
            ],
            [
                'https://override.more.com/avd/qrx',
                [
                    'service_ticket_expire_time' => 5,
                    'authproc' => [
                        [
                            'class' => 'core:AttributeMap',
                            'oid2custom',
                            'urn:example' => 'example'
                        ],
                    ],
                    'casService' => [
                        'matchingUrl' => '@^https://override.more.com/avd@',
                        'serviceUrl' => 'https://override.more.com/avd/qrx'
                    ]
                ] + $defaultConfig
            ],
        ];
    }

    /**
     * Test confirming service url matching and per service configuration
     * @param string $service the service url to check
     * @param bool $allowed is the service url allowed?
     * @dataProvider checkServiceURLProvider
     */
    public function testCheckServiceURL(string $service, bool $allowed): void
    {
        $casConfig = [
            'legal_service_urls' => [
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
            ]
        ];
        $serviceValidator = new ServiceValidator(Configuration::loadFromArray($casConfig));
        $config = $serviceValidator->checkServiceURL(urldecode($service));
        $this->assertEquals($allowed, $config != null, "$service validated wrong");
    }


    /**
     * @return array
     */
    public function checkServiceURLProvider(): array
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
