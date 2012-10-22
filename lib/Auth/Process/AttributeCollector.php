<?php

class sspmod_attributestore_Auth_Process_AttributeCollector extends SimpleSAML_Auth_ProcessingFilter {

      private $attributeStoreUrl;
      
      public function __construct($config, $reserved) {
            parent::__construct($config, $reserved);

            $this->attributeStoreUrl = preg_replace('/\/$/','',$config['attributeStoreUrl']);
      }

      public function process(&$request) {
            $idp = $this->getEntityIdFromMetaData($request['Source']);
            $sp = $this->getEntityIdFromMetaData($request['Destination']);

            SimpleSAML_Logger::debug('AttributeCollector: idp ' . var_export($idp, TRUE) . '.');
            SimpleSAML_Logger::debug('AttributeCollector: sp ' . var_export($sp, TRUE) . '.');

            $userId = $this->getUserIdFromRequest($request);

            SimpleSAML_Logger::debug('AttributeCollector: user ' . var_export($userId, TRUE));

            $attributes = $this->getAttributesFromAttributeStore($this->scopeKey($idp,$userId,$sp,'').'*');

            SimpleSAML_Logger::debug('AttributeCollector: attribute scoped response ' . var_export($attributes, TRUE));

            foreach($attributes as $i => &$attribute) {
                  $name = $this->unscopeKey($idp,$userId,$sp,$attribute['key']);
                  $value = $attribute['value'];
                  
                  $request['Attributes'][$name] = array($value);
            }

            SimpleSAML_Logger::debug('AttributeCollector: Attributes' . var_export($request['Attributes'], TRUE));

            $lastLogin = $this->getTimeStampObject();

            SimpleSAML_Logger::debug('AttributeCollector: lastLogin ' . var_export($lastLogin, TRUE));

            $scopedLastLogin['key'] = $this->scopeKey($idp,$userId,$sp,$lastLogin['key']);
            $scopedLastLogin['value'] = $lastLogin['value'];

            SimpleSAML_Logger::debug('AttributeCollector: scopedLastLogin ' . var_export($scopedLastLogin, TRUE));

            $response = $this->createAttributeInAttributeStore($scopedLastLogin);

            SimpleSAML_Logger::debug('AttributeCollector: response ' . var_export($response, TRUE));
      }

      private function getEntityIdFromMetaData($metadata) {
            foreach($metadata as $key => $value) {
                  if(preg_match('/^entity/', $key)) {
                        return $value;
                  }
            }

            return "unknown";
      }

      private function getUserIdFromRequest($request) {
            return $request['Attributes']['eduPersonPrincipalName'][0];
      }

      private function createAttributeInAttributeStore($attribute) {
            $postParameters = array('http' => array('method' => 'POST', 'header' => array('Content-Type: application/json'),
                  'content' => json_encode($attribute),'ignore_errors' => true));

            $context = stream_context_create($postParameters);
            $response = file_get_contents($this->attributeStoreUrl, false, $context);
            
            return $response;
      }

      private function getAttributesFromAttributeStore($scope) {
            $getParameters = array('http' => array('method' => 'GET', 'header' => array('Content-Type: application/json'),
                  'ignore_errors' => true));

            $getUrl = $this->attributeStoreUrl.'/'.urlencode($scope);

            SimpleSAML_Logger::debug('AttributeCollector: get request ' . var_export($getUrl, TRUE));            

            $context = stream_context_create($getParameters);
            $response = file_get_contents($getUrl, false, $context);

            return json_decode($response, true);
      }

      private function getTimeStampObject() {
            return array('key' => 'SBLastLoginTimestamp', 'value' => time());
      }

      private function scopeKey($idp, $userId, $sp, $key) {
            return urlencode($idp.'.'.$userId.'.'.$sp.'.'.$key);
      }

      private function unscopeKey($idp, $userId, $sp, $key) {
            $prefix = $idp.'.'.$userId.'.'.$sp.'.';

            return str_replace($prefix,'',urldecode($key));
      }
}
?>
