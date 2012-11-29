<?php

class sspmod_sbcasserver_Cas_TicketStore_FileSystemTicketStore {

  private $pathToTicketDirectory;

  public function __construct($config) {
    $storeConfig = $config->getValue('ticketstore');

    if(!is_string($storeConfig['directory'])) {
      throw new Exception('Missing or invalid directory option in config.');
    }

    $path = $config->resolvePath($storeConfig['directory']);

    if (!is_dir($path)) 
      throw new Exception('Directory for CAS Server ticket storage [' . $path . '] does not exists. ');
		
    if (!is_writable($path)) 
      throw new Exception('Directory for CAS Server ticket storage [' . $path . '] is not writable. ');

    $this->pathToTicketDirectory = preg_replace('/\/$/','',$path);
  }

  public function createTicket($value) {
    $ticket = str_replace( '_', 'ST-', SimpleSAML_Utilities::generateID() );

    $filename = $this->pathToTicketDirectory . '/' . $ticket;
    file_put_contents($filename, serialize($value));

    return $ticket;
  }

  public function getTicket($ticket) {

    if (!preg_match('/^(ST|PT|PGT)-?[a-zA-Z0-9]+$/D', $ticket)) throw new Exception('Invalid characters in ticket');

    $filename = $this->pathToTicketDirectory . '/' . $ticket;

    if (!file_exists($filename))
      throw new Exception('Could not find ticket');
	
    $content = file_get_contents($filename);
	
    return unserialize($content);
  }

  public function deleteTicket($ticket) {
    if (!preg_match('/^(ST|PT|PGT)-?[a-zA-Z0-9]+$/D', $ticket)) throw new Exception('Invalid characters in ticket');

    $filename = $this->pathToTicketDirectory . '/' . $ticket;

    if (file_exists($filename)) {
      unlink($filename);
    }
  }
  }
?>