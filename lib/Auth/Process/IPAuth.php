<?php
  /**
   */
class sspmod_sbcasserver_Auth_Process_IPAuth extends SimpleSAML_Auth_ProcessingFilter {

  /**
   * The URL of the IPAuth REST service
   */
  private $restUrl = '';
  private $appendString = "";
  private $base64Encode = true;
  private $targetAttributeName = 'eduPersonScopedAffiliation';

  /**
   * Initialize this filter.
   *
   * @param array $config  Configuration information about this filter.
   * @param mixed $reserved  For future use.
   */
  public function __construct($config, $reserved) {
    assert('is_array($config)');

    parent::__construct($config, $reserved);

    // Parse filter configuration               
    foreach($config as $name => $value) {
      // Unknown flag
      if(!is_string($name)) {
        throw new Exception('Unknown flag : ' . var_export($name, TRUE));
      }
                        
      // Set URL
      if($name == 'url') {
        $this->restUrl = $value;
      }

      // String to append
      if($name == 'append.string') {
        $this->appendString = $value;
      }

      // whether to base 64 encode the attribute values
      if($name == 'base64.encode') {
        $this->base64Encode = $value;
      }

      if($name == 'targetAttributeName') {
        $this->targetAttributeName = $value;
      }
    }
  }

  function getIPRole($serviceURL, $ip) {

    error_reporting(E_ALL);

    $request = $serviceURL . urlencode($ip);

    // Make the request
    $result = file_get_contents($request);

    // Retrieve HTTP status code
    list($version, $status_code, $msg) = explode(' ', $http_response_header[0], 3);

    // Check the HTTP Status code
    switch ($status_code) {
    case 200:
      // Success
      break;
    case 503:
      $result = "unknown";
      //die('Your call to Yahoo Web Services failed and returned an HTTP status of 503. That means: Service unavailable. An internal problem prevented us from returning data to you.');
      break;
    case 403:
      $result = "unknown";
      //die('Your call to Yahoo Web Services failed and returned an HTTP status of 403. That means: Forbidden. You do not have permission to access this resource, or are over your rate limit.');
      break;
    case 400:
      $result = "unknown";
      // You may want to fall through here and read the specific XML error
      //die('Bad request. The parameters passed to the service did not match as expected.\n');
      break;
    default:
      $result = "unknown";
      //die('Your call to Yahoo Web Services returned an unexpected HTTP status of:' . $status_code);
    }

    // Output the XML
    //echo htmlspecialchars($result, ENT_QUOTES);
    return $result;
  }


  /**
   * Apply filter to modify attributes.
   *
   * Modify existing attributes with the configured values.
   *
   * @param array &$request  The current request
   */
  public function process(&$request) {
    assert('is_array($request)');
    assert('array_key_exists("Attributes", $request)');

    // Get attributes from request
    $attributes =& $request['Attributes'];

    // Check that all required params are set in config
    if(empty($this->restUrl)) {
      throw new Exception("Not all params set in config.");
    }

    // Get all the roles, append a given string to them and maybe base 64 encode the values.
    $ipRoles = array();
    foreach (explode(',',
                     $this->getIPRole(
                                      $this->restUrl,
                                      $_SERVER['REMOTE_ADDR'])) as $key => $value) {
      if (!empty($value))
        $ipRoles[] = $this->base64Encode ? base64_encode($value . $this->appendString) : $value . $this->appendString;
    }

    SimpleSAML_Logger::debug("For IP ".$_SERVER['REMOTE_ADDR']." the roles are ".print_r($ipRoles,true));

    if(!empty($ipRoles)) {
      SimpleSAML_Logger::debug('We got some IP roles to append to the attributes set');
      if (array_key_exists($this->targetAttributeName,$attributes)) {
        SimpleSAML_Logger::debug(var_export($this->targetAttributeName, true).' already exists with the value '
                                 .print_r($attributes[$this->targetAttributeName],true)
                                 ."Adding the IP role(s): "
                                 .print_r($ipRoles,true));

        $attributes[$this->targetAttributeName] = array_merge($attributes[$this->targetAttributeName], $ipRoles);
      } else {
        SimpleSAML_Logger::debug(var_export($this->targetAttributeName, true).' do not exist yet. Adding it with the value(s):'.print_r($ipRoles,true));

        $attributes[$this->targetAttributeName] = $ipRoles;
      }
    }
  }
  }

