<?php

namespace Simplesamlphp\Casserver;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

/**
 *
 * These integration tests use an embedded php server to avoid issues that unit tests encounter with SSP use of `exit`.
 *
 * The embedded server is authenticating users user exampleauth::static to automatically log users in.
 *
 * Currently you must start the embedded server by hand.
 * <pre>
 * export SIMPLESAMLPHP_CONFIG_DIR=/PATH/simplesamlphp-module-casserver/tests/config/
 * php -S 0.0.0.0:8732 -t /PATH/simplesamlphp-module-casserver/vendor/simplesamlphp/simplesamlphp/www
 * </pre>
 *
 * @package Simplesamlphp\Casserver
 */
class LoginIntegrationTest extends \PHPUnit_Framework_TestCase
{
    private static $LINK_URL = 'http://localhost:8732/module.php/casserver/login.php';

    /**
     * Test authenticating to the login endpoint with no parameters.'
     */
    public function testNoQueryParameters()
    {
        $client = new Client();
        // Use cookies Jar to store auth session cookies
        $jar = new CookieJar;
        $response = $client->get(
            self::$LINK_URL,
            [
                'cookies' => $jar
            ]
        );
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertContains(
            'You are logged in.',
            (string)$response->getBody(),
            'Login with no query parameters should make you authenticate and then take you to the login page.'
        );
    }

    /**
     * Test incorrect service url
     */
    public function testWrongServiceUrl()
    {
        $client = new Client();
        // Use cookies Jar to store auth session cookies
        $jar = new CookieJar;
        $response = $client->get(
            self::$LINK_URL,
            [
                'query' => ['service' => 'http://not-legal'],
                'http_errors' => false,
                'cookies' => $jar
            ]
        );
        $this->assertEquals(500, $response->getStatusCode());

        $this->assertContains(
            'CAS server is not listed as a legal service',
            (string)$response->getBody(),
            'Illegal cas service urls should be rejected'
        );
    }

    /**
     * test a valid service URL
     */
    public function testValidServiceUrl()
    {
        $service_url = 'http://host1.domain:1234/path1';
        $client = new Client();
        // Use cookies Jar to store auth session cookies
        $jar = new CookieJar;
        // Setup authenticated cookies
        $this->authenticate($jar);
        $response = $client->get(
            self::$LINK_URL,
            [
                'query' => ['service' => $service_url],
                'cookies' => $jar,
                'allow_redirects' => false, // Disable redirects since the service url can't be redirected to
            ]
        );
        $this->assertEquals(302, $response->getStatusCode());

        $this->assertStringStartsWith(
            $service_url . '?ticket=ST-',
            $response->getHeader('Location')[0],
            'Link should read the correct socialname from the idpdisco_saml_lastidp cookie'
        );
    }

    /**
     * Sets up an authenticated session for the cookie $jar
     * @param CookieJar $jar
     */
    private function authenticate(CookieJar $jar)
    {
        $client = new Client();
        // Use cookies Jar to store auth session cookies
        $response = $client->get(
            self::$LINK_URL,
            [
                'cookies' => $jar
            ]
        );
        $this->assertEquals(200, $response->getStatusCode());
    }
}
