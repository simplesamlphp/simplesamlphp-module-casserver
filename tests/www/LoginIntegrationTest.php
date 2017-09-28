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
 * # path is the current checkout of the module
 * export SIMPLESAMLPHP_CONFIG_DIR=$PWD/tests/config/
 * php -S 0.0.0.0:8732 -t $PWD/vendor/simplesamlphp/simplesamlphp/www &
 * </pre>
 *
 * @package Simplesamlphp\Casserver
 */
class LoginIntegrationTest extends \PHPUnit_Framework_TestCase
{
    /** @var string $LINK_URL */
    private static $LINK_URL = 'http://localhost:8732/module.php/casserver/login.php';


    /**
     * Test authenticating to the login endpoint with no parameters.'
     * @return void
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
            strval($response->getBody()),
            'Login with no query parameters should make you authenticate and then take you to the login page.'
        );
    }


    /**
     * Test incorrect service url
     * @return void
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
            strval($response->getBody()),
            'Illegal cas service urls should be rejected'
        );
    }


    /**
     * test a valid service URL
     * @return void
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
            'Ticket should be part of the redirect.'
        );
    }

    /**
     * test a valid service URL with Post
     */
    public function testValidServiceUrlWithPost()
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
                'query' => ['service' => $service_url, 'method' => 'POST'],
                'cookies' => $jar,
                'allow_redirects' => false, // Disable redirects since the service url can't be redirected to
            ]
        );
        // POST responds with a form that is uses JavaScript to submit
        $this->assertEquals(200, $response->getStatusCode());

        // Validate the form contains the required elements
        $body = $response->getBody()->getContents();
        $dom = new \DOMDocument;
        $dom->loadHTML($body);
        $form = $dom->getElementsByTagName('form');
        $this->assertEquals($service_url, $form[0]->getAttribute('action'));
        $formInputs = $dom->getElementsByTagName('input');
        //note: $formInputs[0] is '<input type="submit" style="display:none;" />' . See the post.php template from SSP 
        $this->assertEquals(
            'ticket',
            $formInputs[1]->getAttribute('name')

        );
        $this->assertStringStartsWith(
            'ST-',
            $formInputs[1]->getAttribute('value'),
            ''
        );
    }


    /**
     * Sets up an authenticated session for the cookie $jar
     * @param CookieJar $jar
     * @return void
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
