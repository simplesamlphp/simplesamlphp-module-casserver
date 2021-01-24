<?php

declare(strict_types=1);

namespace Simplesamlphp\Casserver\Ticket;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\Ticket\DelegatingTicketStore;
use SimpleSAML\Module\casserver\Cas\Ticket\FileSystemTicketStore;
use SimpleSAML\Module\casserver\Cas\Ticket\TicketStore;
use SimpleSAML\Utils\Config;

class DelegatingTicketStoreTest extends TestCase
{
    /**
     * @var array The configuration of the ticket store
     */
    private array $ticketstoreConfig = [];

    /**
     * @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore $fileStore1
     */
    private TicketStore $fileStore1;

    /**
     * @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore $fileStore2
     */
    private TicketStore $fileStore2;


    /**
     */
    public function setup(): void
    {
        putenv('SIMPLESAMLPHP_CONFIG_DIR=' . dirname(dirname(__DIR__)) . '/config');
        Configuration::setConfigDir(Config::getConfigDir());
        $this->ticketstoreConfig = array(
            'delegateTo' => 'all',
            'ticketStores' => [
                'name1' => [
                    'class' => 'casserver:FileSystemTicketStore',
                    'directory' => dirname(dirname(__DIR__)) . '/ticketcacheAlt',
                ],
                'error' => [
                    'class' => ErroringTicketStore::class
                ],
                'name2' => [
                    'class' => 'casserver:FileSystemTicketStore',
                    'directory' => dirname(dirname(__DIR__)) . '/ticketcache',
                ],
                'misconfigured' => [
                    'class' => 'casserver:FileSystemTicketStore',
                    'directory' => 'does-not-exist',
                ],
            ]
        );

        $this->fileStore1 = new FileSystemTicketStore(
            Configuration::loadFromArray(['ticketstore' => $this->ticketstoreConfig['ticketStores']['name1']])
        );
        $this->fileStore2 = new FileSystemTicketStore(
            Configuration::loadFromArray(['ticketstore' => $this->ticketstoreConfig['ticketStores']['name2']])
        );
    }


    /**
     * Test storing and retrieving from all ticket store delegates.
     */
    public function testAll(): void
    {
        $this->ticketstoreConfig['delegateTo'] = 'all';
        $ticketStore = new DelegatingTicketStore(
            Configuration::loadFromArray(['ticketstore' => $this->ticketstoreConfig])
        );

        $ticket = ['a' => 'b', 'id' => '1'];
        $ticketStore->addTicket($ticket);


        $this->assertEquals($ticket, $ticketStore->getTicket('1'));
        $this->assertEquals($ticket, $this->fileStore1->getTicket('1'), "Ticket delegated to all stores");
        $this->assertEquals($ticket, $this->fileStore2->getTicket('1'), "Ticket delegated to all stores");

        // delete from first store
        $this->fileStore1->deleteTicket('1');
        // and read should still work
        $this->assertEquals($ticket, $ticketStore->getTicket('1'));

        // delete from all stores
        $ticketStore->deleteTicket('1');

        // an no results expected
        $this->assertNull($ticketStore->getTicket('1'));
        $this->assertNull($this->fileStore2->getTicket('1'));
    }


    public function testFirst(): void
    {
        $this->ticketstoreConfig['delegateTo'] = 'first';
        $ticketStore = new DelegatingTicketStore(
            Configuration::loadFromArray(['ticketstore' => $this->ticketstoreConfig])
        );

        $ticket = ['a' => 'b', 'id' => '1'];
        $ticketStore->addTicket($ticket);

        $this->assertEquals($ticket, $ticketStore->getTicket('1'));
        $this->assertEquals($ticket, $this->fileStore1->getTicket('1'), "Ticket only to first store");
        $this->assertNull($this->fileStore2->getTicket('1'), "Ticket shouldn't reac here");

        // delete from all stores
        $ticketStore->deleteTicket('1');

        // an no results expected
        $this->assertNull($ticketStore->getTicket('1'));
        $this->assertNull($this->fileStore1->getTicket('1'));
    }


    public function testNamed(): void
    {
        $this->ticketstoreConfig['delegateTo'] = 'name2';
        $ticketStore = new DelegatingTicketStore(
            Configuration::loadFromArray(['ticketstore' => $this->ticketstoreConfig])
        );

        $ticket = ['a' => 'b', 'id' => '1'];
        $ticketStore->addTicket($ticket);

        $this->assertEquals($ticket, $ticketStore->getTicket('1'));
        $this->assertEquals($ticket, $this->fileStore2->getTicket('1'), "Ticket only to named store");
        $this->assertNull($this->fileStore1->getTicket('1'), "Ticket should skip this one");

        // delete from all stores
        $ticketStore->deleteTicket('1');

        // an no results expected
        $this->assertNull($ticketStore->getTicket('1'));
        $this->assertNull($this->fileStore2->getTicket('1'));
    }


    /**
     * Confirm behavior of a default configuration
     */
    public function testDelegateErrorsIfNoSuccess(): void
    {
        $config = array(
            'delegateTo' => 'all',
            'ticketStores' => [
                'error' => [
                    'class' => ErroringTicketStore::class
                ]
            ]
        );

        $ticketStore = new DelegatingTicketStore(Configuration::loadFromArray(['ticketstore' => $config]));
        try {
            $ticketStore->getTicket('abc');
            $this->fail('Exceptione expected');
        } catch (\Exception $e) {
            $this->assertEquals('Sample get error', $e->getMessage());
        }
        try {
            $ticketStore->addTicket(['a' => 'b']);
            $this->fail('Exceptione expected');
        } catch (\Exception $e) {
            $this->assertEquals('Sample add error', $e->getMessage());
        }
    }
}
