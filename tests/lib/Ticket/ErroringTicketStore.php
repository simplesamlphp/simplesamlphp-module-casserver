<?php

namespace Simplesamlphp\Casserver\Ticket;

use SimpleSAML\Module\casserver\Cas\Ticket\TicketStore;

/**
 * Ticket store that always generates errors, used for testing error handling
 */
class ErroringTicketStore extends TicketStore
{

    /**
     * @param string $ticketId The ticket id
     * @return array|null The ticket content or null if there is no such ticket
     * @throws \Exception for all invocations.
     */
    public function getTicket($ticketId)
    {
        throw new \Exception("Sample get error");
    }

    /**
     * @param array $ticket The ticket to store
     * @throws \Exception for all invocations.
     */
    public function addTicket(array $ticket)
    {
        throw new \Exception("Sample add error");
    }

    /**
     * @param string $ticketId The ticket id
     * @throws \Exception for all invocations.
     */
    public function deleteTicket($ticketId)
    {
        throw new \Exception("Sample delete error");
    }
}
