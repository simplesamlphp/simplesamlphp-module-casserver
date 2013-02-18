<?php

class sspmod_sbcasserver_Cas_TicketStore_MemCacheTicketStore extends sspmod_sbcasserver_Cas_TicketStore_TicketStore
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

    protected function retrieveTicket($ticket)
    {
        $scopedTicketId = $this->scopeTicketId($ticket);

        $content = SimpleSAML_Memcache::get($scopedTicketId);

        if (is_null($content)) {
            throw new Exception('Could not find ticket');
        } else {
            return $content;
        }
    }

    protected function storeTicket($ticket, $value)
    {
        $scopedTicketId = $this->scopeTicketId($ticket);

        SimpleSAML_Memcache::set($scopedTicketId, $value, $this->expireSeconds);
    }

    protected function deleteTicket($ticket)
    {
        $scopedTicketId = $this->scopeTicketId($ticket);

        SimpleSAML_Memcache::delete($scopedTicketId);
    }

    private function scopeTicketId($ticketId)
    {
        return $this->attributeStorePrefix . '.' . $ticketId;
    }
}

?>