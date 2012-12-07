<?php
abstract class sspmod_sbcasserver_Cas_TicketStore_TicketStore
{

    public function __construct($config)
    {
    }

    public function createTicket($value)
    {
        $ticket = $this->generateTicketId();

        if ($this->validateTicketId($ticket)) {
            $this->storeTicket($ticket, $value);

            return $ticket;
        } else {
            throw new Exception('Cas ticket id generator is creating invalid ids: ' . $ticket);
        }
    }

    public function getTicket($ticket)
    {
        if ($this->validateTicketId($ticket)) {
            return $this->retrieveTicket($ticket);
        } else {
            return null;
        }
    }

    public function removeTicket($ticket)
    {
        if ($this->validateTicketId($ticket)) {
            $this->deleteTicket($ticket);
        }
    }

    protected function generateTicketId()
    {
        return str_replace('_', 'ST-', SimpleSAML_Utilities::generateID());
    }

    protected function validateTicketId($ticket)
    {
        return preg_match('/^(ST|PT|PGT)-?[a-zA-Z0-9]+$/D', $ticket);
    }

    abstract protected function retrieveTicket($ticket);

    abstract protected function storeTicket($ticket, $content);

    abstract protected function deleteTicket($ticket);
}

?>