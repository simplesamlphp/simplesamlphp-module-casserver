<?php

namespace Simplesamlphp\Casserver;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\CasException;
use SimpleSAML\Module\casserver\Cas\Ticket\FileSystemTicketStore;
use SimpleSAML\Module\casserver\Cas\Ticket\TicketStore;
use SimpleSAML\Module\casserver\Cas\TicketValidator;
use SimpleSAML\Utils\Random;

/**
 * Created by PhpStorm.
 * User: patrick
 * Date: 8/23/19
 * Time: 12:13 PM
 */
class TicketValidatorTest extends TestCase
{
    /**
     * @var TicketValidator
     */
    private $ticketValidator;

    /**
     * @var TicketStore
     */
    private $ticketStore;


    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
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
     * @return void
     */
    public function testNonExistantTicket()
    {
        $id = 'no-such-ticket';
        $this->assertNull($this->ticketStore->getTicket($id));
        try {
            $this->ticketValidator->validateAndDeleteTicket($id, 'efg');
            $this->fail('exception expected');
        } catch (CasException $e) {
            $this->assertEquals('INVALID_TICKET', $e->getCasCode());
            $this->assertEquals('Ticket \'' . $id . '\' not recognized', $e->getMessage());
        }
    }


    /**
     * @return void
     */
    public function testValidTicket()
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
            $this->assertEquals('INVALID_TICKET', $e->getCasCode());
            $this->assertEquals('Ticket \'' . $id . '\' not recognized', $e->getMessage());
        }
    }


    /**
     * @return void
     */
    public function testWrongServiceUrlTicket()
    {
        $serviceUrl =  'http://efh.com?a=b&';
        $serviceTicket = $this->createTicket('http://otherurl.com');
        $id = $serviceTicket['id'];

        try {
            $this->ticketValidator->validateAndDeleteTicket($id, $serviceUrl);
            $this->fail('exception expected');
        } catch (CasException $e) {
            $this->assertEquals('INVALID_SERVICE', $e->getCasCode());
            $this->assertEquals(
                "Mismatching service parameters: expected 'http://otherurl.com' but was: 'http://efh.com?a=b&'",
                $e->getMessage()
            );
        }
        // ensure ticket deleted after validation
        $this->assertNull($this->ticketStore->getTicket($id), "ticket deleted after loading");
    }


    /**
     * @return void
     */
    public function testExpiredTicket()
    {
        $serviceUrl =  'http://efh.com?a=b&';
        $serviceTicket = $this->createTicket($serviceUrl, -1);
        $id = $serviceTicket['id'];

        try {
            $this->ticketValidator->validateAndDeleteTicket($id, $serviceUrl);
            $this->fail('exception expected');
        } catch (CasException $e) {
            $this->assertEquals('INVALID_TICKET', $e->getCasCode());
            $this->assertEquals('Ticket \'' . $id . '\' has expired', $e->getMessage());
        }
        // ensure ticket deleted after validation
        $this->assertNull($this->ticketStore->getTicket($id), "ticket deleted after loading");
    }


    /**
     * Create a ticket to use for testing
     * @param string $serviceUrl The service url for this ticket
     * @param int $expiration seconds from now that ticket should expire
     * @return array the ticket contents
     */
    private function createTicket($serviceUrl, $expiration = 0)
    {
        $id = Random::generateID();
        $serviceTicket = [
            'id' => $id,
            'validBefore' => time() + $expiration,
            'service' => $serviceUrl,
            'forceAuthn' => false,
            'userName' => 'bob',
            'attributes' => [],
            'proxies' => [],
            'sessionId' => 'sesId'
        ];
        $this->ticketStore->addTicket($serviceTicket);
        return $serviceTicket;
    }
}
