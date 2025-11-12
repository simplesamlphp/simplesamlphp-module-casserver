<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Cas;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module\casserver\Codebooks\OverrideConfigPropertiesEnum;

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
     *
     * @param   string  $service  The service url. Assume to already be url decoded
     *
     * @return Configuration|null Return the configuration to use for this service, or null if service is not allowed
     * @throws \ErrorException
     */
    public function checkServiceURL(string $service): ?Configuration
    {
        $isValidService = false;
        $legalUrl = 'undefined';
        $configOverride = null;
        $legalServiceUrlsConfig = $this->mainConfig->getOptionalArray('legal_service_urls', []);

        foreach ($legalServiceUrlsConfig as $index => $value) {
            // Support two styles:  0 => 'https://example' and 'https://example' => [ extra config ]
            $legalUrl = \is_int($index) ? $value : $index;
            if (empty($legalUrl)) {
                Logger::warning("Ignoring empty CAS legal service url '$legalUrl'.");
                continue;
            }

            $configOverride = \is_int($index) ? null : $value;

            if ($isValidService = $this->validateServiceIsLegal($legalUrl, $service)) {
                break;
            }
        }

        if (!$isValidService) {
            return null;
        }

        $serviceConfig = $this->mainConfig->toArray();
        // Return contextual information about which url rule triggered the validation
        $serviceConfig['casService'] = [
            'matchingUrl' => $legalUrl,
            'serviceUrl'  => $service,
        ];
        if ($configOverride !== null) {
            // We need to remove all the unsupported configuration keys
            $supportedProperties = array_column(OverrideConfigPropertiesEnum::cases(), 'value');
            $configOverride = array_filter(
                $configOverride,
                static fn($property) => \in_array($property, $supportedProperties, true),
                ARRAY_FILTER_USE_KEY,
            );
            // Merge the configurations
            $serviceConfig = array_merge($serviceConfig, $configOverride);
        }
        return Configuration::loadFromArray($serviceConfig);
    }

    /**
     * @param string $legalUrl The string or regex to use for comparison
     * @param string $service  The service to compare
     *
     * @return bool Whether the service is legal
     * @throws \ErrorException
     */
    protected function validateServiceIsLegal(string $legalUrl, string $service): bool
    {
        $isValid = false;
        if (!ctype_alnum($legalUrl[0])) {
            // Since "If the regex pattern passed does not compile to a valid regex, an E_WARNING is emitted. "
            // we will throw an exception if the warning is emitted and use try-catch to handle it
            set_error_handler(static function ($severity, $message, $file, $line) {
                throw new \ErrorException($message, $severity, $severity, $file, $line);
            }, E_WARNING);

            try {
                if (preg_match($legalUrl, $service) === 1) {
                    $isValid = true;
                }
            } catch (\ErrorException $e) {
                // do nothing
                Logger::warning("Invalid CAS legal service url '$legalUrl'. Error " . preg_last_error_msg());
            } finally {
                restore_error_handler();
            }
        } elseif (str_starts_with($service, $legalUrl)) {
            $isValid = true;
        }

        return $isValid;
    }
}
