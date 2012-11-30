<?php
/*
 * Incomming parameters:
 *  service
 *  renew
 *  ticket
 *
 */

if (!array_key_exists('service', $_GET))
  throw new Exception('Required URL query parameter [service] not provided. (CAS Server)');

$service = $_GET['service'];

if (!array_key_exists('ticket', $_GET))
  throw new Exception('Required URL query parameter [ticket] not provided. (CAS Server)');

$ticket = $_GET['ticket'];

$renew = FALSE;

if (array_key_exists('renew', $_GET)) {
  $renew = TRUE;
}

try {
  /* Load simpleSAMLphp, configuration and metadata */
  $casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');
	
  /* Instantiate ticket store */
  $ticketStoreConfig = $casconfig->getValue('ticketstore');
  $ticketStoreClass = SimpleSAML_Module::resolveClass($ticketStoreConfig['class'],'Cas_TicketStore');
  $ticketStore = new $ticketStoreClass($casconfig);
	
  $base64encodeQ = $casconfig->getValue('base64attributes',false);
	
  $ticketcontent = $ticketStore->getTicket($ticket);
  $ticketStore->removeTicket($ticket);
	
  $usernamefield = $casconfig->getValue('attrname', 'eduPersonPrincipalName');
  $dosendattributes = $casconfig->getValue('attributes', FALSE);;
	
  if (array_key_exists($usernamefield, $ticketcontent)) {
    returnResponse('YES', $ticketcontent[$usernamefield][0], $dosendattributes ? $ticketcontent : array(), $base64encodeQ);
  } else {
    returnResponse('NO');
  }

} catch (Exception $e) {
  returnResponse('NO', $e->getMessage());
  }

function returnResponse($value, $content = '', $attributes = array(), $base64encodeQ = false) {
  if ($value === 'YES') {
    $attributesxml = "";
    foreach ($attributes as $attributename => $attributelist) {
      $attr = htmlentities($attributename);
      foreach ($attributelist as $attributevalue) {
	if (!preg_match('/urn:oid/',$attr)) {
	  $attributesxml .= "<cas:".$attr.">" . ($base64encodeQ ? base64_encode(htmlentities($attributevalue )):htmlentities($attributevalue)) . "</cas:$attr>\n";
	}
      }
    }
    if (sizeof($attributes)) $attributesxml = '<cas:attributes>' . $attributesxml . '</cas:attributes>';
    echo '<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
		<cas:authenticationSuccess>
	<cas:user>' . htmlentities($content) . '</cas:user>' .
      $attributesxml .
      '</cas:authenticationSuccess>
</cas:serviceResponse>';

  } else {
    echo '<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
    <cas:authenticationFailure code="...">
	' . $content . '
    </cas:authenticationFailure>
</cas:serviceResponse>';
  }
}

?>

