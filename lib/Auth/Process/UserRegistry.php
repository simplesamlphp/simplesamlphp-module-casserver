<?php

class sspmod_sbcasserver_Auth_Process_UserRegistry extends SimpleSAML_Auth_ProcessingFilter {

  private $soapClient;
  private $sbBorrowerIdAttribute;
  private $sbPersonScopedAffiliationAttribute;
  private $sbPersonScopedAffiliationMapping;
  private $sbPersonPrimaryAffiliationAttribute;
  private $sbPersonPrimaryAffiliationMapping;
 
  public function __construct($config, $reserved) {
    parent::__construct($config, $reserved);

    if(!is_string($config['ws-userregistry'])) {
      throw new Exception('Missing or invalid ws-userregistry url option in config.');
    }

    $wsuserregistry = $config['ws-userregistry'];

    SimpleSAML_Logger::debug('SBUserRegistry: ws-userregistry url ' . var_export($wsuserregistry, TRUE) . '.');

    $this->soapClient = new SoapClient($wsuserregistry);

    if(!is_string($config['sbBorrowerIdAttribute'])) {
      throw new Exception('Missing or invalid sbBorrowerIdAttribute option in config.');
    }

    $this->sbBorrowerIdAttribute = $config['sbBorrowerIdAttribute'];

    if(!is_string($config['sbPersonPrimaryAffiliationAttribute'])) {
      throw new Exception('Missing or invalid sbPersonPrimaryAffiliationAttribute option in config.');
    }

    $this->sbPersonPrimaryAffiliationAttribute = $config['sbPersonPrimaryAffiliationAttribute'];

    if(is_null($config['sbPersonPrimaryAffiliationMapping'])) {
      throw new Exception('Missing or invalid sbPersonPrimaryAffiliationMapping option in config.');
    }

    $this->sbPersonPrimaryAffiliationMapping = $config['sbPersonPrimaryAffiliationMapping'];

    if(!is_string($config['sbPersonScopedAffiliationAttribute'])) {
      throw new Exception('Missing or invalid sbPersonScopedAffiliationAttribute option in config.');
    }

    $this->sbPersonScopedAffiliationAttribute = $config['sbPersonScopedAffiliationAttribute'];

    if(is_null($config['sbPersonScopedAffiliationMapping'])) {
      throw new Exception('Missing or invalid sbPersonScopedAffiliationMapping option in config.');
    }

    $this->sbPersonScopedAffiliationMapping = $config['sbPersonScopedAffiliationMapping'];
  }

  public function process(&$request) {

    if(array_key_exists($this->sbBorrowerIdAttribute, $request['Attributes'])) {
      $borrowerId = $request['Attributes'][$this->sbBorrowerIdAttribute][0];

      $userRegistryAttributesResponse = $this->soapClient->lookupIdpInfo(array('borrowerId' =>$borrowerId));

      if ($userRegistryAttributesResponse->serviceStatus == "IdPInfoRetrieved") {
	SimpleSAML_Logger::debug('SBUserRegistryAuth: look up of user ' . var_export($borrowerId, TRUE) . ' attributes succeeded');

        if(isset($userRegistryAttributesResponse->Info->organizationalRelation)) {
	  $this->addAttribute($request['Attributes'],$this->sbPersonPrimaryAffiliationAttribute,
			      $this->calculateSBAffiliation($userRegistryAttributesResponse->Info->organizationalRelation, $this->sbPersonPrimaryAffiliationMapping));
	  
	  foreach($this->translateSBScopedAffiliation($userRegistryAttributesResponse->Info->organizationalRelation, $this->sbPersonScopedAffiliationMapping) as $value) {
	    $this->addAttribute($request['Attributes'],$this->sbPersonScopedAffiliationAttribute,$value);
	  }
	} else {
	  $this->addAttribute($request['Attributes'],$this->sbPersonPrimaryAffiliationAttribute,'affiliate');
	}

      } else {
	SimpleSAML_Logger::error('SBUserRegistryAuth: look up of user ' . var_export($username, TRUE) . ' attributes failed with status '.var_export($userRegistryAttributesResponse->serviceStatus).'.');
      }
	
    } else if($userRegistryResponse->serviceStatus == 'SystemError') {
      SimpleSAML_Logger::error('SBUserRegistry: look up of user ' . var_export($username, TRUE) . ' failed with status '.var_export($userRegistryResponse->serviceStatus).'.');
    }
  }

  private function calculateSBAffiliation($borrowerType, $affiliationMapping) {
    $affiliation = 'affiliate@statsbibliotektet.dk';

    foreach($affiliationMapping as $affiliationValue => $affiliationPattern) {
      SimpleSAML_Logger::debug('matching pattern "'.$affiliationPattern.'" against "'.$borrowerType.'"');

      if(preg_match($affiliationPattern,$borrowerType)) {
	$affiliation = $affiliationValue.'@statsbiblioteket.dk';
      }
    }

    SimpleSAML_Logger::debug('resulting affiliation "'.$affiliation.'"');
 
    return $affiliation;
  }

  private function translateSBScopedAffiliation($borrowerType, $affiliationTranslation) {
    $scopedAffiliation = array();

    foreach($affiliationTranslation as $affiliationValue => $affiliationPattern) {
      SimpleSAML_Logger::debug('matching pattern "'.$affiliationPattern.'" against "'.$borrowerType.'"');

      if(preg_match($affiliationPattern,$borrowerType)) {
	array_push($scopedAffiliation , preg_replace(array($affiliationPattern), array($affiliationValue), $borrowerType));
      }
    }

    foreach($scopedAffiliation as $sa) {
      SimpleSAML_Logger::debug('resulting scopedAffiliation "'.$sa.'"');
    }

    return $scopedAffiliation;
  }

  private function addAttribute(&$attributes, $attributeName, $attributeValue) {
    if(array_key_exists($attributeName, $attributes)) {
      if(!in_array($attributeValue, $attributes[$attributeName])) {
        array_push($attributes[$attributeName], $attributeValue);
      }
    } else {
      $attributes[$attributeName] = array($attributeValue);
    }
  }
  }
?>
