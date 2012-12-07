<?php

class sspmod_sbcasserver_Auth_Process_BorrowerId extends SimpleSAML_Auth_ProcessingFilter
{

    private $soapClient;
    private $sbBorrowerIdAttribute;

    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);

        if (!is_string($config['ws-userregistry'])) {
            throw new Exception('Missing or invalid ws-userregistry url option in config.');
        }

        $wsuserregistry = $config['ws-userregistry'];

        SimpleSAML_Logger::debug('BorrowerId: ws-userregistry url ' . var_export($wsuserregistry, TRUE) . '.');

        $this->soapClient = new SoapClient($wsuserregistry);

        if (!is_string($config['sbBorrowerIdAttribute'])) {
            throw new Exception('Missing or invalid sbBorrowerIdAttribute option in config.');
        }

        $this->sbBorrowerIdAttribute = $config['sbBorrowerIdAttribute'];
    }

    public function process(&$request)
    {
        $username = $this->getUserIdFromRequest($request);

        SimpleSAML_Logger::debug('BorrowerId: looking up user ' . var_export($username, TRUE) . '.');

        $userRegistryResponse = $this->soapClient->findUserAccount(array('accountId' => $username));

        if ($userRegistryResponse->serviceStatus == 'AccountRetrieved') {
            $borrowerId = $userRegistryResponse->userAccount->borrowerId;

            SimpleSAML_Logger::debug('BorrowerId: user has borrower id ' . var_export($borrowerId, TRUE) . '.');

            $this->addAttribute($request['Attributes'], $this->sbBorrowerIdAttribute, $borrowerId);
        } else if ($userRegistryResponse->serviceStatus == 'SystemError') {
            SimpleSAML_Logger::error('BorrowerId: look up of user ' . var_export($username, TRUE) . ' failed with status ' . var_export($userRegistryResponse->serviceStatus) . '.');
        }
    }

    private function addAttribute(&$attributes, $attributeName, $attributeValue)
    {
        if (array_key_exists($attributeName, $attributes)) {
            if (!in_array($attributeValue, $attributes[$attributeName])) {
                array_push($attributes[$attributeName], $attributeValue);
            }
        } else {
            $attributes[$attributeName] = array($attributeValue);
        }
    }

    private function getUserIdFromRequest($request)
    {
        $id = $request['Attributes']['schacPersonalUniqueID'];

        if (!is_null($id)) {
            $id = str_replace('urn:mace:terena.org:schac:personalUniqueID:dk:CPR:', '', $id[0], $count);

            if ($count > 0) {
                return $id;
            }
        }

        return null;
    }
}

?>
