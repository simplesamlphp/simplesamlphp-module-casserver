<?php

/**
 * Extract the user and any mapped attributes from the AuthSource attributes
 */
class sspmod_casserver_Cas_AttributeExtractor
{

    /**
     * Determine the user and any CAS attributes based on the attributes from the
     * authsource and the CAS configuration.
     *
     * The result is an array
     * [
     *   'user' => 'user_value',
     *   'attributes' => [
     *    // any attributes
     * ]
     *
     * If no CAS attributes are configured then the attributes array is empty
     * @param array $attributes
     * @param SimpleSAML_Configuration $casconfig
     * @return array
     */
    public function extractUserAndAttributes(array $attributes, SimpleSAML_Configuration $casconfig)
    {
        $casUsernameAttribute = $casconfig->getValue('attrname', 'eduPersonPrincipalName');

        //TODO: how should a missing userName be handled?
        $userName = $attributes[$casUsernameAttribute][0];

        if ($casconfig->getValue('attributes', true)) {
            $attributesToTransfer = $casconfig->getValue('attributes_to_transfer', array());

            if (sizeof($attributesToTransfer) > 0) {
                $casAttributes = array();

                foreach ($attributesToTransfer as $key) {
                    if (array_key_exists($key, $attributes)) {
                        $casAttributes[$key] = $attributes[$key];
                    }
                }
            } else {
                $casAttributes = $attributes;
            }
        } else {
            $casAttributes = array();
        }

        return array(
            'user' => $userName,
            'attributes' => $casAttributes
        );
    }
}
