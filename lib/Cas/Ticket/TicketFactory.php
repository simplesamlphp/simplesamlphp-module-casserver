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

    public function createSessionTicket($sessionId, $expireTime)
    {
        $expiresAt = time() + $expireTime;

        return array('id' => $sessionId, 'validBefore' => $expiresAt, 'renewId' => SimpleSAML_Utilities::generateID());
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
        $result = array();

        if (preg_match('/^ST-?[a-zA-Z0-9]+$/D', $ticket['id'])) {
            if (array_key_exists('validBefore', $ticket) && $ticket['validBefore'] > time()) {
                $result['valid'] = true;
            } else {
                $result['valid'] = false;
                $result['reason'] = 'ticket expired';
            }
        } else {
            $result['valid'] = false;
            $result['reason'] = 'not a valid service ticket id: ' . $ticket['id'];
        }

        return $result;
    }

    public function validateSessionTicket($ticket)
    {
        return preg_match('/^[a-zA-Z0-9]+$/D', $ticket['id']) && array_key_exists('validBefore', $ticket) && $ticket['validBefore'] > time();
    }

    public function validateProxyGrantingTicket($ticket)
    {
        return preg_match('/^PGT-?[a-zA-Z0-9]+$/D', $ticket['id']);
    }

    public function validateProxyTicket($ticket)
    {
        return preg_match('/^(PT|ST)-?[a-zA-Z0-9]+$/D', $ticket['id']) && array_key_exists('validBefore', $ticket) && $ticket['validBefore'] > time();
    }
}

?>