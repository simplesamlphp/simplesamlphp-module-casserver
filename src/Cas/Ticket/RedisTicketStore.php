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

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Cas\Ticket;

use SimpleSAML\Configuration;
use SimpleSAML\Store\RedisStore;

class RedisTicketStore extends TicketStore
{
    /** @var string $prefix */
    private string $prefix = '';

    /** @var \SimpleSAML\Store\RedisStore $redis */
    private RedisStore $redis;


    /**
     * @param \SimpleSAML\Configuration $config
     */
    public function __construct(Configuration $config)
    {
        parent::__construct($config);

        $storeConfig = $config->getOptionalValue('ticketstore', []);

        if (array_key_exists('prefix', $storeConfig)) {
            $this->prefix = $storeConfig['prefix'];
        }

        $this->redis = new RedisStore();
    }


    /**
     * @param string $ticketId
     * @return array|null
     */
    public function getTicket(string $ticketId): ?array
    {
        return $this->redis->get($this->prefix, $ticketId);
    }


    /**
     * @param array $ticket
     */
    public function addTicket(array $ticket): void
    {
        $this->redis->set($this->prefix, $ticket['id'], $ticket, $ticket['validBefore']);
    }


    /**
     * @param string $ticketId
     */
    public function deleteTicket(string $ticketId): void
    {
        $this->redis->delete($this->prefix, $ticketId);
    }
}
