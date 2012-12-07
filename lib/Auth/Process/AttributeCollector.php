<?php

class sspmod_sbcasserver_Auth_Process_AttributeCollector extends SimpleSAML_Auth_ProcessingFilter
{

    private $attributeStoreUrl;
    private $attributeStorePrefix;

    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);

        $this->attributeStoreUrl = preg_replace('/\/$/', '', $config['attributeStoreUrl']);
        $this->attributeStorePrefix = $config['attributeStorePrefix'];
    }

    public function process(&$request)
    {
        $userId = $this->getUserIdFromRequest($request);

        SimpleSAML_Logger::debug('AttributeCollector: user ' . var_export($userId, TRUE));

        if (is_string($userId)) {
            $attributes = $this->getAttributesFromAttributeStore($this->scopeKey($userId, '') . '*');

            SimpleSAML_Logger::debug('AttributeCollector: attribute scoped response ' . var_export($attributes, TRUE));

            if (!is_null($attributes)) {
                foreach ($attributes as $i => &$attribute) {
                    $name = $this->unscopeKey($userId, $attribute['key']);
                    $value = $attribute['value'];

                    $request['Attributes'][$name] = array($value);
                }
            }

            SimpleSAML_Logger::debug('AttributeCollector: Attributes' . var_export($request['Attributes'], TRUE));

            $lastLogin = $this->getTimeStampObject();

            SimpleSAML_Logger::debug('AttributeCollector: lastLogin ' . var_export($lastLogin, TRUE));

            $scopedLastLogin['key'] = $this->scopeKey($userId, $lastLogin['key']);
            $scopedLastLogin['value'] = $lastLogin['value'];

            SimpleSAML_Logger::debug('AttributeCollector: scopedLastLogin ' . var_export($scopedLastLogin, TRUE));

            $response = $this->createAttributeInAttributeStore($scopedLastLogin);

            SimpleSAML_Logger::debug('AttributeCollector: response ' . var_export($response, TRUE));
        }
    }

    private function getUserIdFromRequest($request)
    {
        if (!is_null($request['Attributes']) && !is_null($request['Attributes']['eduPersonPrincipalName'])) {
            return $request['Attributes']['eduPersonPrincipalName'][0];
        } else {
            return null;
        }
    }

    private function createAttributeInAttributeStore($attribute)
    {
        $postParameters = array('http' => array('method' => 'POST', 'header' => array('Content-Type: application/json'),
            'content' => json_encode($attribute), 'ignore_errors' => true));

        $context = stream_context_create($postParameters);
        $response = file_get_contents($this->attributeStoreUrl, false, $context);

        return $response;
    }

    private function getAttributesFromAttributeStore($scope)
    {
        $getParameters = array('http' => array('method' => 'GET', 'header' => array('Content-Type: application/json'),
            'ignore_errors' => true));

        $getUrl = $this->attributeStoreUrl . '/' . urlencode($scope);

        SimpleSAML_Logger::debug('AttributeCollector: get request ' . var_export($getUrl, TRUE));

        $context = stream_context_create($getParameters);
        $response = file_get_contents($getUrl, false, $context);

        return json_decode($response, true);
    }

    private function getTimeStampObject()
    {
        return array('key' => 'SBLastLoginTimestamp', 'value' => time());
    }

    private function scopeKey($userId, $key)
    {
        return urlencode($this->attributeStorePrefix . '.' . $userId . '.' . $key);
    }

    private function unscopeKey($userId, $key)
    {
        return str_replace($this->attributeStorePrefix . '.' . $userId . '.', '', urldecode($key));
    }
}

?>
