<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Cas\Ticket;

use Exception;
use InvalidArgumentException;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module;

/**
 * Ticket store that delegates to other ticket stores.
 * Allows you to use multiple source for redundancy or allows a transition from one source to another.
 */
class DelegatingTicketStore extends TicketStore
{
    /**
     * @var string Delegate to 'all', 'first', or a named entry.
     */
    private string $delegateTo = 'all';

    /**
     * @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore[]
     */
    private array $ticketStores = [];

    /**
     * @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore
     */
    private TicketStore $primaryDelegate;


    /**
     * @param \SimpleSAML\Configuration $casConfig The cas configuration.
     */
    public function __construct(Configuration $casConfig)
    {
        $config = $casConfig->getConfigItem('ticketstore');
        $this->delegateTo = $config->getOptionalString('delegateTo', 'all');
        foreach ($config->getArray('ticketStores') as $name => $storeArray) {
            // TicketStore expects the store config to be in a specific item
            $storeConfig = Configuration::loadFromArray(['ticketstore' => $storeArray]);
            $class = $storeConfig->getConfigItem('ticketstore')->getString('class');
            $ticketStoreClass = Module::resolveClass($class, 'Cas\Ticket');
            try {
                /**
                 * @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore $ticketStore
                 */
                $ticketStore = new $ticketStoreClass($storeConfig);
                $this->ticketStores[$name] = $ticketStore;
            } catch (Exception $e) {
                Logger::error("Unable to create ticket store '$name'. Error " . $e->getMessage());
            }
        }
        Assert::notEmpty($this->ticketStores, "'ticketStores' must be defined.");
        $this->ticketStores = $this->ticketStores;

        if ($this->delegateTo === 'first') {
            $this->primaryDelegate = reset($this->ticketStores);
        } elseif ($this->delegateTo !== 'all') {
            if (array_key_exists($this->delegateTo, $this->ticketStores)) {
                $this->primaryDelegate = $this->ticketStores[$this->delegateTo];
            } else {
                throw new InvalidArgumentException("No ticket store called '" . $this->delegateTo . "'");
            }
        }
    }

    /**
     * Get the ticket, searching one or all of the delegates
     * @param string $ticketId The ticket to find
     * @return array|null The ticket or null if none found
     * @throws \Exception from any delegate stores ONLY if no delegates worked
     */
    public function getTicket(string $ticketId): ?array
    {
        if ($this->delegateTo === 'all') {
            $ticket = null;
            $rethrowException = null;
            foreach ($this->ticketStores as $name => $store) {
                try {
                    $ticket = $store->getTicket($ticketId);
                    $rethrowException = false; // no need to rethrow, at least one store worked
                } catch (Exception $e) {
                    if ($rethrowException === null) {
                        $rethrowException = $e;
                    }
                    Logger::error("Unable to read tickets from '$name'. Trying next store. Error " . $e->getMessage());
                }
                if ($ticket) {
                    return $ticket;
                }
            }
            if ($rethrowException) {
                throw $rethrowException;
            }
            return $ticket;
        } else {
            return $this->primaryDelegate->getTicket($ticketId);
        }
    }

    /**
     * @param array $ticket Ticket to add
     * @throws \Exception from any delegate stores ONLY if no delegates worked
     */
    public function addTicket(array $ticket): void
    {
        if ($this->delegateTo === 'all') {
            $rethrowException = null;
            foreach ($this->ticketStores as $name => $store) {
                try {
                    $store->addTicket($ticket);
                    $rethrowException = false; // no need to rethrow, at least one store worked
                } catch (Exception $e) {
                    if ($rethrowException === null) {
                        $rethrowException = $e;
                    }
                    Logger::error("Unable to add ticket to '$name'. Continue to next store. Error" . $e->getMessage());
                }
            }
            if ($rethrowException) {
                throw $rethrowException;
            }
        } else {
            $this->primaryDelegate->addTicket($ticket);
        }
    }


    /**
     * @param string $ticketId Ticket to delete
     */
    public function deleteTicket(string $ticketId): void
    {
        if ($this->delegateTo === 'all') {
            foreach ($this->ticketStores as $name => $store) {
                try {
                    $store->deleteTicket($ticketId);
                } catch (Exception $e) {
                    Logger::error("Unable to delete ticket from '$name'. Trying next store. Error" . $e->getMessage());
                }
            }
        } else {
            $this->primaryDelegate->deleteTicket($ticketId);
        }
    }
}
