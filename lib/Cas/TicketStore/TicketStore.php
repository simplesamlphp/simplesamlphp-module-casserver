<?php
abstract class sspmod_sbcasserver_Cas_TicketStore_TicketStore
{

    public function __construct($config)
    {
    }

    public function createTicket($value)
    {
        $id = $this->generateTicketId();

        if ($this->validateTicketId($id)) {
            $this->storeTicket($id, $value);

            return $id;
        } else {
            throw new Exception('Cas ticket id generator is creating invalid ids: ' . $id);
        }
    }

    public function createProxyGrantingTicket($value)
    {
        $id = $this->generateProxyGrantingTicketId();
        $iou = $this->generateProxyGrantingTicketIOU();

        if ($this->validateTicketId($id)) {
            $this->storeTicket($id, $value);

            return array('id' => $id, 'iou' => $iou);
        } else {
            throw new Exception('Cas ticket id generator is creating invalid ids: ' . $id);
        }
    }

    public function createProxyTicket($value)
    {
        $id = $this->generateTicketId();

        if ($this->validateTicketId($id)) {
            $this->storeTicket($id, $value);

            return $id;
        } else {
            throw new Exception('Cas ticket id generator is creating invalid ids: ' . $id);
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

    protected function generateProxyGrantingTicketId()
    {
        return str_replace('_', 'PGT-', SimpleSAML_Utilities::generateID());
    }

    protected function generateProxyGrantingTicketIOU()
    {
        return str_replace('_', 'PGTIOU-', SimpleSAML_Utilities::generateID());
    }

    protected function generateProxyTicketId()
    {
        return str_replace('_', 'PT-', SimpleSAML_Utilities::generateID());
    }

    abstract protected function retrieveTicket($ticket);

    abstract protected function storeTicket($ticket, $content);

    abstract protected function deleteTicket($ticket);
}

?>