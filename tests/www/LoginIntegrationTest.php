<?php

namespace Simplesamlphp\Casserver;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Test\BuiltInServer;

/**
 *
 * These integration tests use an embedded php server to avoid issues that unit tests encounter with SSP use of `exit`.
 *
 * The embedded server is authenticating users user exampleauth::static to automatically log users in.
 *
 * @package Simplesamlphp\Casserver
 */
class LoginIntegrationTest extends TestCase
{
    /** @var string $LINK_URL */
    private static $LINK_URL = '/module.php/casserver/login.php';

    /** @var string $VALIDATE_URL */
    private static $VALIDATE_URL = '/module.php/casserver/serviceValidate.php';

    /**
     * @var string $SAMLVALIDATE_URL
     */
    private static $SAMLVALIDATE_URL = '/module.php/casserver/samlValidate.php';

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
    protected function setup(): void
    {
        $this->server = new BuiltInServer();
        $this->server_addr = $this->server->start();
        $this->server_pid = $this->server->getPid();
        $this->shared_file = sys_get_temp_dir() . '/' . $this->server_pid . '.lock';
        $this->cookies_file = sys_get_temp_dir() . '/' . $this->server_pid . '.cookies';
    }


    /**
     * The tear down method that is executed after all tests in this class.
     * Removes the lock file and cookies file
     * @return void
     */
    protected function tearDown(): void
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

        $this->assertStringContainsString(
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

        $this->assertStringContainsString(
            'CAS server is not listed as a legal service',
            $resp['body'],
            'Illegal cas service urls should be rejected'
        );
    }


    /**
     * Test a valid service URL
     * @dataProvider validServiceUrlProvider
     * @param string $serviceParam The name of the query parameter to use for the service url
     * @param string $ticketParam The name of the query parameter that will contain the ticket
     * @return void
     */
    public function testValidServiceUrl(string $serviceParam, string $ticketParam)
    {
        $service_url = 'http://host1.domain:1234/path1';

        $this->authenticate();

        /** @var array $resp */
        $resp = $this->server->get(
            self::$LINK_URL,
            [$serviceParam => $service_url],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file
            ]
        );
        $this->assertEquals(302, $resp['code']);

        $this->assertStringStartsWith(
            $service_url . '?' . $ticketParam . '=ST-',
            $resp['headers']['Location'],
            'Ticket should be part of the redirect.'
        );

        // Config ticket can be validated
        $matches = [];
        $this->assertEquals(1, preg_match("@$ticketParam=(.*)@", $resp['headers']['Location'], $matches));
        $ticket = $matches[1];
        $resp = $this->server->get(
            self::$VALIDATE_URL,
            [
                $serviceParam => $service_url,
                'ticket' => $ticket,
                ],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file
            ]
        );
        $expectedResponse = '<?xml version="1.0"?>
<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
<cas:authenticationSuccess>
<cas:user>testuser@example.com</cas:user>
<cas:attributes>
<cas:eduPersonPrincipalName>testuser@example.com</cas:eduPersonPrincipalName>
<cas:base64Attributes>false</cas:base64Attributes>
</cas:attributes>
</cas:authenticationSuccess>
</cas:serviceResponse>';
        $this->assertEquals(200, $resp['code']);
        $this->assertEquals($expectedResponse, $resp['body']);
    }

    public function validServiceUrlProvider(): array
    {
        return [
            ['service', 'ticket'],
            ['TARGET', 'SAMLart']
        ];
    }

    /**
     * Test changing the ticket name
     * @return void
     */
    public function testValidTicketNameOverride()
    {
        $service_url = 'http://changeTicketParam/abc';

        $this->authenticate();

        /** @var array $resp */
        $resp = $this->server->get(
            self::$LINK_URL,
            ['TARGET' => $service_url],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file
            ]
        );
        $this->assertEquals(302, $resp['code'], $resp['body']);

        $this->assertStringStartsWith(
            $service_url . '?myTicket=ST-',
            $resp['headers']['Location'],
            'Ticket should be part of the redirect.'
        );
    }

    /**
     * Some clients urls:
     * 1. Incorrectly encode query parameters or fragments
     * 2. Encode spaces as %20 which php rencodes to +
     * 3. Special characters url encoded to lower case hexadecimal (some .net versions) are changed to upper case.
     *
     * Test to confirm these type of urls work
     * @dataProvider encodingIssueProvider
     * @param string $service_url The service url submitted by the client
     * @param string $expectedStartsWith The redirect location is expected to start with this.
     * @param string $expectedEndsWith The redirect location is expected to end with this. Used for testing #fragments.
     * @return void
     */
    public function testEncodingIssued($service_url, $expectedStartsWith, $expectedEndsWith)
    {
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
        $this->assertEquals(302, $resp['code'], $resp['body']);

        $this->assertStringStartsWith(
            $expectedStartsWith,
            $resp['headers']['Location'],
            'Ticket should be part of the redirect.'
        );
        if (!empty($expectedEndsWith)) {
            $this->assertStringEndsWith(
                $expectedEndsWith,
                $resp['headers']['Location'],
                'url fragments happen after the query params'
            );
        }
    }

    public function encodingIssueProvider(): array
    {
        $urlWithQuery = 'https://encoding.edu/bug/portal.do?solo&ct=Search%20Prot&curl=https://kc.edu/IRB.do?se=1875*&rs=1';
        $urlNoQuery = 'https://encoding.edu/bug';
        $urlMultiKeys = 'https://encoding.edu/bug?a=val1&a=val2';
        $urlWithCaseDifference = 'https://encoding.edu?url=https%3a%2f%2Fencoding.edu%3Fa%3Db';
        $urlWithSpace = 'https://encoding.edu?a=a%20space';
        $urlWithRepeatParams = 'https://encoding.edu?a=b&a=c';
        return [
            [$urlWithQuery, $urlWithQuery . '&ticket=ST-', ''],
            [$urlWithQuery . '#fragment', $urlWithQuery . '&ticket=ST-', '#fragment'],
            [$urlMultiKeys, $urlMultiKeys . '&ticket=ST-', ''],
            [$urlMultiKeys . '#fragment', $urlMultiKeys . '&ticket=ST-', '#fragment'],
            [$urlNoQuery, $urlNoQuery. '?ticket=ST-', ''],
            [$urlNoQuery . '#fragment', $urlNoQuery . '?ticket=ST-', '#fragment'],
            [$urlWithCaseDifference, $urlWithCaseDifference. '&ticket=ST-', ''],
            [$urlWithSpace, $urlWithSpace. '&ticket=ST-', ''],
            [$urlWithRepeatParams, $urlWithRepeatParams. '&ticket=ST-', ''],
        ];
    }


    /**
     * Test outputting user info instead of redirecting
     * @return void
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

        $this->assertStringContainsString(
            '&lt;cas:eduPersonPrincipalName&gt;testuser@example.com&lt;/cas:eduPersonPrincipalName&gt;',
            $resp['body'],
            'Attributes should have been printed.'
        );
    }


    /**
     * Test outputting user info instead of redirecting
     * @return void
     */
    public function testDebugOutputSamlValidate()
    {
        $service_url = 'http://host1.domain:1234/path1';
        $this->authenticate();
        /** @var array $resp */
        $resp = $this->server->get(
            self::$LINK_URL,
            ['service' => $service_url, 'debugMode' => 'samlValidate'],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file
            ]
        );
        $this->assertEquals(200, $resp['code']);


        $this->assertStringContainsString(
            'testuser@example.com&lt;/NameIdentifier',
            $resp['body'],
            'Attributes should have been printed.'
        );
    }


    /**
     * Test outputting user info instead of redirecting
     * @return void
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
        $this->assertStringContainsString(
            '&lt;cas:user&gt;testuser&lt;/cas:user&gt;',
            $resp['body'],
            'cas:user attribute should have been overridden'
        );
        $this->assertStringContainsString(
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
        $dom = new DOMDocument();
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
     * @return void
     */
    public function testSamlValidate()
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

        $location =  $resp['headers']['Location'];
        $matches = [];
        $this->assertEquals(1, preg_match('@ticket=(.*)@', $location, $matches));
        $ticket = $matches[1];
        $soapRequest = <<<SOAP
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
	<SOAP-ENV:Header/>
	<SOAP-ENV:Body>
		<samlp:Request xmlns:samlp="urn:oasis:names:tc:SAML:1.0:protocol" MajorVersion="1" MinorVersion="1" RequestID="_192.168.16.51.1024506224022" IssueInstant="2002-06-19T17:03:44.022Z">
			<samlp:AssertionArtifact>$ticket</samlp:AssertionArtifact>
		</samlp:Request>
	</SOAP-ENV:Body>
</SOAP-ENV:Envelope>
SOAP;

        $resp = $this->post(
            self::$SAMLVALIDATE_URL,
            $soapRequest,
            [
                'TARGET' => $service_url,
            ]
        );

        $this->assertEquals(200, $resp['code']);
        $this->assertStringContainsString('testuser@example.com</NameIdentifier>', $resp['body']);
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
        $this->assertEquals(200, $resp['code'], $resp['body']);
    }


    /**
     * TODO: migrate into BuiltInServer
     * @param resource $ch
     * @return array
     */
    private function execAndHandleCurlResponse($ch)
    {
        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new \Exception('curl error: ' . curl_error($ch));
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        /** @psalm-var string $resp */
        list($header, $body) = explode("\r\n\r\n", $resp, 2);
        $raw_headers = explode("\r\n", $header);
        array_shift($raw_headers);
        $headers = [];
        foreach ($raw_headers as $header) {
            list($name, $value) = explode(':', $header, 2);
            $headers[trim($name)] = trim($value);
        }
        curl_close($ch);
        return [
            'code' => $code,
            'headers' => $headers,
            'body' => $body,
        ];
    }


    /**
     * TODO: migrate into BuiltInServer
     * @param string $query The path at the embedded server to query
     * @param string|array $body The content to post
     * @param array $parameters Any query parameters to add.
     * @param array $curlopts Additional curl options
     * @return array The response code, headers and body
     */
    public function post($query, $body, $parameters = [], $curlopts = [])
    {
        $ch = curl_init();
        $url = 'http://' . $this->server_addr . $query;
        $url .= (!empty($parameters)) ? '?' . http_build_query($parameters) : '';
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 1,
            CURLOPT_POST => 1,
        ]);

        // body may be multi dimensional, so we convert it ourselves
        // https://stackoverflow.com/a/21111209/54396
        $postParam = is_string($body) ? $body : http_build_query($body);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postParam);
        curl_setopt_array($ch, $curlopts);
        return $this->execAndHandleCurlResponse($ch);
    }
}
