<?php
abstract class sspmod_sbcasserver_Cas_TicketStore_TicketStore {

  public function __construct($config) {
  }

  public function createTicket($value) {
    $ticket = $this->generateTicketId();

    $this->storeTicket($ticket,$value);

    return $ticket;
  }

  public function getTicket($ticket) {

    $this->validateTicketId();

    return $this->retrieveTicket($ticket);
  }

  public function removeTicket($ticket) {
    $this->validateTicketId();

    $this->deleteTicket($ticket);
  }

  abstract protected function generateTicketId();

  abstract protected function retrieveTicket($ticket);

  abstract protected function storeTicket($ticket, $content);

  abstract protected function deleteTicket($ticket);
  }
?>