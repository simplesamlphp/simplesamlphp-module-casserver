<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Cas;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;

/**
 * Validates if a CAS service can use server
 * @package SimpleSAML\Module\casserver\Cas
 */
class ServiceValidator
{
    /**
     * @var \SimpleSAML\Configuration
     */
    private Configuration $mainConfig;

    /**
     * ServiceValidator constructor.
     * @param Configuration $mainConfig
     */
    public function __construct(Configuration $mainConfig)
    {
        $this->mainConfig = $mainConfig;
    }

    /**
     * Check that the $service is allowed, and if so return the configuration to use.
     * @param string $service The service url. Assume to already be url decoded
     * @return Configuration|null Return the configuration to use for this service, or null if service is not allowed
     */
    public function checkServiceURL(string $service): ?Configuration
    {
        $isValidService = false;
        $legalUrl = 'undefined';
        $configOverride = null;
        foreach ($this->mainConfig->getArray('legal_service_urls', []) as $index => $value) {
            // Support two styles:  0 => 'https://example' and 'https://example' => [ extra config ]
            if (is_int($index)) {
                $legalUrl = $value;
                $configOverride = null;
            } else {
                $legalUrl = $index;
                $configOverride = $value;
            }
            if (empty($legalUrl)) {
                Logger::warning("Ignoring empty CAS legal service url '$legalUrl'.");
                continue;
            }
            if (!ctype_alnum($legalUrl[0])) {
                // Probably a regex. Suppress errors incase the format is invalid
                $result = @preg_match($legalUrl, $service);
                if ($result === 1) {
                    $isValidService = true;
                    break;
                } elseif ($result === false) {
                    Logger::warning("Invalid CAS legal service url '$legalUrl'. Error " . preg_last_error());
                }
            } elseif (strpos($service, $legalUrl) === 0) {
                $isValidService = true;
                break;
            }
        }
        if ($isValidService) {
            $serviceConfig = $this->mainConfig->toArray();
            // Return contextual information about which url rule triggered the validation
            $serviceConfig['casService'] = [
                'matchingUrl' => $legalUrl,
                'serviceUrl'  => $service,
            ];
            if ($configOverride) {
                $serviceConfig = array_merge($serviceConfig, $configOverride);
            }
            return Configuration::loadFromArray($serviceConfig);
        }
        return null;
    }
}
