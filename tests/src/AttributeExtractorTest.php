<?php

declare(strict_types=1);

namespace SimpleSAML\Casserver;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\AttributeExtractor;
use SimpleSAML\Module\casserver\Cas\Factories\ProcessingChainFactory;

class AttributeExtractorTest extends TestCase
{
    /**
     * Confirm behavior of a default configuration
     */
    public function testNoCasConfig(): void
    {
        $casConfig = [
            // Default is to use eppn and copy all attributes
        ];

        $state['Attributes'] = [
            'eduPersonPrincipalName' => ['testuser@example.com'],
            'additionalAttribute' => ['Taco Club'],
        ];
        $loadedConfig = Configuration::loadFromArray($casConfig);
        $attributeExtractor = new AttributeExtractor(
            $loadedConfig,
            new ProcessingChainFactory($loadedConfig),
        );

        $result = $attributeExtractor->extractUserAndAttributes($state);

        $this->assertEquals('testuser@example.com', $result['user']);
        $this->assertEquals($state['Attributes'], $result['attributes']);
    }


    /**
     * Test disable attribute copying
     */
    public function testNoAttributeCopying(): void
    {
        $casConfig = [
            'attributes' => false,
        ];

        $state['Attributes'] = [
            'eduPersonPrincipalName' => ['testuser@example.com'],
            'additionalAttribute' => ['Taco Club'],
        ];
        $loadedConfig = Configuration::loadFromArray($casConfig);
        $attributeExtractor = new AttributeExtractor(
            $loadedConfig,
            new ProcessingChainFactory($loadedConfig),
        );

        $result = $attributeExtractor->extractUserAndAttributes($state);

        $this->assertEquals('testuser@example.com', $result['user']);
        $this->assertEquals([], $result['attributes']);
    }


    /**
     * Confirm customizing the attribute for user and attributes to copy
     */
    public function testCustomAttributeCopy(): void
    {
        $casConfig = [
            'attrname' => 'userNameAttribute',
            'attributes_to_transfer' => [
                'exampleAttribute',
                'additionalAttribute',
            ],
        ];

        $state['Attributes'] = [
            'userNameAttribute' => ['testuser@example.com'],
            'additionalAttribute' => ['Taco Club'],
        ];
        $loadedConfig = Configuration::loadFromArray($casConfig);
        $attributeExtractor = new AttributeExtractor(
            $loadedConfig,
            new ProcessingChainFactory($loadedConfig),
        );

        $result = $attributeExtractor->extractUserAndAttributes($state);

        $this->assertEquals('testuser@example.com', $result['user']);
        $this->assertEquals(['additionalAttribute' => ['Taco Club']], $result['attributes']);
    }


    /**
     * Confirm empty authproc has no affect
     */
    public function testEmptyAuthproc(): void
    {
        $casConfig = [
            // Default is to use eppn and copy all attributes
        ];

        $state['Attributes'] = [
            'eduPersonPrincipalName' => ['testuser@example.com'],
            'additionalAttribute' => ['Taco Club'],
            'authproc' => [],
        ];
        $loadedConfig = Configuration::loadFromArray($casConfig);
        $attributeExtractor = new AttributeExtractor(
            $loadedConfig,
            new ProcessingChainFactory($loadedConfig),
        );

        $result = $attributeExtractor->extractUserAndAttributes($state);

        $this->assertEquals('testuser@example.com', $result['user']);
        $this->assertEquals($state['Attributes'], $result['attributes']);
    }


    /**
     * Test authproc configurations can adjust the attributes.
     */
    public function testAuthprocConfig(): void
    {
        // Authproc filters need a config.php defined
        putenv('SIMPLESAMLPHP_CONFIG_DIR=' . dirname(__DIR__) . '/config/');
        $casConfig = [
            // Default is to use eppn and copy all attributes
            'authproc' => [
                [
                    'class' => 'core:AttributeMap',
                    'oid2name',
                    'urn:example' => 'additionalAttribute',
                ],
            ],
            'attributes_to_transfer' => [
                'not-affected-by-authproc',
                'additionalAttribute',
            ],
        ];

        $state['Attributes'] = [
            'urn:oid:1.3.6.1.4.1.5923.1.1.1.6' => ['testuser@example.com'],
            'urn:example' => ['Taco Club'],
            'not-affected-by-authproc' => ['Value'],
        ];
        $loadedConfig = Configuration::loadFromArray($casConfig);
        $attributeExtractor = new AttributeExtractor(
            $loadedConfig,
            new ProcessingChainFactory($loadedConfig),
        );
        // The authproc filters will remap the attributes prior to mapping them to CAS attributes
        $result = $attributeExtractor->extractUserAndAttributes($state);

        $expectedAttributes = [
            'additionalAttribute' => ['Taco Club'],
            'not-affected-by-authproc' => ['Value'],
        ];
        $this->assertEquals('testuser@example.com', $result['user']);
        $this->assertEquals($expectedAttributes, $result['attributes']);
    }
}
