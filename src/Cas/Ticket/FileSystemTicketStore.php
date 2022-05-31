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

use Exception;
use SimpleSAML\Configuration;

class FileSystemTicketStore extends TicketStore
{
    /** @var string $pathToTicketDirectory */
    private string $pathToTicketDirectory;


    /**
     * @param \SimpleSAML\Configuration $config
     * @throws \Exception
     */
    public function __construct(Configuration $config)
    {
        $storeConfig = $config->getOptionalValue('ticketstore', ['directory' => 'ticketcache']);

        if (!is_string($storeConfig['directory'])) {
            throw new Exception('Invalid directory option in config.');
        }

        $path = $config->resolvePath($storeConfig['directory']);

        if (is_null($path) || !is_dir($path)) {
            throw new Exception('Directory for CAS Server ticket storage [' . strval($path) . '] does not exists.');
        }

        if (!is_writable($path)) {
            throw new Exception('Directory for CAS Server ticket storage [' . $path . '] is not writable.');
        }

        $this->pathToTicketDirectory = preg_replace('/\/$/', '', $path);
    }


    /**
     * @param string $ticketId
     * @return array|null
     */
    public function getTicket(string $ticketId): ?array
    {
        $filename = $this->pathToTicketDirectory . '/' . $ticketId;

        if (file_exists($filename)) {
            $content = file_get_contents($filename);

            return unserialize($content);
        } else {
            return null;
        }
    }


    /**
     * @param array $ticket
     */
    public function addTicket(array $ticket): void
    {
        $filename = $this->pathToTicketDirectory . '/' . $ticket['id'];
        file_put_contents($filename, serialize($ticket));
    }


    /**
     * @param string $ticketId
     */
    public function deleteTicket(string $ticketId): void
    {
        $filename = $this->pathToTicketDirectory . '/' . $ticketId;

        if (file_exists($filename)) {
            unlink($filename);
        }
    }
}
