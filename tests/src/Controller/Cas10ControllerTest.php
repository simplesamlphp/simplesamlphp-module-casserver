<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Tests\Controller;

use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\Ticket\FileSystemTicketStore;
use SimpleSAML\Module\casserver\Controller\Cas10Controller;
use SimpleSAML\Session;
use Symfony\Component\HttpFoundation\Request;

class Cas10ControllerTest extends TestCase
{
    private array $moduleConfig;

    private Session $sessionMock;

    private array $ticket;

    private string $sessionId;

    private Configuration $sspConfig;

    protected function setUp(): void
    {
        $this->sspConfig = Configuration::getConfig('config.php');
        $this->sessionId = session_create_id();
        $this->moduleConfig = [
            'ticketstore' => [
                'class' => 'casserver:FileSystemTicketStore', //Not intended for production
                'directory' => __DIR__ . '../../../../tests/ticketcache',
            ],
        ];

        $this->sessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSessionId'])
            ->getMock();

        $this->ticket = [
            'id' => 'ST-' . $this->sessionId,
            'validBefore' => 1731111111,
            // phpcs:ignore Generic.Files.LineLength.TooLong
            'service' => 'https://myservice.com/abcd',
            'forceAuthn' => false,
            'userName' => 'username@google.com',
            'attributes' =>
                [
                    'eduPersonPrincipalName' =>
                        [
                            0 => 'eduPersonPrincipalName@google.com',
                        ],
                ],
            'proxies' =>
                [
                ],
            'sessionId' => $this->sessionId,
        ];
    }

    public static function setUpBeforeClass(): void
    {
        // Some of the constructs in this test cause a Configuration to be created prior to us
        // setting the one we want to use for the test.
        Configuration::clearInternalState();

        // To make lib/SimpleSAML/Utils/HTTP::getSelfURL() work...
        global $_SERVER;
        $_SERVER['REQUEST_URI'] = '/';
    }

    public static function queryParameterValues(): array
    {
        return [
            'Has Service' => [
                ['service' => 'https://myservice.com/abcd'],
            ],
            'Has Ticket' => [
                ['ticket' => '1234567'],
            ],
            'Has Neither Service Nor Ticket' => [
                [],
            ],
        ];
    }

    /**
     * @param   array  $params
     *
     * @return void
     * @throws Exception
     */
    #[DataProvider('queryParameterValues')]
    public function testReturnBadRequestOnEmptyServiceOrTicket(array $params): void
    {
        $config = Configuration::loadFromArray($this->moduleConfig);

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $params,
        );

        $cas10Controller = new Cas10Controller($this->sspConfig, $config);
        $response = $cas10Controller->validate($request, ...$params);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals("no\n\n", $response->getContent());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testReturn500OnDeleteTicketThatThrows(): void
    {
        $config = Configuration::loadFromArray($this->moduleConfig);
        $params = [
            'ticket' => $this->sessionId,
            'service' => 'https://myservice.com/abcd',
        ];

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $params,
        );

        $ticketStore = new class ($config) extends FileSystemTicketStore {
            public function getTicket(string $ticketId): ?array
            {
                throw new Exception();
            }
        };

        /** @psalm-suppress InvalidArgument */
        $cas10Controller = new Cas10Controller($this->sspConfig, $config, $ticketStore);
        $response = $cas10Controller->validate($request, ...$params);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals("no\n\n", $response->getContent());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testReturnBadRequestOnTicketNotExist(): void
    {
        $config = Configuration::loadFromArray($this->moduleConfig);
        $params = [
            'ticket' => $this->sessionId,
            'service' => 'https://myservice.com/abcd',
        ];

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $params,
        );

        $cas10Controller = new Cas10Controller($this->sspConfig, $config);
        $response = $cas10Controller->validate($request, ...$params);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals("no\n\n", $response->getContent());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testReturnBadRequestOnTicketExpired(): void
    {
        $config = Configuration::loadFromArray($this->moduleConfig);
        $params = [
            'ticket' => 'ST-' . $this->sessionId,
            'service' => 'https://myservice.com/abcd',
        ];

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $params,
        );

        $cas10Controller = new Cas10Controller($this->sspConfig, $config);
        $ticketStore = $cas10Controller->getTicketStore();
        $ticketStore->addTicket($this->ticket);
        $response = $cas10Controller->validate($request, ...$params);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals("no\n\n", $response->getContent());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testReturnBadRequestOnTicketNotService(): void
    {
        $config = Configuration::loadFromArray($this->moduleConfig);
        $params = [
            'ticket' => $this->sessionId,
            'service' => 'https://myservice.com/abcd',
        ];

        $this->ticket['id'] = $this->sessionId;

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $params,
        );

        $cas10Controller = new Cas10Controller($this->sspConfig, $config);
        $ticketStore = $cas10Controller->getTicketStore();
        $ticketStore->addTicket($this->ticket);
        $response = $cas10Controller->validate($request, ...$params);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals("no\n\n", $response->getContent());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testReturnBadRequestOnTicketMissingUsernameField(): void
    {
        $config = Configuration::loadFromArray($this->moduleConfig);
        $params = [
            'ticket' => 'ST-' . $this->sessionId,
            'service' => 'https://myservice.com/abcd',
        ];
        $this->ticket['validBefore'] = 9999999999;
        $this->ticket['userName'] = '';

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $params,
        );

        $cas10Controller = new Cas10Controller($this->sspConfig, $config);
        $ticketStore = $cas10Controller->getTicketStore();
        $ticketStore->addTicket($this->ticket);
        $response = $cas10Controller->validate($request, ...$params);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals("no\n\n", $response->getContent());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testReturnBadRequestOnTicketServiceQueryAndTicketMismatch(): void
    {
        $config = Configuration::loadFromArray($this->moduleConfig);
        $params = [
            'ticket' => 'ST-' . $this->sessionId,
            'service' => 'https://myservice.com/failservice',
        ];
        $this->ticket['validBefore'] = 9999999999;
        $this->ticket['attributes'] = [];

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $params,
        );

        $cas10Controller = new Cas10Controller($this->sspConfig, $config);
        $ticketStore = $cas10Controller->getTicketStore();
        $ticketStore->addTicket($this->ticket);
        $response = $cas10Controller->validate($request, ...$params);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals("no\n\n", $response->getContent());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testReturnBadRequestOnTicketIssuedBySingleSignOnSession(): void
    {
        $config = Configuration::loadFromArray($this->moduleConfig);
        $params = [
            'ticket' => 'ST-' . $this->sessionId,
            'service' => 'https://myservice.com/abcd',
            'renew' => true,
        ];
        $this->ticket['validBefore'] = 9999999999;

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $params,
        );

        $cas10Controller = new Cas10Controller($this->sspConfig, $config);
        $ticketStore = $cas10Controller->getTicketStore();
        $ticketStore->addTicket($this->ticket);
        $response = $cas10Controller->validate($request, ...$params);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals("no\n\n", $response->getContent());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testSuccessfullValidation(): void
    {
        $config = Configuration::loadFromArray($this->moduleConfig);
        $params = [
            'ticket' => 'ST-' . $this->sessionId,
            'service' => 'https://myservice.com/abcd',
        ];

        $this->ticket['validBefore'] = 9999999999;

        $request = Request::create(
            uri: 'http://localhost',
            parameters: $params,
        );

        $cas10Controller = new Cas10Controller($this->sspConfig, $config);
        $ticketStore = $cas10Controller->getTicketStore();
        $ticketStore->addTicket($this->ticket);
        $response = $cas10Controller->validate($request, ...$params);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("yes\nusername@google.com\n", $response->getContent());
    }
}
