<?php

class sspmod_sbcasserver_Cas_TicketStore_FileSystemTicketStore {

  public function __construct($storeConfig) {
  }

  public function storeTicket($ticket, $path, $value ) {

    if (!is_dir($path)) 
      throw new Exception('Directory for CAS Server ticket storage [' . $path . '] does not exists. ');
		
    if (!is_writable($path)) 
      throw new Exception('Directory for CAS Server ticket storage [' . $path . '] is not writable. ');

    $filename = $path . '/' . $ticket;
    file_put_contents($filename, serialize($value));
  }

  public function retrieveTicket($ticket, $path, $unlink = true) {

    if (!preg_match('/^(ST|PT|PGT)-?[a-zA-Z0-9]+$/D', $ticket)) throw new Exception('Invalid characters in ticket');

    if (!is_dir($path)) 
      throw new Exception('Directory for CAS Server ticket storage [' . $path . '] does not exists. ');

    $filename = $path . '/' . $ticket;

    if (!file_exists($filename))
      throw new Exception('Could not find ticket');
	
    $content = file_get_contents($filename);
	
    if ($unlink) {
      unlink($filename);
    }
	
    return unserialize($content);
  }
  }
?>