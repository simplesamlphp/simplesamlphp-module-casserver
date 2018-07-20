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

class sspmod_casserver_Cas_Ticket_RedisTicketStore extends sspmod_casserver_Cas_Ticket_TicketStore
{
    private $prefix = '';
    private $redis;

    public function __construct(\SimpleSAML_Configuration $config)
    {
        parent::__construct($config);

        $storeConfig = $config->getValue('ticketstore');

        if (array_key_exists('prefix', $storeConfig)) {
            $this->prefix = $storeConfig['prefix'];
        }

        $this->redis = new \SimpleSAML\Store\Redis();
    }

    /**
     * @param $ticketId string
     * @return array|null
     */
    public function getTicket($ticketId)
    {
        return $this->redis->get($this->prefix, $ticketId);
    }

    public function addTicket(array $ticket)
    {
        $this->redis->set($this->prefix, $ticket['id'], $ticket, $ticket['validBefore']);
    }

    /**
     * @param $ticketId string
     */
    public function deleteTicket($ticketId)
    {
        $this->redis->delete($this->prefix, $ticketId);
    }
}
