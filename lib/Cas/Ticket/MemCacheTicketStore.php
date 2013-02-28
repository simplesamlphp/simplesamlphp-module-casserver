<?php

class sspmod_sbcasserver_Cas_Ticket_MemCacheTicketStore extends sspmod_sbcasserver_Cas_Ticket_TicketStore
{
    private $prefix = '';

    public function __construct($config)
    {
        parent::__construct($config);

        $storeConfig = $config->getValue('ticketstore');

        if (array_key_exists('prefix', $storeConfig)) {
            $this->prefix = $storeConfig['prefix'];
        }
    }

    public function getTicket($ticketId)
    {
        $scopedTicketId = $this->scopeTicketId($ticketId);

        return SimpleSAML_Memcache::get($scopedTicketId);
    }

    public function addTicket($ticket)
    {
        $scopedTicketId = $this->scopeTicketId($ticket['id']);

        SimpleSAML_Memcache::set($scopedTicketId, $ticket, $ticket['validBefore']);
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