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
use SimpleSAML\Utils;

class TicketFactory
{
    /** @var int $serviceTicketExpireTime */
    private int $serviceTicketExpireTime;

    /** @var int $proxyGrantingTicketExpireTime */
    private int $proxyGrantingTicketExpireTime;

    /** @var int $proxyTicketExpireTime */
    private int $proxyTicketExpireTime;


    /**
     * @param \SimpleSAML\Configuration $config
     */
    public function __construct(Configuration $config)
    {
        $this->serviceTicketExpireTime = $config->getOptionalValue('service_ticket_expire_time', 5);
        $this->proxyGrantingTicketExpireTime = $config->getOptionalValue('proxy_granting_ticket_expire_time', 3600);
        $this->proxyTicketExpireTime = $config->getOptionalValue('proxy_ticket_expire_time', 5);
    }


    /**
     * @param string $sessionId
     * @param int $expiresAt
     * @return array
     */
    public function createSessionTicket(string $sessionId, int $expiresAt): array
    {
        $randomUtils = new Utils\Random();
        return [
            'id' => $sessionId,
            'validBefore' => $expiresAt,
            'renewId' => $randomUtils->generateID()
        ];
    }


    /**
     * @param array $content
     * @return array
     */
    public function createServiceTicket(array $content): array
    {
        $randomUtils = new Utils\Random();
        $id = str_replace('_', 'ST-', $randomUtils->generateID());
        $expiresAt = time() + $this->serviceTicketExpireTime;

        return array_merge(['id' => $id, 'validBefore' => $expiresAt], $content);
    }


    /**
     * @param array $content
     * @return array
     */
    public function createProxyGrantingTicket(array $content): array
    {
        $randomUtils = new Utils\Random();
        $id = str_replace('_', 'PGT-', $randomUtils->generateID());
        $iou = str_replace('_', 'PGTIOU-', $randomUtils->generateID());

        $expireAt = time() + $this->proxyGrantingTicketExpireTime;

        return array_merge(['id' => $id, 'iou' => $iou, 'validBefore' => $expireAt], $content);
    }


    /**
     * @param array $content
     * @return array
     */
    public function createProxyTicket(array $content): array
    {
        $randomUtils = new Utils\Random();
        $id = str_replace('_', 'PT-', $randomUtils->generateID());
        $expiresAt = time() + $this->proxyTicketExpireTime;

        return array_merge(['id' => $id, 'validBefore' => $expiresAt], $content);
    }


    /**
     * @param array $ticket
     * @return int|false
     */
    public function isSessionTicket(array $ticket)
    {
        return preg_match('/^[a-zA-Z0-9]+$/D', $ticket['id']);
    }


    /**
     * @param array $ticket
     * @return int|false
     */
    public function isServiceTicket(array $ticket)
    {
        return preg_match('/^ST-?[a-zA-Z0-9]+$/D', $ticket['id']);
    }


    /**
     * @param array $ticket
     * @return int|false
     */
    public function isProxyGrantingTicket(array $ticket)
    {
        return preg_match('/^PGT-?[a-zA-Z0-9]+$/D', $ticket['id']);
    }


    /**
     * @param array $ticket
     * @return int|false
     */
    public function isProxyTicket(array $ticket)
    {
        return preg_match('/^PT-?[a-zA-Z0-9]+$/D', $ticket['id']);
    }


    /**
     * @param array $ticket
     * @return bool
     */
    public function isExpired(array $ticket): bool
    {
        return !array_key_exists('validBefore', $ticket) || $ticket['validBefore'] < time();
    }
}
