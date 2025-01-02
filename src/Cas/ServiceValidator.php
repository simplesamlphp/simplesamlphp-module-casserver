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

            // URL String
            if (str_starts_with($service, $legalUrl)) {
                $isValidService = true;
                break;
            }

            // Regex
            // Since "If the regex pattern passed does not compile to a valid regex, an E_WARNING is emitted. "
            // we will throw an exception if the warning is emitted and use try-catch to handle it
            set_error_handler(static function ($severity, $message, $file, $line) {
                throw new \ErrorException($message, $severity, $severity, $file, $line);
            }, E_WARNING);

            try {
                $result = preg_match($legalUrl, $service);
                if ($result !== 1) {
                    throw new \RuntimeException('Service URL does not match legal service URL.');
                }
                $isValidService = true;
                break;
            } catch (\Exception $e) {
                // do nothing
                Logger::warning("Invalid CAS legal service url '$legalUrl'. Error " . preg_last_error());
            } finally {
                restore_error_handler();
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
        if ($configOverride) {
            $serviceConfig = array_merge($serviceConfig, $configOverride);
        }
        return Configuration::loadFromArray($serviceConfig);
    }
}
