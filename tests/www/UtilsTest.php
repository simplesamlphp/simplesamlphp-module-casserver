<?php

/**
 * Created by PhpStorm.
 * User: patrick
 * Date: 5/5/17
 * Time: 3:57 PM
 */

declare(strict_types=1);

namespace SimpleSAML\Casserver;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/www/utility/urlUtils.php';

class UtilsTest extends TestCase
{
    /**
     * @param string $service the service url to check
     * @param bool $allowed is the service url allowed?
     * @dataProvider checkServiceURLProvider
     */
    public function testCheckServiceURL(string $service, bool $allowed): void
    {
        $legalServices = [
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

        $this->assertEquals($allowed, checkServiceURL(sanitize($service), $legalServices), "$service validated wrong");
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
