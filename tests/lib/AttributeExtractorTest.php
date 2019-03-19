<?php

namespace Simplesamlphp\Casserver;

class AttributeExtractorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Confirm behavior of a default configuration
     */
    public function testNoCasConfig()
    {
        $casConfig = [
            // Default is to use eppn and copy all attributes
        ];

        $attributes = [
            'eduPersonPrincipalName' => ['testuser@example.com'],
            'additionalAttribute' => ['Taco Club']
        ];
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
        $casConfig = [
            'attributes' => false
        ];

        $attributes = [
            'eduPersonPrincipalName' => ['testuser@example.com'],
            'additionalAttribute' => ['Taco Club']
        ];
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
        $casConfig = [
            'attrname' => 'userNameAttribute',
            'attributes_to_transfer' => [
                'exampleAttribute',
                'additionalAttribute'
            ]
        ];

        $attributes = [
            'userNameAttribute' => ['testuser@example.com'],
            'additionalAttribute' => ['Taco Club']
        ];
        $attributeExtractor = new \sspmod_casserver_Cas_AttributeExtractor();
        $result = $attributeExtractor->extractUserAndAttributes(
            $attributes,
            \SimpleSAML_Configuration::loadFromArray($casConfig)
        );

        $this->assertEquals('testuser@example.com', $result['user']);
        $this->assertEquals(['additionalAttribute' => ['Taco Club']], $result['attributes']);
    }

    /**
     * Confirm empty authproc has no affect
     */
    public function testEmptyAuthproc()
    {
        $casConfig = [
            // Default is to use eppn and copy all attributes
        ];

        $attributes = [
            'eduPersonPrincipalName' => ['testuser@example.com'],
            'additionalAttribute' => ['Taco Club'],
            'authproc' => [],
        ];
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
        $casConfig = [
            // Default is to use eppn and copy all attributes
            'authproc' => [
                [
                    'class' => 'core:AttributeMap',
                    'oid2name',
                    'urn:example' => 'additionalAttribute'
                ]
            ],
            'attributes_to_transfer' => [
                'not-affected-by-authproc',
                'additionalAttribute'
            ]
        ];

        $attributes = [
            'urn:oid:1.3.6.1.4.1.5923.1.1.1.6' => ['testuser@example.com'],
            'urn:example' => ['Taco Club'],
            'not-affected-by-authproc' => ['Value']
        ];
        $attributeExtractor = new \sspmod_casserver_Cas_AttributeExtractor();
        // The authproc filters will remap the attributes prior to mapping them to CAS attributes
        $result = $attributeExtractor->extractUserAndAttributes(
            $attributes,
            \SimpleSAML\Configuration::loadFromArray($casConfig)
        );

        $expectedAttributes = [
            'additionalAttribute' => ['Taco Club'],
            'not-affected-by-authproc' => ['Value']
        ];
        $this->assertEquals('testuser@example.com', $result['user']);
        $this->assertEquals($expectedAttributes, $result['attributes']);
    }
}
