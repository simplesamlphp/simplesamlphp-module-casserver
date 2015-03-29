<?php
/*
*    simpleSAMLphp-casserver is a CAS 1.0 and 2.0 compliant CAS server in the form of a simpleSAMLphp module
*
*    Copyright (C) 2013  Bjorn R. Jensen
*
*    This library is free software; you can redistribute it and/or
*    modify it under the terms of the GNU Lesser General Public
*    License as published by the Free Software Foundation; either
*    version 2.1 of the License, or (at your option) any later version.
*
*    This library is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
*    Lesser General Public License for more details.
*
*    You should have received a copy of the GNU Lesser General Public
*    License along with this library; if not, write to the Free Software
*    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*
*/

class sspmod_casserver_Cas_Ticket_MemCacheTicketStore extends sspmod_casserver_Cas_Ticket_TicketStore
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
