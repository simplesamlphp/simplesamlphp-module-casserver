<?php

class sspmod_sbcasserver_Cas_Ticket_MemCacheTicketStore extends sspmod_sbcasserver_Cas_Ticket_TicketStore
{

    private $expireSeconds = 5;
    private $prefix = '';

    public function __construct($config)
    {
        parent::__construct($config);

        $storeConfig = $config->getValue('ticketstore');

        if (array_key_exists('expireInSeconds', $storeConfig)) {
            $this->expireSeconds = $storeConfig['expireInSeconds'];
        }

        if (array_key_exists('prefix', $storeConfig)) {
            $this->prefix = $storeConfig['prefix'];
        }
    }

    public function getTicket($ticketId)
    {
        $scopedTicketId = $this->scopeTicketId($ticketId);

        $content = SimpleSAML_Memcache::get($scopedTicketId);

        if (is_null($content)) {
            throw new Exception('Could not find ticket');
        } else {
            return $content;
        }
    }

    public function addTicket($ticket)
    {
        $scopedTicketId = $this->scopeTicketId($ticket['id']);

        SimpleSAML_Memcache::set($scopedTicketId, $ticket, $this->expireSeconds);
    }

    public function deleteTicket($ticketId)
    {
        $scopedTicketId = $this->scopeTicketId($ticketId);

        SimpleSAML_Memcache::delete($scopedTicketId);
    }

    private function scopeTicketId($ticketId)
    {
        return $this->prefix . '.' . $ticketId;
    }
}

?>