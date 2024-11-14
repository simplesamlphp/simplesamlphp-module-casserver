<?php

declare(strict_types=1);

namespace SimpleSAML\Casserver;

use CurlHandle;
use DOMDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\TestUtils\BuiltInServer;

/**
 *
 * These integration tests use an embedded php server to avoid issues that unit tests encounter with SSP use of `exit`.
 *
 * The embedded server is authenticating users user exampleauth::static to automatically log users in.
 *
 * @package simplesamlphp\simplesamlphp-module-casserver
 */
class LoginIntegrationTest extends TestCase
{
    /** @var string $LINK_URL */
    private static string $LINK_URL = '/module.php/casserver/login.php';

    /** @var string $VALIDATE_URL */
    private static string $VALIDATE_URL = '/module.php/casserver/serviceValidate.php';

    /**
     * @var string $SAMLVALIDATE_URL
     */
    private static string $SAMLVALIDATE_URL = '/module.php/casserver/samlValidate.php';

    /**
     * @var \SimpleSAML\TestUtils\BuiltInServer
     */
    protected BuiltInServer $server;

    /**
     * @var string
     */
    protected string $server_addr;

    /**
     * @var int
     */
    protected int $server_pid;

    /**
     * @var string
     */
    protected string $shared_file;

    /**
     * @var string
     */
    protected string $cookies_file;


    /**
     * The setup method that is run before any tests in this class.
     */
    protected function setUp(): void
    {
        $this->server = new BuiltInServer(
            'configLoader',
            dirname(__FILE__, 3) . '/vendor/simplesamlphp/simplesamlphp/public',
        );
        $this->server_addr = $this->server->start();
        $this->server_pid = $this->server->getPid();
        $this->shared_file = sys_get_temp_dir() . '/' . $this->server_pid . '.lock';
        $this->cookies_file = sys_get_temp_dir() . '/' . $this->server_pid . '.cookies';

        $this->updateConfig([
            'baseurlpath' => '/',
            'secretsalt' => 'abc123',

            'tempdir' => sys_get_temp_dir(),
            'loggingdir' => sys_get_temp_dir(),
            'logging.handler' => 'file',

            'module.enable' => [
                'casserver' => true,
                'exampleauth' => true,
            ],
        ]);
    }


    /**
     * The tear down method that is executed after all tests in this class.
     * Removes the lock file and cookies file
     */
    protected function tearDown(): void
    {
        @unlink($this->shared_file);
        @unlink($this->cookies_file); // remove it if it exists
        $this->server->stop();
    }


    /**
     * @param array $config
     */
    protected function updateConfig(array $config): void
    {
        @unlink($this->shared_file);
        $file = "<?php\n\$config = " . var_export($config, true) . ";\n";
        file_put_contents($this->shared_file, $file);

        Configuration::setPreloadedConfig(Configuration::loadFromArray($config));
    }


    /**
     * Test authenticating to the login endpoint with no parameters.'
     */
    public function testNoQueryParameters(): void
    {
        $resp = $this->server->get(
            self::$LINK_URL,
            [],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
                CURLOPT_FOLLOWLOCATION => true,
            ],
        );
        $this->assertEquals(200, $resp['code']);

        $this->assertStringContainsString(
            'You are logged in.',
            $resp['body'],
            'Login with no query parameters should make you authenticate and then take you to the login page.',
        );
    }


    /**
     * Test incorrect service url
     */
    public function testWrongServiceUrl(): void
    {
        $resp = $this->server->get(
            self::$LINK_URL,
            ['service' => 'http://not-legal'],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
                CURLOPT_FOLLOWLOCATION => true,
            ],
        );
        $this->assertEquals(500, $resp['code']);

        $this->assertStringContainsString(
            'CAS server is not listed as a legal service',
            $resp['body'],
            'Illegal cas service urls should be rejected',
        );
    }


    /**
     * Test a valid service URL
     * @param string $serviceParam The name of the query parameter to use for the service url
     * @param string $ticketParam The name of the query parameter that will contain the ticket
     */
    #[DataProvider('validServiceUrlProvider')]
    public function testValidServiceUrl(string $serviceParam, string $ticketParam): void
    {
        $service_url = 'http://host1.domain:1234/path1';

        $this->authenticate();

        $resp = $this->server->get(
            self::$LINK_URL,
            [$serviceParam => $service_url],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
            ],
        );
        $this->assertEquals(303, $resp['code']);

        $this->assertStringStartsWith(
            $service_url . '?' . $ticketParam . '=ST-',
            $resp['headers']['Location'],
            'Ticket should be part of the redirect.',
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
                CURLOPT_COOKIEFILE => $this->cookies_file,
            ],
        );

        $expectedXml = simplexml_load_string(
            file_get_contents(\dirname(__FILE__, 2) . '/resources/xml/testValidServiceUrl.xml'),
        );

        $actualXml = simplexml_load_string($resp['body']);

        // We will remove the cas:authenticationDate element since we know that it will fail. The dates will not match
        $authenticationNodeToDeleteExpected = $expectedXml->xpath('//cas:authenticationDate')[0];
        $authenticationNodeToDeleteActual = $actualXml->xpath('//cas:authenticationDate')[0];
        unset($authenticationNodeToDeleteExpected[0], $authenticationNodeToDeleteActual[0]);

        $this->assertEquals(200, $resp['code']);

        $this->assertEquals(
            $expectedXml->xpath('//cas:serviceResponse')[0]->asXML(),
            $actualXml->xpath('//cas:serviceResponse')[0]->asXML(),
        );
    }

    public static function validServiceUrlProvider(): array
    {
        return [
            ['service', 'ticket'],
            ['TARGET', 'SAMLart'],
        ];
    }

    /**
     * Test changing the ticket name
     */
    public function testValidTicketNameOverride(): void
    {
        $service_url = 'http://changeTicketParam/abc';

        $this->authenticate();

        $resp = $this->server->get(
            self::$LINK_URL,
            ['TARGET' => $service_url],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
            ],
        );
        $this->assertEquals(303, $resp['code']);

        $this->assertStringStartsWith(
            $service_url . '?myTicket=ST-',
            $resp['headers']['Location'],
            'Ticket should be part of the redirect.',
        );
    }


    /**
     * Test outputting user info instead of redirecting
     */
    public function testDebugOutput(): void
    {
        $service_url = 'http://host1.domain:1234/path1';
        $this->authenticate();
        $resp = $this->server->get(
            self::$LINK_URL,
            ['service' => $service_url, 'debugMode' => 'true'],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
            ],
        );
        $this->assertEquals(200, $resp['code']);

        $this->assertStringContainsString(
            '&lt;cas:eduPersonPrincipalName&gt;testuser@example.com&lt;/cas:eduPersonPrincipalName&gt;',
            $resp['body'],
            'Attributes should have been printed.',
        );
    }


    /**
     * Test outputting user info instead of redirecting
     */
    public function testDebugOutputSamlValidate(): void
    {
        $service_url = 'http://host1.domain:1234/path1';
        $this->authenticate();
        $resp = $this->server->get(
            self::$LINK_URL,
            ['service' => $service_url, 'debugMode' => 'samlValidate'],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
            ],
        );
        $this->assertEquals(200, $resp['code']);


        $this->assertStringContainsString(
            'testuser@example.com&lt;/saml:NameIdentifier',
            $resp['body'],
            'Attributes should have been printed.',
        );
    }


    /**
     * Test outputting user info instead of redirecting
     */
    public function testAlternateServiceConfigUsed(): void
    {
        $service_url = 'https://override.example.com/somepath';
        $this->authenticate();
        $resp = $this->server->get(
            self::$LINK_URL,
            ['service' => $service_url, 'debugMode' => 'true'],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
            ],
        );
        $this->assertEquals(200, $resp['code']);
        $this->assertStringContainsString(
            '&lt;cas:user&gt;testuser&lt;/cas:user&gt;',
            $resp['body'],
            'cas:user attribute should have been overridden',
        );
        $this->assertStringContainsString(
            '&lt;cas:cn&gt;Test User&lt;/cas:cn&gt;',
            $resp['body'],
            'Attributes should have been printed with alternate attribute release',
        );
    }


    /**
     * test a valid service URL with Post
     */
    public function testValidServiceUrlWithPost(): void
    {
        $service_url = 'http://host1.domain:1234/path1';

        $this->authenticate();
        $resp = $this->server->get(
            self::$LINK_URL,
            [
                'service' => $service_url,
                'method' => 'POST',
            ],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
            ],
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
            $item->getAttribute('name'),
        );
        $this->assertStringStartsWith(
            'ST-',
            $item->getAttribute('value'),
            '',
        );
    }


    /**
     */
    public function testSamlValidate(): void
    {
        $service_url = 'http://host1.domain:1234/path1';
        $this->authenticate();

        $resp = $this->server->get(
            self::$LINK_URL,
            ['service' => $service_url],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
            ],
        );
        $this->assertEquals(303, $resp['code']);

        $this->assertStringStartsWith(
            $service_url . '?ticket=ST-',
            $resp['headers']['Location'],
            'Ticket should be part of the redirect.',
        );

        $location =  $resp['headers']['Location'];
        $matches = [];
        $this->assertEquals(1, preg_match('@ticket=(.*)@', $location, $matches));
        $ticket = $matches[1];
        $soapRequest = <<<SOAP
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
  <SOAP-ENV:Header/>
  <SOAP-ENV:Body>
    <samlp:Request xmlns:samlp="urn:oasis:names:tc:SAML:1.0:protocol"
                   MajorVersion="1"
                   MinorVersion="1"
                   RequestID="_192.168.16.51.1024506224022"
                   IssueInstant="2002-06-19T17:03:44.022Z">
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
            ],
        );

        $this->assertEquals(200, $resp['code']);
        $this->assertStringContainsString('testuser@example.com</saml:NameIdentifier>', $resp['body']);
    }


    /**
     * Sets up an authenticated session for the cookie $jar
     */
    private function authenticate(): void
    {
        // Use cookies Jar to store auth session cookies
        $resp = $this->server->get(
            self::$LINK_URL,
            [],
            [
                CURLOPT_COOKIEJAR => $this->cookies_file,
                CURLOPT_COOKIEFILE => $this->cookies_file,
                CURLOPT_FOLLOWLOCATION => true,
            ],
        );
        $this->assertEquals(200, $resp['code'], $resp['body']);
    }


    /**
     * TODO: migrate into BuiltInServer
     * @param \CurlHandle $ch
     * @return array
     */
    private function execAndHandleCurlResponse(CurlHandle $ch): array
    {
        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new \Exception('curl error: ' . curl_error($ch));
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
    public function post($query, $body, $parameters = [], $curlopts = []): array
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
