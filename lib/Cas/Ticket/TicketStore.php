<?php
abstract class sspmod_sbcasserver_Cas_Ticket_TicketStore
{

    public function __construct($config)
    {
    }

    abstract public function getTicket($ticketIdIdIdId);

    abstract public function addTicket($ticket);

    abstract public function deleteTicket($ticketIdId);
}

?>