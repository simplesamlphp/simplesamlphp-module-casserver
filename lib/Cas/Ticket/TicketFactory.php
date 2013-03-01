<?php
class sspmod_sbcasserver_Cas_Ticket_TicketFactory
{
    private $serviceTicketExpireTime;
    private $proxyGrantingTicketExpireTime;
    private $proxyTicketExpireTime;

    public function __construct($config)
    {
        $this->serviceTicketExpireTime = $config->getValue('serviceTicketExpireTime', 5);
        $this->proxyGrantingTicketExpireTime = $config->getValue('proxyGrantingTicketExpireTime', 3600);
        $this->proxyTicketExpireTime = $config->getValue('proxyTicketExpireTime', 5);
    }

    public function createSessionTicket($sessionId, $expiresAt)
    {
        return array('id' => $sessionId, 'validBefore' => $expiresAt, 'renewId' => SimpleSAML_Utilities::generateID());
    }

    public function createServiceTicket($content)
    {
        $id = str_replace('_', 'ST-', SimpleSAML_Utilities::generateID());
        $expiresAt = time() + $this->serviceTicketExpireTime;

        return array_merge(array('id' => $id, 'validBefore' => $expiresAt), $content);
    }

    public function createProxyGrantingTicket($content, $expiresAt)
    {
        $id = str_replace('_', 'PGT-', SimpleSAML_Utilities::generateID());
        $iou = str_replace('_', 'PGTIOU-', SimpleSAML_Utilities::generateID());

        $upperBound = time() + $this->proxyGrantingTicketExpireTime;

        return array_merge(array('id' => $id, 'iou' => $iou,
            'validBefore' => $expiresAt < $upperBound ? $expiresAt : $upperBound), $content);
    }

    public function createProxyTicket($content)
    {
        $id = str_replace('_', 'PT-', SimpleSAML_Utilities::generateID());
        $expiresAt = time() + $this->proxyTicketExpireTime;

        return array_merge(array('id' => $id, 'validBefore' => $expiresAt), $content);
    }

    public function isSessionTicket($ticket)
    {
        return preg_match('/^[a-zA-Z0-9]+$/D', $ticket['id']);
    }

    public function isServiceTicket($ticket)
    {
        return preg_match('/^ST-?[a-zA-Z0-9]+$/D', $ticket['id']);
    }

    public function isProxyGrantingTicket($ticket)
    {
        return preg_match('/^PGT-?[a-zA-Z0-9]+$/D', $ticket['id']);
    }

    public function isProxyTicket($ticket)
    {
        return preg_match('/^PT-?[a-zA-Z0-9]+$/D', $ticket['id']);
    }

    public function isExpired($ticket)
    {
        return !array_key_exists('validBefore', $ticket) || $ticket['validBefore'] < time();
    }
}

?>