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
use PDO;
use PDOException;
use PDOStatement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;

class SQLTicketStore extends TicketStore
{
    /** @var \PDO $pdo */
    public PDO $pdo;

    /** @var string $driver */
    public string $driver = 'pdo';

    /** @var string $prefix */
    public string $prefix;

    /** @var array $tableVersions */
    private array $tableVersions = [];


    /**
     * @param \SimpleSAML\Configuration $config
     */
    public function __construct(Configuration $config)
    {
        parent::__construct($config);

        $storeConfig = $config->getConfigItem('ticketstore');
        $dsn = $storeConfig->getString('dsn');
        $username = $storeConfig->getString('username');
        $password = $storeConfig->getString('password');
        $options = $storeConfig->getOptionalArray('options', []);
        $this->prefix = $storeConfig->getOptionalString('prefix', '');

        $this->pdo = new PDO($dsn, $username, $password, $options);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($this->driver === 'mysql') {
            $this->pdo->exec('SET time_zone = "+00:00"');
        }

        $this->initTableVersionTable();
        $this->initKVTable();
    }


    /**
     * @param string $ticketId
     * @return array|null
     */
    public function getTicket(string $ticketId): ?array
    {
        $scopedTicketId = $this->scopeTicketId($ticketId);

        return $this->get($scopedTicketId);
    }


    /**
     * @param array $ticket
     */
    public function addTicket(array $ticket): void
    {
        $scopedTicketId = $this->scopeTicketId($ticket['id']);

        $this->set($scopedTicketId, $ticket, $ticket['validBefore']);
    }


    /**
     * @param string $ticketId
     */
    public function deleteTicket(string $ticketId): void
    {
        $scopedTicketId = $this->scopeTicketId($ticketId);

        $this->delete($scopedTicketId);
    }


    /**
     * @param string $ticketId
     * @return string
     */
    private function scopeTicketId(string $ticketId): string
    {
        return $this->prefix . '.' . $ticketId;
    }


    /**
     */
    private function initTableVersionTable(): void
    {
        $this->tableVersions = [];

        try {
            $fetchTableVersion = $this->pdo->query('SELECT _name, _version FROM ' . $this->prefix . '_tableVersion');
        } catch (PDOException $e) {
            $this->pdo->exec('CREATE TABLE ' . $this->prefix .
                '_tableVersion (_name VARCHAR(30) NOT NULL UNIQUE, _version INTEGER NOT NULL)');
            return;
        }

        while (($row = $fetchTableVersion->fetch(PDO::FETCH_ASSOC)) !== false) {
            $this->tableVersions[$row['_name']] = intval($row['_version']);
        }
    }


    /**
     */
    private function initKVTable(): void
    {
        if ($this->getTableVersion('kvstore') === 1) {
            /* Table initialized. */
            return;
        }

        $query = 'CREATE TABLE ' . $this->prefix .
            '_kvstore (_key VARCHAR(50) NOT NULL, _value TEXT NOT NULL, _expire TIMESTAMP, PRIMARY KEY (_key))';
        $this->pdo->exec($query);

        $query = 'CREATE INDEX ' . $this->prefix . '_kvstore_expire ON ' . $this->prefix . '_kvstore (_expire)';
        $this->pdo->exec($query);

        $this->setTableVersion('kvstore', 1);
    }


    /**
     * @param string $name
     * @return int
     */
    private function getTableVersion(string $name): int
    {
        if (!isset($this->tableVersions[$name])) {
            return 0;
        }

        return $this->tableVersions[$name];
    }


    /**
     * @param string $name
     * @param int $version
     */
    private function setTableVersion(string $name, int $version): void
    {
        $this->insertOrUpdate(
            $this->prefix . '_tableVersion',
            ['_name'],
            [
                '_name' => $name,
                '_version' => $version,
            ],
        );
        $this->tableVersions[$name] = $version;
    }


    /**
     * @param string $table
     * @param array $keys
     * @param array $data
     */
    private function insertOrUpdate(string $table, array $keys, array $data): void
    {
        $colNames = '(' . implode(', ', array_keys($data)) . ')';
        $values = 'VALUES(:' . implode(', :', array_keys($data)) . ')';

        switch ($this->driver) {
            case 'mysql':
                $query = 'REPLACE INTO ' . $table . ' ' . $colNames . ' ' . $values;
                $query = $this->pdo->prepare($query);
                $query->execute($data);
                return;
            case 'sqlite':
                $query = 'INSERT OR REPLACE INTO ' . $table . ' ' . $colNames . ' ' . $values;
                $query = $this->pdo->prepare($query);
                $query->execute($data);
                return;
            default:
                /* Default implementation. Try INSERT, and UPDATE if that fails. */

                $insertQuery = 'INSERT INTO ' . $table . ' ' . $colNames . ' ' . $values;
                $insertQuery = $this->pdo->prepare($insertQuery);

                if ($insertQuery === false) {
                    throw new Exception("Error preparing statement.");
                }
                $this->insertOrUpdateFallback($table, $keys, $data, $insertQuery);
                return;
        }
    }


    /**
     * @param string $table
     * @param array $keys
     * @param array $data
     * @param \PDOStatement $insertQuery
     */
    private function insertOrUpdateFallback(string $table, array $keys, array $data, PDOStatement $insertQuery): void
    {
        try {
            $insertQuery->execute($data);
            return;
        } catch (PDOException $e) {
            $ecode = strval($e->getCode());
            switch ($ecode) {
                case '23505': /* PostgreSQL */
                    break;
                default:
                    Logger::error('casserver: Error while saving data: ' . $e->getMessage());
                    throw $e;
            }
        }

        $updateCols = [];
        $condCols = [];

        foreach ($data as $col => $value) {
            $tmp = $col . ' = :' . $col;

            if (in_array($col, $keys, true)) {
                $condCols[] = $tmp;
            } else {
                $updateCols[] = $tmp;
            }
        }

        $updateCols = implode(',', $updateCols);
        $condCols = implode(' AND ', $condCols);
        $updateQuery = 'UPDATE ' . $table . ' SET ' . $updateCols . ' WHERE ' . $condCols;
        $updateQuery = $this->pdo->prepare($updateQuery);
        $updateQuery->execute($data);
    }


    /**
     */
    private function cleanKVStore(): void
    {
        $query = 'DELETE FROM ' . $this->prefix . '_kvstore WHERE _expire < :now';
        $params = ['now' => gmdate('Y-m-d H:i:s')];

        $query = $this->pdo->prepare($query);
        $query->execute($params);
    }


    /**
     * @param string $key
     * @return array|null
     */
    private function get(string $key): ?array
    {
        if (strlen($key) > 50) {
            $key = sha1($key);
        }

        $query = 'SELECT _value FROM ' . $this->prefix .
            '_kvstore WHERE _key = :key AND (_expire IS NULL OR _expire > :now)';
        $params = ['key' => $key, 'now' => gmdate('Y-m-d H:i:s')];

        $query = $this->pdo->prepare($query);
        $query->execute($params);

        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $value = $row['_value'];
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        $value = urldecode($value);
        $value = unserialize($value);

        if ($value === false) {
            return null;
        }

        return $value;
    }


    /**
     * @param   string    $key
     * @param   array     $value
     * @param   int|null  $expire
     *
     * @throws Exception
     */
    private function set(string $key, array $value, ?int $expire = null): void
    {
        Assert::string($key);
        Assert::nullOrInteger($expire);
        Assert::greaterThan($expire, 2592000);

        if (rand(0, 1000) < 10) {
            $this->cleanKVStore();
        }

        if (strlen($key) > 50) {
            $key = sha1($key);
        }

        if ($expire !== null) {
            $expire = gmdate('Y-m-d H:i:s', $expire);
        }

        $value = serialize($value);
        $value = rawurlencode($value);

        $data = [
            '_key' => $key,
            '_value' => $value,
            '_expire' => $expire,
        ];

        $this->insertOrUpdate($this->prefix . '_kvstore', ['_key'], $data);
    }


    /**
     * @param string $key
     */
    private function delete(string $key): void
    {
        Assert::string($key);

        if (strlen($key) > 50) {
            $key = sha1($key);
        }

        $data = [
            '_key' => $key,

        ];

        $query = 'DELETE FROM ' . $this->prefix . '_kvstore WHERE _key=:_key';
        $query = $this->pdo->prepare($query);
        $query->execute($data);
    }
}
