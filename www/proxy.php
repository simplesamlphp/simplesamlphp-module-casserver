<?php

/*
 * Incomming parameters:
 *  targetService
 *  ptg
 *  
 */

if (array_key_exists('targetService', $_GET)) {
    $targetService = $_GET['targetService'];
    $pgt = $_GET['pgt'];
} else {
    throw new Exception('Required URL query parameter [targetService] not provided. (CAS Server)');
}

$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');

$legal_service_urls = $casconfig->getValue('legal_service_urls');

if (!checkServiceURL($targetService, $legal_service_urls))
    throw new Exception('Service parameter provided to CAS server is not listed as a legal service: [service] = ' . $targetService);

$ticketStoreConfig = $casconfig->getValue('ticketstore', array('class' => 'sbcasserver:FileSystemTicketStore', 'directory' => 'ticketcache'));
$ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'], 'Cas_TicketStore');
$ticketStore = new $ticketStoreClass($casconfig);

$proxyGrantingTicketContent = $ticketStore->getTicket($pgt);

if (is_array($proxyGrantingTicketContent) && $proxyGrantingTicketContent['validbefore'] > time()) {

    $proxyTicketContent = array(
        'service' => $targetService,
        'forceAuthn' => false,
        'attributes' => $proxyGrantingTicketContent['attributes'],
        'proxies' => $proxyGrantingTicketContent['proxies'],
        'validbefore' => time() + 5);

    $pt = $ticketStore->createProxyTicket($proxyTicketContent);

    print <<<eox
<cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
    <cas:proxySuccess>
        <cas:proxyTicket>$pt</cas:proxyTicket>
    </cas:proxySuccess>
</cas:serviceResponse>
eox;
} else {
    print <<<eox
<cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
    <cas:proxyFailure code="INVALID_REQUEST">
        Proxygranting ticket to old - ssp casserver only supports shortlived (30 secs) pgts.
    </cas:proxyFailure>
</cas:serviceResponse>
eox;
}

?>