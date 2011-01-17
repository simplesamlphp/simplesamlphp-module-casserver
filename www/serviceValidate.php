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
  SimpleSAML_Logger::debug("PDJ: Starting TRY block");
	/* Load simpleSAMLphp, configuration and metadata */
	$casconfig = SimpleSAML_Configuration::getConfig('module_sbcasserver.php');
  SimpleSAML_Logger::debug("PDJ: Loaded module_sbcasserver.php");
	
	
	$path = $casconfig->resolvePath($casconfig->getValue('ticketcache', 'ticketcache'));
	
	$ticketcontent = retrieveTicket($ticket, $path);
  SimpleSAML_Logger::debug("PDJ: loaded the ticket");
  SimpleSAML_Logger::debug(print_r($ticketcontent, true));
	
	$usernamefield = $casconfig->getValue('attrname', 'eduPersonPrincipalName');
	$dosendattributes = $casconfig->getValue('attributes', FALSE);;
	
	if (array_key_exists($usernamefield, $ticketcontent)) {
    SimpleSAML_Logger::debug("PDJ: found usernamefield in ticket");
		returnResponse('YES', $ticketcontent[$usernamefield][0], $dosendattributes ? $ticketcontent : array());
	} else {
    SimpleSAML_Logger::debug("PDJ: didn't find any usernamefield in ticket");
		returnResponse('NO');
	}

} catch (Exception $e) {
  SimpleSAML_Logger::debug("PDJ: Ran into an exception: ".$e->getMessage());

	returnResponse('NO', $e->getMessage());
}

function returnResponse($value, $content = '', $attributes = array()) {
	if ($value === 'YES') {
		$attributesxml = "";
		foreach ($attributes as $attributename => $attributelist) {
			$attr = htmlentities($attributename);
			foreach ($attributelist as $attributevalue) {
				$attributesxml .= "<cas:$attr>" . htmlentities($attributevalue) . "</cas:$attr>\n";
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


function storeTicket($ticket, $path, &$value ) {

	if (!is_dir($path)) 
		throw new Exception('Directory for CAS Server ticket storage [' . $path . '] does not exists. ');
		
	if (!is_writable($path)) 
		throw new Exception('Directory for CAS Server ticket storage [' . $path . '] is not writable. ');

	$filename = $path . '/' . $ticket;
	file_put_contents($filename, serialize($value));
}

function retrieveTicket($ticket, $path) {

	// Zakomentirao Dubravko Voncina (to ga je mucilo)
	// if (!preg_match('/^_?[a-zA-Z0-9]+$/D', $ticket)) throw new Exception('Invalid characters in ticket');
	if (!preg_match('/^ST-?[a-zA-Z0-9]+$/D', $ticket)) throw new Exception('Invalid characters in ticket');

	if (!is_dir($path)) 
		throw new Exception('Directory for CAS Server ticket storage [' . $path . '] does not exists. ');

	$filename = $path . '/' . $ticket;

	if (!file_exists($filename))
		throw new Exception('Could not find ticket');
	
	$content = file_get_contents($filename);
	
	unlink($filename);
	
	return unserialize($content);
}




?>
