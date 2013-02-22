<?php
class sspmod_sbcasserver_Cas_Ticket_TicketFactory
{
    private $serviceTicketExpireTime;
    private $proxyTicketExpireTime;

    public function __construct($config)
    {
        $this->serviceTicketExpireTime = $config->getValue('serviceTicketExpireTime', 5);
        $this->proxyTicketExpireTime = $config->getValue('proxyTicketExpireTime', 5);
    }

    public function createServiceTicket($content)
    {
        $id = str_replace('_', 'ST-', SimpleSAML_Utilities::generateID());
        $expiresAt = time() + $this->serviceTicketExpireTime;

        return array_merge(array('id' => $id, 'validBefore' => $expiresAt), $content);
    }

    public function createProxyGrantingTicket($content)
    {
        $id = str_replace('_', 'PGT-', SimpleSAML_Utilities::generateID());
        $iou = str_replace('_', 'PGTIOU-', SimpleSAML_Utilities::generateID());

        return array_merge(array('id' => $id, 'iou' => $iou), $content);
    }

    public function createProxyTicket($content)
    {
        $id = str_replace('_', 'PT-', SimpleSAML_Utilities::generateID());
        $expiresAt = time() + $this->proxyTicketExpireTime;

        return array_merge(array('id' => $id, 'validBefore' => $expiresAt), $content);
    }

    public function validateServiceTicket($ticket)
    {
        return preg_match('/^ST-?[a-zA-Z0-9]+$/D', $ticket) && array_key_exists('validBefore', $ticket) && $ticket['validBefore'] > time();
    }

    public function validateProxyGrantingTicket($ticket)
    {
        return preg_match('/^PGT-?[a-zA-Z0-9]+$/D', $ticket);
    }

    public function validateProxyTicket($ticket)
    {
        return preg_match('/^PT-?[a-zA-Z0-9]+$/D', $ticket) && array_key_exists('validBefore', $ticket) && $ticket['validBefore'] > time();
    }
}

?>