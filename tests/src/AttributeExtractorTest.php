<?php

declare(strict_types=1);

namespace SimpleSAML\Casserver;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\AttributeExtractor;
use SimpleSAML\Module\casserver\Cas\Factories\ProcessingChainFactory;
use SimpleSAML\Utils;

class AttributeExtractorTest extends TestCase
{
    /**
     * An authproc filter that records the IdP and SP entity IDs the processing chain was built with,
     * so that the test can assert on what a real filter would observe.
     */
    private const array ENTITY_ID_OBSERVER = [
        'class' => 'core:PHP',
        'code' => '$attributes["observedIdpEntityId"] = [$state["Source"]["entityid"] ?? "MISSING"];'
            . '$attributes["observedSpEntityId"] = [$state["Destination"]["entityid"] ?? "MISSING"];',
    ];


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


    /**
     * The CAS service URL seeded into the state is handed to the authproc filters as the SP entity ID,
     * and the IdP entity ID falls back to the SimpleSAMLphp base URL when not configured.
     */
    public function testAuthprocReceivesServiceUrlAsSpEntityId(): void
    {
        putenv('SIMPLESAMLPHP_CONFIG_DIR=' . dirname(__DIR__) . '/config/');
        $serviceUrl = 'https://myservice.example.com/cas/callback';

        $casConfig = [
            'authproc' => [self::ENTITY_ID_OBSERVER],
        ];

        $state = [
            'Attributes' => [
                'eduPersonPrincipalName' => ['testuser@example.com'],
            ],
            // Seeded by LoginController from the validated ?service= / ?TARGET= parameter
            'Destination' => ['entityid' => $serviceUrl],
        ];

        $loadedConfig = Configuration::loadFromArray($casConfig);
        $attributeExtractor = new AttributeExtractor(
            $loadedConfig,
            new ProcessingChainFactory($loadedConfig),
        );

        $result = $attributeExtractor->extractUserAndAttributes($state);

        $this->assertEquals([$serviceUrl], $result['attributes']['observedSpEntityId']);

        // Regression guard: the whole failure mode is that this silently becomes an empty string.
        $observedIdpEntityId = $result['attributes']['observedIdpEntityId'][0];
        $this->assertIsString($observedIdpEntityId);
        $this->assertNotEmpty($observedIdpEntityId);
        $this->assertEquals((new Utils\HTTP())->getBaseURL(), $observedIdpEntityId);
    }


    /**
     * The configured idp_entity_id is handed to the authproc filters as the IdP entity ID.
     */
    public function testAuthprocReceivesConfiguredIdpEntityId(): void
    {
        putenv('SIMPLESAMLPHP_CONFIG_DIR=' . dirname(__DIR__) . '/config/');
        $idpEntityId = 'https://login.example.org/idp/metadata.php';

        $casConfig = [
            'idp_entity_id' => $idpEntityId,
            'authproc' => [self::ENTITY_ID_OBSERVER],
        ];

        $state = [
            'Attributes' => [
                'eduPersonPrincipalName' => ['testuser@example.com'],
            ],
        ];

        $loadedConfig = Configuration::loadFromArray($casConfig);
        $attributeExtractor = new AttributeExtractor(
            $loadedConfig,
            new ProcessingChainFactory($loadedConfig),
        );

        $result = $attributeExtractor->extractUserAndAttributes($state);

        $this->assertEquals([$idpEntityId], $result['attributes']['observedIdpEntityId']);
    }


    /**
     * Entity IDs already present in the state take precedence over the configuration, so that a state
     * resumed from an authproc filter keeps what the first pass established.
     */
    public function testAuthprocPreservesPreExistingEntityIds(): void
    {
        putenv('SIMPLESAMLPHP_CONFIG_DIR=' . dirname(__DIR__) . '/config/');
        $stateIdpEntityId = 'https://from-state.example.org/idp/metadata.php';
        $stateSpEntityId = 'https://from-state.example.com/cas/callback';

        $casConfig = [
            'idp_entity_id' => 'https://from-config.example.org/idp/metadata.php',
            'authproc' => [self::ENTITY_ID_OBSERVER],
        ];

        $state = [
            'Attributes' => [
                'eduPersonPrincipalName' => ['testuser@example.com'],
            ],
            'Source' => ['entityid' => $stateIdpEntityId],
            'Destination' => ['entityid' => $stateSpEntityId],
        ];

        $loadedConfig = Configuration::loadFromArray($casConfig);
        $attributeExtractor = new AttributeExtractor(
            $loadedConfig,
            new ProcessingChainFactory($loadedConfig),
        );

        $result = $attributeExtractor->extractUserAndAttributes($state);

        $this->assertEquals([$stateIdpEntityId], $result['attributes']['observedIdpEntityId']);
        $this->assertEquals([$stateSpEntityId], $result['attributes']['observedSpEntityId']);
    }


    /**
     * Only the casserver configuration may contribute authproc filters. Filters seeded into the SP
     * metadata by a caller must not be executed by the processing chain.
     */
    public function testAuthprocIgnoresFiltersSeededIntoSpMetadata(): void
    {
        putenv('SIMPLESAMLPHP_CONFIG_DIR=' . dirname(__DIR__) . '/config/');
        $casConfig = [
            'authproc' => [self::ENTITY_ID_OBSERVER],
        ];

        $state = [
            'Attributes' => [
                'eduPersonPrincipalName' => ['testuser@example.com'],
            ],
            'Destination' => [
                'entityid' => 'https://myservice.example.com/cas/callback',
                // A control key, not metadata. It must be dropped rather than added to the chain.
                'authproc' => [
                    [
                        'class' => 'core:PHP',
                        'code' => '$attributes["filterFromSpMetadata"] = ["should not run"];',
                    ],
                ],
            ],
        ];

        $loadedConfig = Configuration::loadFromArray($casConfig);
        $attributeExtractor = new AttributeExtractor(
            $loadedConfig,
            new ProcessingChainFactory($loadedConfig),
        );

        $result = $attributeExtractor->extractUserAndAttributes($state);

        $this->assertArrayNotHasKey('filterFromSpMetadata', $result['attributes']);
        // The configured filters still ran.
        $this->assertArrayHasKey('observedSpEntityId', $result['attributes']);
    }


    /**
     * Without a service URL the chain still runs, and the SP entity ID stays empty.
     */
    public function testAuthprocWithoutServiceUrl(): void
    {
        putenv('SIMPLESAMLPHP_CONFIG_DIR=' . dirname(__DIR__) . '/config/');
        $casConfig = [
            'idp_entity_id' => 'https://login.example.org/idp/metadata.php',
            'authproc' => [self::ENTITY_ID_OBSERVER],
        ];

        $state = [
            'Attributes' => [
                'eduPersonPrincipalName' => ['testuser@example.com'],
            ],
        ];

        $loadedConfig = Configuration::loadFromArray($casConfig);
        $attributeExtractor = new AttributeExtractor(
            $loadedConfig,
            new ProcessingChainFactory($loadedConfig),
        );

        $result = $attributeExtractor->extractUserAndAttributes($state);

        $this->assertEquals([''], $result['attributes']['observedSpEntityId']);
    }
}
