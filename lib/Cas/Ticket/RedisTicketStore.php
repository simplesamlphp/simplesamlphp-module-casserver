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

namespace SimpleSAML\Module\casserver\Cas\Ticket;

use SimpleSAML\Configuration;
use SimpleSAML\Store\Redis;

class RedisTicketStore extends TicketStore
{
    /** @var string $prefix */
    private $prefix = '';

    /** @var \SimpleSAML\Store\Redis $redis */
    private $redis;


    /**
     * @param \SimpleSAML\Configuration $config
     */
    public function __construct(Configuration $config)
    {
        parent::__construct($config);

        $storeConfig = $config->getValue('ticketstore', []);

        if (array_key_exists('prefix', $storeConfig)) {
            $this->prefix = $storeConfig['prefix'];
        }

        $this->redis = new Redis();
    }


    /**
     * @param string $ticketId
     * @return array|null
     */
    public function getTicket($ticketId)
    {
        return $this->redis->get($this->prefix, $ticketId);
    }


    /**
     * @param array $ticket
     * @return void
     */
    public function addTicket(array $ticket)
    {
        $this->redis->set($this->prefix, $ticket['id'], $ticket, $ticket['validBefore']);
    }


    /**
     * @param string $ticketId
     * @return void
     */
    public function deleteTicket($ticketId)
    {
        $this->redis->delete($this->prefix, $ticketId);
    }
}
