<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Cas;

use ErrorException;
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
     * ServiceValidator constructor.
     * @param \SimpleSAML\Configuration $mainConfig
     */
    public function __construct(
        protected Configuration $mainConfig,
    ) {
    }


    /**
     * Check that a service url, exactly as it was received in the request, is allowed, and if so return the
     * configuration to use.
     *
     * The legal service urls are matched against the sanitized form of the service (url decoded, without
     * ';jsessionid'), while tickets are issued for, and users are sent to, the service as received. The service is
     * therefore only accepted when it starts with "scheme://host[:port]" and sanitizing it leaves that part
     * untouched, so that the host that was checked is the host that is used.
     *
     * @param string $service The service url as received in the request, neither url decoded nor sanitized
     *
     * @return \SimpleSAML\Configuration|null Return the configuration to use for this service,
     *   or null if service is not allowed
     * @throws \ErrorException
     */
    public function checkRequestedServiceURL(string $service): ?Configuration
    {
        $sanitizedService = TicketValidator::sanitize($service);
        if (!self::hasStableAuthority($service, $sanitizedService)) {
            Logger::warning(
                'casserver: Rejecting service url with a missing or ambiguous authority: '
                . json_encode($service, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            );
            return null;
        }

        return $this->checkServiceURL($sanitizedService);
    }


    /**
     * Check that the $service is allowed, and if so return the configuration to use.
     *
     * Only the given string is checked. For a service url received in a request, which is later used as received,
     * use checkRequestedServiceURL() instead.
     *
     * @param   string  $service  The service url. Assume to already be url decoded
     *
     * @return \SimpleSAML\Configuration|null Return the configuration to use for this service,
     *   or null if service is not allowed
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
                throw new ErrorException($message, $severity, $severity, $file, $line);
            }, E_WARNING);

            try {
                if (preg_match($legalUrl, $service) === 1) {
                    $isValid = true;
                }
            } catch (ErrorException $e) {
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


    /**
     * Whether a service starts with a scheme and authority (RFC 3986, section 3) that its sanitized form shares, and
     * that authority is a plain host with an optional port.
     *
     * A service without a leading "scheme://" is refused: browsers and SimpleSAMLphp still find a host in forms such
     * as "//host", "https:\\host" or " https://host" (WHATWG parsers strip whitespace and accept backslashes).
     * Userinfo, percent-encoding, backslashes, whitespace and control characters are refused in the authority: URL
     * parsers disagree about where such an authority ends, and decoding can move that end, so the host that
     * receives the ticket could differ from the host that was checked.
     *
     * @param string $service The service url as received
     * @param string $sanitizedService The same service url after sanitizing
     *
     * @return bool
     */
    private static function hasStableAuthority(string $service, string $sanitizedService): bool
    {
        $schemeAndAuthority = self::getSchemeAndAuthority($service);
        if ($schemeAndAuthority === null) {
            return false;
        }

        $authority = substr($schemeAndAuthority, strpos($schemeAndAuthority, '://') + 3);
        if ($authority === '' || preg_match('/[@%\\\\\x00-\x20\x7F]/', $authority) === 1) {
            return false;
        }

        return self::getSchemeAndAuthority($sanitizedService) === $schemeAndAuthority;
    }


    /**
     * The leading "scheme://authority" of a url, where the authority ends at the first "/", "?" or "#".
     *
     * @param string $url
     *
     * @return string|null The scheme and authority, or null if the url does not start with "scheme://"
     */
    private static function getSchemeAndAuthority(string $url): ?string
    {
        return preg_match('~^[A-Za-z][A-Za-z0-9+.\-]*://[^/?#]*~', $url, $matches) === 1 ? $matches[0] : null;
    }
}
