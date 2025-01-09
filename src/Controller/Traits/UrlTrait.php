<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller\Traits;

use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\ServiceValidator;
use SimpleSAML\Module\casserver\Cas\TicketValidator;
use Symfony\Component\HttpFoundation\Request;

trait UrlTrait
{
    /**
     * @deprecated
     * @see ServiceValidator
     * @param string $service
     * @param array $legal_service_urls
     * @return bool
     */
    public function checkServiceURL(string $service, array $legal_service_urls): bool
    {
        //delegate to ServiceValidator until all references to this can be cleaned up
        $config = Configuration::loadFromArray(['legal_service_urls' => $legal_service_urls]);
        $serviceValidator = new ServiceValidator($config);
        return $serviceValidator->checkServiceURL($service) !== null;
    }

    /**
     * @param string $parameter
     * @return string
     */
    public function sanitize(string $parameter): string
    {
        return TicketValidator::sanitize($parameter);
    }

    /**
     * Parse the query Parameters from $_GET global and return them in an array.
     *
     * @param   Request     $request
     * @param   array|null  $sessionTicket
     *
     * @return array
     */
    public function parseQueryParameters(Request $request, ?array $sessionTicket): array
    {
        $forceAuthn = $this->getRequestParam($request, 'renew');
        $sessionRenewId = !empty($sessionTicket['renewId']) ? $sessionTicket['renewId'] : null;

        $queryParameters = $request->query->all();
        $requestParameters = $request->request->all();

        $query = array_merge($requestParameters, $queryParameters);

        if ($sessionRenewId && $forceAuthn) {
            $query['renewId'] = $sessionRenewId;
        }

        if (isset($query['language'])) {
            $query['language'] = is_string($query['language']) ? $query['language'] : null;
        }

        return $query;
    }

    /**
     * @param   Request  $request
     * @param   string   $paramName
     *
     * @return mixed
     */
    public function getRequestParam(Request $request, string $paramName): mixed
    {
        return $request->query->get($paramName) ?? $request->request->get($paramName) ?? null;
    }
}
