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

    /**
     * Confirm empty authproc has no affect
     */
    public function testEmptyAuthproc()
    {
        $casConfig = array(// Default is to use eppn and copy all attributes
        );

        $attributes = array(
            'eduPersonPrincipalName' => array('testuser@example.com'),
            'additionalAttribute' => array('Taco Club'),
            'authproc' => array(),
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
     * Test authproc configurations can adjust the attributes.
     */
    public function testAuthprocConfig()
    {
        // Authproc filters need a config.php defined
        putenv('SIMPLESAMLPHP_CONFIG_DIR=' . dirname(__DIR__) . '/config/');
        $casConfig = array(// Default is to use eppn and copy all attributes
            'authproc' => array(
                array(
                    'class' => 'core:AttributeMap',
                    'oid2name',
                    'urn:example' => 'additionalAttribute'
                )
            ),
            'attributes_to_transfer' => array(
                'not-affected-by-authproc',
                'additionalAttribute'
            )
        );

        $attributes = array(
            'urn:oid:1.3.6.1.4.1.5923.1.1.1.6' => array('testuser@example.com'),
            'urn:example' => array('Taco Club'),
            'not-affected-by-authproc' => array('Value')
        );
        $attributeExtractor = new \sspmod_casserver_Cas_AttributeExtractor();
        // The authproc filters will remap the attributes prior to mapping them to CAS attributes
        $result = $attributeExtractor->extractUserAndAttributes(
            $attributes,
            \SimpleSAML_Configuration::loadFromArray($casConfig)
        );

        $expectedAttributes = array(
            'additionalAttribute' => array('Taco Club'),
            'not-affected-by-authproc' => array('Value')
        );
        $this->assertEquals('testuser@example.com', $result['user']);
        $this->assertEquals($expectedAttributes, $result['attributes']);
    }
}
