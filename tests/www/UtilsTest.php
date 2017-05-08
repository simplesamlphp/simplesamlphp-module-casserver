<?php
/**
 * Created by PhpStorm.
 * User: patrick
 * Date: 5/5/17
 * Time: 3:57 PM
 */

namespace Simplesamlphp\Casserver;

require_once dirname(dirname(__DIR__)) . '/www/utility/urlUtils.php';

class UtilsTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @param $service string the service url to check
     * @param $allowed bool is the service url allowed?
     * @dataProvider checkServiceURLProvider
     */
    public function testCheckServiceURL($service, $allowed)
    {

        $legalServices = array(
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
        );

        $this->assertEquals($allowed, checkServiceURL(sanitize($service), $legalServices), "$service validated wrong");
    }

    public function checkServiceURLProvider()
    {
        return array(
            array('no-match', false),
            array('https://myservice.com', true),
            // maybe we should warn if there is no at least a path component of /
            array('https://myservice.com.at.somedomain', true),
            array('https://anotherservice.com.nope', false),
            array('https://anotherservice.com/anypathOk', true),
            array('https://anotherservice.com:8080/anypathOk', true),
            array('https://anotherservice.com:8080/', true),
            array('https://anotherservice.com:9999/', false),

            array('http://sub.domain.com/path/a/b/c/more?query=a', true),
            // Matching less path fails
            array('http://sub.domain.com/path/a/b/less', false),

            array('https://query.param/secure?apple=red&b=g', true),
            // Future improvement: ignore query parameter order
            //array('https://query.param/secure?b=g&apple=red', true),
            array('https://query.param/secure?b=g', false),

            array('https://encode.com/space test/', true),
            array('https://encode.com/space+test/', true),
            array('https://encode.com/space%20test/', true),

            array('https://any.subdomain.com/', true),
            array('https://two.any.subdomain.com/', true),
            array('https://path.subdomain.com/abc', true),
            array('https://subdomain.com/abc', false),

            array('https://anything-someprefix.com/abc', true),
            array('http://need_an_s-someprefix.com/abc', false),

            array('', false),
        );
    }
}
