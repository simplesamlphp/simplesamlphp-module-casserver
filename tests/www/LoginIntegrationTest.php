<?php

namespace Simplesamlphp\Casserver;

use \SimpleSAML\Test\BuiltInServer;

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
    private static $LINK_URL = '/module.php/casserver/login.php';
    /**
     * @var \SimpleSAML\Test\BuiltInServer
     */
    protected $server;
    /**
     * @var string
     */
    protected $server_addr;
    /**
     * @var int
     */
    protected $server_pid;
    /**
     * @var string
     */
    protected $shared_file;

    /**
     * @var string
     */
    protected $cookies_file;

    /**
     * The setup method that is run before any tests in this class.
     * @return void
     */
    protected function setup()
    {
        $this->server = new BuiltInServer();
        $this->server_addr = $this->server->start();
        $this->server_pid = $this->server->getPid();
        $this->shared_file = sys_get_temp_dir().'/'.$this->server_pid.'.lock';
        $this->cookies_file = sys_get_temp_dir().'/'.$this->server_pid.'.cookies';
    }

    /**
     * The tear down method that is executed after all tests in this class.
     * Removes the lock file and cookies file
     * @return void
     */
    protected function tearDown()
    {
        @unlink($this->shared_file);
        @unlink($this->cookies_file); // remove it if it exists
        $this->server->stop();
    }


    /**
     * Test authenticating to the login endpoint with no parameters.'
     * @return void
     */
    public function testNoQueryParameters()
    {
        /** @var array $resp */
        $resp = $this->server->get(
            self::$LINK_URL,
            [],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
                CURLOPT_FOLLOWLOCATION => true
            ]
        );
        $this->assertEquals(200, $resp['code']);

        $this->assertContains(
            'You are logged in.',
            $resp['body'],
            'Login with no query parameters should make you authenticate and then take you to the login page.'
        );
    }


    /**
     * Test incorrect service url
     * @return void
     */
    public function testWrongServiceUrl()
    {
        /** @var array $resp */
        $resp = $this->server->get(
            self::$LINK_URL,
            ['service' => 'http://not-legal'],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
                CURLOPT_FOLLOWLOCATION => true
            ]
        );
        $this->assertEquals(500, $resp['code']);

        $this->assertContains(
            'CAS server is not listed as a legal service',
            $resp['body'],
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

        $this->authenticate();

        /** @var array $resp */
        $resp = $this->server->get(
            self::$LINK_URL,
            ['service' => $service_url],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file
            ]
        );
        $this->assertEquals(302, $resp['code']);

        $this->assertStringStartsWith(
            $service_url . '?ticket=ST-',
            $resp['headers']['Location'],
            'Ticket should be part of the redirect.'
        );
    }

    /**
     * Test outputting user info instead of redirecting
     */
    public function testDebugOutput()
    {
        $service_url = 'http://host1.domain:1234/path1';
        $this->authenticate();
        /** @var array $resp */
        $resp = $this->server->get(
            self::$LINK_URL,
            ['service' => $service_url, 'debugMode' => 'true'],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file
            ]
        );
        $this->assertEquals(200, $resp['code']);

        $this->assertContains(
            '&lt;cas:eduPersonPrincipalName&gt;testuser@example.com&lt;/cas:eduPersonPrincipalName&gt;',
            $resp['body'],
            'Attributes should have been printed.'
        );
    }

    /**
     * Test outputting user info instead of redirecting
     */
    public function testAlternateServiceConfigUsed()
    {
        $service_url = 'https://override.example.com/somepath';
        $this->authenticate();
        /** @var array $resp */
        $resp = $this->server->get(
            self::$LINK_URL,
            ['service' => $service_url, 'debugMode' => 'true'],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file
            ]
        );
        $this->assertEquals(200, $resp['code']);
        $this->assertContains(
            '&lt;cas:user&gt;testuser&lt;/cas:user&gt;',
            $resp['body'],
            'cas:user attribute should have been overridden'
        );
        $this->assertContains(
            '&lt;cas:cn&gt;Test User&lt;/cas:cn&gt;',
            $resp['body'],
            'Attributes should have been printed with alternate attribute release'
        );
    }

    /**
     * test a valid service URL with Post
     * @return void
     */
    public function testValidServiceUrlWithPost()
    {
        $service_url = 'http://host1.domain:1234/path1';

        $this->authenticate();
        /** @var array $resp */
        $resp = $this->server->get(
            self::$LINK_URL,
            [
                'service' => $service_url,
                'method' => 'POST',
            ],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file
            ]
        );

        // POST responds with a form that is uses JavaScript to submit
        $this->assertEquals(200, $resp['code']);

        // Validate the form contains the required elements
        $body = $resp['body'];
        $dom = new \DOMDocument;
        $dom->loadHTML($body);
        $form = $dom->getElementsByTagName('form');
        $item = $form->item(0);
        if (is_null($item)) {
            $this->fail('Unable to parse response.');
            return;
        }
        $this->assertEquals($service_url, $item->getAttribute('action'));
        $formInputs = $dom->getElementsByTagName('input');
        //note: $formInputs[0] is '<input type="submit" style="display:none;" />'. See the post.php template from SSP
        $item = $formInputs->item(1);
        if (is_null($item)) {
            $this->fail('Unable to parse response.');
            return;
        }
        $this->assertEquals(
            'ticket',
            $item->getAttribute('name')
        );
        $this->assertStringStartsWith(
            'ST-',
            $item->getAttribute('value'),
            ''
        );
    }


    /**
     * Sets up an authenticated session for the cookie $jar
     * @return void
     */
    private function authenticate()
    {
        // Use cookies Jar to store auth session cookies
        /** @var array $resp */
        $resp = $this->server->get(
            self::$LINK_URL,
            [],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
                CURLOPT_FOLLOWLOCATION => true
            ]
        );
        $this->assertEquals(200, $resp['code']);
    }
}
