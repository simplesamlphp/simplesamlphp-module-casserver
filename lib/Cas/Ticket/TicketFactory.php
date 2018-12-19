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

class TicketFactory
{
    private $serviceTicketExpireTime;
    private $proxyGrantingTicketExpireTime;
    private $proxyTicketExpireTime;

    public function __construct(\SimpleSAML_Configuration $config)
    {
        $this->serviceTicketExpireTime = $config->getValue('service_ticket_expire_time', 5);
        $this->proxyGrantingTicketExpireTime = $config->getValue('proxy_granting_ticket_expire_time', 3600);
        $this->proxyTicketExpireTime = $config->getValue('proxy_ticket_expire_time', 5);
    }

    /**
     * @param $sessionId string
     * @param $expiresAt int
     * @return array
     */
    public function createSessionTicket($sessionId, $expiresAt)
    {
        return array(
            'id' => $sessionId,
            'validBefore' => $expiresAt,
            'renewId' => SimpleSAML\Utils\Random::generateID()
        );
    }

    /**
     * @param $content array
     * @return array
     */
    public function createServiceTicket(array $content)
    {
        $id = str_replace('_', 'ST-', SimpleSAML\Utils\Random::generateID());
        $expiresAt = time() + $this->serviceTicketExpireTime;

        return array_merge(array('id' => $id, 'validBefore' => $expiresAt), $content);
    }

    /**
     * @param $content array
     * @return array
     */
    public function createProxyGrantingTicket(array $content)
    {
        $id = str_replace('_', 'PGT-', SimpleSAML\Utils\Random::generateID());
        $iou = str_replace('_', 'PGTIOU-', SimpleSAML\Utils\Random::generateID());

        $expireAt = time() + $this->proxyGrantingTicketExpireTime;

        return array_merge(array('id' => $id, 'iou' => $iou, 'validBefore' => $expireAt), $content);
    }

    /**
     * @param $content array
     * @return array
     */
    public function createProxyTicket(array $content)
    {
        $id = str_replace('_', 'PT-', SimpleSAML\Utils\Random::generateID());
        $expiresAt = time() + $this->proxyTicketExpireTime;

        return array_merge(array('id' => $id, 'validBefore' => $expiresAt), $content);
    }

    /**
     * @param $ticket array
     * @return int|false
     */
    public function isSessionTicket(array $ticket)
    {
        return preg_match('/^[a-zA-Z0-9]+$/D', $ticket['id']);
    }

    /**
     * @param $ticket array
     * @return int|false
     */
    public function isServiceTicket(array $ticket)
    {
        return preg_match('/^ST-?[a-zA-Z0-9]+$/D', $ticket['id']);
    }

    /**
     * @param $ticket array
     * @return int|false
     */
    public function isProxyGrantingTicket(array $ticket)
    {
        return preg_match('/^PGT-?[a-zA-Z0-9]+$/D', $ticket['id']);
    }

    /**
     * @param $ticket array
     * @return int|false
     */
    public function isProxyTicket(array $ticket)
    {
        return preg_match('/^PT-?[a-zA-Z0-9]+$/D', $ticket['id']);
    }

    /**
     * @param $ticket array
     * @return bool
     */
    public function isExpired(array $ticket)
    {
        return !array_key_exists('validBefore', $ticket) || $ticket['validBefore'] < time();
    }
}
