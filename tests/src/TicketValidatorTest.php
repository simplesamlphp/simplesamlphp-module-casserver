<?php

declare(strict_types=1);

namespace SimpleSAML\Casserver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleSAML\CAS\Constants as C;
use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\CasException;
use SimpleSAML\Module\casserver\Cas\Ticket\FileSystemTicketStore;
use SimpleSAML\Module\casserver\Cas\Ticket\TicketStore;
use SimpleSAML\Module\casserver\Cas\TicketValidator;
use SimpleSAML\XML\Utils\Random;

class TicketValidatorTest extends TestCase
{
    /**
     * @var \SimpleSAML\Module\casserver\Cas\TicketValidator
     */
    private TicketValidator $ticketValidator;

    /**
     * @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore
     */
    private TicketStore $ticketStore;


    /**
     */
    protected function setUp(): void
    {
        parent::setUp();
        Configuration::clearInternalState();
        putenv('SIMPLESAMLPHP_CONFIG_DIR=' . dirname(__DIR__) . '/config');
        $casConfig = Configuration::loadFromArray([
            'ticketstore' => [
                'class' => 'casserver:FileSystemTicketStore',
                'directory' => dirname(__DIR__) . '/ticketcache',
            ],
        ]);
        $this->ticketValidator = new TicketValidator($casConfig);
        $this->ticketStore = new FileSystemTicketStore($casConfig);
    }


    /**
     */
    public function testNonExistantTicket(): void
    {
        $id = 'no-such-ticket';
        $this->assertNull($this->ticketStore->getTicket($id));
        try {
            $this->ticketValidator->validateAndDeleteTicket($id, 'efg');
            $this->fail('exception expected');
        } catch (CasException $e) {
            $this->assertEquals(C::ERR_INVALID_TICKET, $e->getCasCode());
            $this->assertEquals('Ticket \'' . $id . '\' not recognized', $e->getMessage());
        }
    }


    /**
     */
    public function testValidTicket(): void
    {
        $serviceUrl =  'http://efh.com?a=b&';
        $serviceTicket = $this->createTicket($serviceUrl);
        $id = $serviceTicket['id'];

        $ticket = $this->ticketValidator->validateAndDeleteTicket($id, $serviceUrl);
        // ensure ticket loaded
        $this->assertEquals($serviceTicket, $ticket);
        // reloading ticket shouldn't be recognized
        $this->assertNull($this->ticketStore->getTicket($id), "ticket deleted after loading");
        try {
            $this->ticketValidator->validateAndDeleteTicket($id, $serviceUrl);
            $this->fail('exception expected');
        } catch (CasException $e) {
            $this->assertEquals(C::ERR_INVALID_TICKET, $e->getCasCode());
            $this->assertEquals('Ticket \'' . $id . '\' not recognized', $e->getMessage());
        }
    }


    /**
     */
    public function testWrongServiceUrlTicket(): void
    {
        $serviceUrl =  'http://efh.com?a=b&';
        $serviceTicket = $this->createTicket('http://otherurl.com');
        $id = $serviceTicket['id'];

        try {
            $this->ticketValidator->validateAndDeleteTicket($id, $serviceUrl);
            $this->fail('exception expected');
        } catch (CasException $e) {
            $this->assertEquals(C::ERR_INVALID_SERVICE, $e->getCasCode());
            $this->assertEquals(
                "Mismatching service parameters: expected 'http://otherurl.com' but was: 'http://efh.com?a=b&'",
                $e->getMessage(),
            );
        }
        // ensure ticket deleted after validation
        $this->assertNull($this->ticketStore->getTicket($id), "ticket deleted after loading");
    }


    /**
     */
    public function testExpiredTicket(): void
    {
        $serviceUrl =  'http://efh.com?a=b&';
        $serviceTicket = $this->createTicket($serviceUrl, -1);
        $id = $serviceTicket['id'];

        try {
            $this->ticketValidator->validateAndDeleteTicket($id, $serviceUrl);
            $this->fail('exception expected');
        } catch (CasException $e) {
            $this->assertEquals(C::ERR_INVALID_TICKET, $e->getCasCode());
            $this->assertEquals('Ticket \'' . $id . '\' has expired', $e->getMessage());
        }
        // ensure ticket deleted after validation
        $this->assertNull($this->ticketStore->getTicket($id), "ticket deleted after loading");
    }

    /**
     * @param string $serviceUrl The service url that will get sanitized
     * @param string $expectedSanitzedUrl The expected result
     */
    #[DataProvider('urlSanitizationProvider')]
    public function testUrlSanitization(string $serviceUrl, string $expectedSanitzedUrl): void
    {
        $this->assertEquals($expectedSanitzedUrl, TicketValidator::sanitize($serviceUrl));
    }

    /**
     * Urls to test
     * @return array<mixed>
     */
    public static function urlSanitizationProvider(): array
    {
        return [
            [
                'https://example.edu/kc/portal.do;jsessionid=99AC064A12?a=b',
                'https://example.edu/kc/portal.do?a=b',
            ],
            [
                'https://example.edu/kc/portal.do?a=b',
                'https://example.edu/kc/portal.do?a=b',
            ],
            [
                'https://k.edu/kc/portal.do;jsessionid=99AC064A127?ct=Search&cu=https://k.edu/kc/as.do?ssf=456*&rsol=1',
                'https://k.edu/kc/portal.do?ct=Search&cu=https://k.edu/kc/as.do?ssf=456*&rsol=1',
            ],
        ];
    }


    /**
     * Create a ticket to use for testing
     *
     * @param string $serviceUrl The service url for this ticket
     * @param int $expiration seconds from now that ticket should expire
     * @return array<mixed> the ticket contents
     */
    private function createTicket(string $serviceUrl, int $expiration = 0): array
    {
        $randomUtils = new Random();
        $id = $randomUtils->generateID();
        $serviceTicket = [
            'id' => $id,
            'validBefore' => time() + $expiration,
            'service' => $serviceUrl,
            'forceAuthn' => false,
            'userName' => 'bob',
            'attributes' => [],
            'proxies' => [],
            'sessionId' => 'sesId',
        ];
        $this->ticketStore->addTicket($serviceTicket);
        return $serviceTicket;
    }
}
