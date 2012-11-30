<?php

abstract class sspmod_sbcasserver_Cas_TicketStore_TicketStore {

  public function __construct($config) {
  }

  abstract public function createTicket($value);

  abstract public function getTicket($ticket);

  abstract public function deleteTicket($ticket);
  }
?>