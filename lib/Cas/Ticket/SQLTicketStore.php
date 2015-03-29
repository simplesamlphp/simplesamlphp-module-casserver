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

class sspmod_casserver_Cas_Ticket_SQLTicketStore extends sspmod_casserver_Cas_Ticket_TicketStore
{
    public $pdo;
    public $driver;
    public $prefix;
    private $tableVersions;

    public function __construct($config)
    {
        parent::__construct($config);

        $storeConfig = $config->getValue('ticketstore');

        $dsn = $storeConfig['dsn'];
        $username = $storeConfig['username'];
        $password = $storeConfig['password'];
        $this->prefix = $storeConfig['prefix'];

        $this->pdo = new PDO($dsn, $username, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($this->driver === 'mysql') {
            $this->pdo->exec('SET time_zone = "+00:00"');
        }

        $this->initTableVersionTable();
        $this->initKVTable();
    }

    public function getTicket($ticketId)
    {
        $scopedTicketId = $this->scopeTicketId($ticketId);

        return $this->get($scopedTicketId);
    }

    public function addTicket($ticket)
    {
        $scopedTicketId = $this->scopeTicketId($ticket['id']);

        $this->set($scopedTicketId, $ticket, $ticket['validBefore']);
    }

    public function deleteTicket($ticketId)
    {
        $scopedTicketId = $this->scopeTicketId($ticketId);

        $this->delete($scopedTicketId);
    }

    private function scopeTicketId($ticketId)
    {
        return $this->prefix . '.' . $ticketId;
    }

    private function initTableVersionTable()
    {

        $this->tableVersions = array();

        try {
            $fetchTableVersion = $this->pdo->query('SELECT _name, _version FROM ' . $this->prefix . '_tableVersion');
        } catch (PDOException $e) {
            $this->pdo->exec('CREATE TABLE ' . $this->prefix . '_tableVersion (_name VARCHAR(30) NOT NULL UNIQUE, _version INTEGER NOT NULL)');
            return;
        }

        while (($row = $fetchTableVersion->fetch(PDO::FETCH_ASSOC)) !== FALSE) {
            $this->tableVersions[$row['_name']] = (int)$row['_version'];
        }
    }

    private function initKVTable()
    {
        if ($this->getTableVersion('kvstore') === 1) {
            /* Table initialized. */
            return;
        }

        $query = 'CREATE TABLE ' . $this->prefix . '_kvstore (_key VARCHAR(50) NOT NULL, _value TEXT NOT NULL, _expire TIMESTAMP, PRIMARY KEY (_key))';
        $this->pdo->exec($query);

        $query = 'CREATE INDEX ' . $this->prefix . '_kvstore_expire ON ' . $this->prefix . '_kvstore (_expire)';
        $this->pdo->exec($query);

        $this->setTableVersion('kvstore', 1);
    }

    private function getTableVersion($name)
    {
        assert('is_string($name)');

        if (!isset($this->tableVersions[$name])) {
            return 0;
        }

        return $this->tableVersions[$name];
    }

    private function setTableVersion($name, $version)
    {
        assert('is_string($name)');
        assert('is_int($version)');

        $this->insertOrUpdate($this->prefix . '_tableVersion', array('_name'),
            array('_name' => $name, '_version' => $version));
        $this->tableVersions[$name] = $version;
    }

    private function insertOrUpdate($table, array $keys, array $data)
    {
        assert('is_string($table)');

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
        }

        /* Default implementation. Try INSERT, and UPDATE if that fails. */

        $insertQuery = 'INSERT INTO ' . $table . ' ' . $colNames . ' ' . $values;
        $insertQuery = $this->pdo->prepare($insertQuery);
        try {
            $insertQuery->execute($data);
            return;
        } catch (PDOException $e) {
            $ecode = (string)$e->getCode();
            switch ($ecode) {
                case '23505': /* PostgreSQL */
                    break;
                default:
                    SimpleSAML_Logger::error('casserver: Error while saving data: ' . $e->getMessage());
                    throw $e;
            }
        }

        $updateCols = array();
        $condCols = array();

        foreach ($data as $col => $value) {

            $tmp = $col . ' = :' . $col;

            if (in_array($col, $keys, TRUE)) {
                $condCols[] = $tmp;
            } else {
                $updateCols[] = $tmp;
            }
        }

        $updateQuery = 'UPDATE ' . $table . ' SET ' . implode(',', $updateCols) . ' WHERE ' . implode(' AND ', $condCols);
        $updateQuery = $this->pdo->prepare($updateQuery);
        $updateQuery->execute($data);
    }

    private function cleanKVStore()
    {
        $query = 'DELETE FROM ' . $this->prefix . '_kvstore WHERE _expire < :now';
        $params = array('now' => gmdate('Y-m-d H:i:s'));

        $query = $this->pdo->prepare($query);
        $query->execute($params);
    }

    private function get($key)
    {
        assert('is_string($key)');

        if (strlen($key) > 50) {
            $key = sha1($key);
        }

        $query = 'SELECT _value FROM ' . $this->prefix . '_kvstore WHERE _key = :key AND (_expire IS NULL OR _expire > :now)';
        $params = array('key' => $key, 'now' => gmdate('Y-m-d H:i:s'));

        $query = $this->pdo->prepare($query);
        $query->execute($params);

        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row === FALSE) {
            return NULL;
        }

        $value = $row['_value'];
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        $value = urldecode($value);
        $value = unserialize($value);

        if ($value === FALSE) {
            return NULL;
        }

        return $value;
    }

    private function set($key, $value, $expire = NULL)
    {
        assert('is_string($key)');
        assert('is_null($expire) || (is_int($expire) && $expire > 2592000)');

        if (rand(0, 1000) < 10) {
            $this->cleanKVStore();
        }

        if (strlen($key) > 50) {
            $key = sha1($key);
        }

        if ($expire !== NULL) {
            $expire = gmdate('Y-m-d H:i:s', $expire);
        }

        $value = serialize($value);
        $value = rawurlencode($value);

        $data = array(
            '_key' => $key,
            '_value' => $value,
            '_expire' => $expire,
        );

        $this->insertOrUpdate($this->prefix . '_kvstore', array('_key'), $data);
    }

    private function delete($key)
    {
        assert('is_string($key)');

        if (strlen($key) > 50) {
            $key = sha1($key);
        }

        $data = array(
            '_key' => $key,
        );

        $query = 'DELETE FROM ' . $this->prefix . '_kvstore WHERE _key=:_key';
        $query = $this->pdo->prepare($query);
        $query->execute($data);
    }
}
