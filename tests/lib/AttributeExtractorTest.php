<?php

namespace Simplesamlphp\Casserver;

class AttributeExtractorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Confirm behavior of a default configuration
     */
    public function testNoCasConfig()
    {
        $casConfig = array(// Default is to use eppn and copy all attributes
        );

        $attributes = array(
            'eduPersonPrincipalName' => array('testuser@example.com'),
            'additionalAttribute' => array('Taco Club')
        );
        $attributeExtractor = new \sspmod_casserver_Cas_AttributeExtractor();
        $result = $attributeExtractor->extractUserAndAttributes(
            $attributes,
            \SimpleSAML_Configuration::loadFromArray($casConfig)
        );

        $this->assertEquals('testuser@example.com', $result['user']);
        $this->assertEquals($attributes, $result['attributes']);
    }

    /**
     * Test disable attribute copying
     */
    public function testNoAttributeCopying()
    {
        $casConfig = array(
            'attributes' => false
        );

        $attributes = array(
            'eduPersonPrincipalName' => array('testuser@example.com'),
            'additionalAttribute' => array('Taco Club')
        );
        $attributeExtractor = new \sspmod_casserver_Cas_AttributeExtractor();
        $result = $attributeExtractor->extractUserAndAttributes(
            $attributes,
            \SimpleSAML_Configuration::loadFromArray($casConfig)
        );

        $this->assertEquals('testuser@example.com', $result['user']);
        $this->assertEquals([], $result['attributes']);
    }

    /**
     * Confirm customizing the attribute for user and attributes to copy
     */
    public function testCustomAttributeCopy()
    {
        $casConfig = array(
            'attrname' => 'userNameAttribute',
            'attributes_to_transfer' => array(
                'exampleAttribute',
                'additionalAttribute'
            )
        );

        $attributes = array(
            'userNameAttribute' => array('testuser@example.com'),
            'additionalAttribute' => array('Taco Club')
        );
        $attributeExtractor = new \sspmod_casserver_Cas_AttributeExtractor();
        $result = $attributeExtractor->extractUserAndAttributes(
            $attributes,
            \SimpleSAML_Configuration::loadFromArray($casConfig)
        );

        $this->assertEquals('testuser@example.com', $result['user']);
        $this->assertEquals(array('additionalAttribute' => array('Taco Club')), $result['attributes']);
    }
}
