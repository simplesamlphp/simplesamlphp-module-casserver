<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller\Traits;

use SimpleSAML\CAS\Constants as C;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module\casserver\Cas\ServiceValidator;
use SimpleSAML\Module\casserver\Cas\TicketValidator;
use SimpleSAML\Module\casserver\Http\XmlResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * @param   Request      $request
     * @param   string       $method
     * @param   bool         $renew
     * @param   string|null  $target
     * @param   string|null  $ticket
     * @param   string|null  $service
     * @param   string|null  $pgtUrl
     *
     * @return XmlResponse
     */
    public function validate(
        Request $request,
        string $method,
        bool $renew = false,
        ?string $target = null,
        ?string $ticket = null,
        ?string $service = null,
        ?string $pgtUrl = null,
    ): XmlResponse {
        $forceAuthn = $renew;
        $serviceUrl = $service ?? $target ?? null;

        // Check if any of the required query parameters are missing
        if ($serviceUrl === null || $ticket === null) {
            $messagePostfix = $serviceUrl === null ? 'service' : 'ticket';
            $message        = "casserver: Missing service parameter: [{$messagePostfix}]";
            Logger::debug($message);

            return new XmlResponse(
                (string)$this->cas20Protocol->getValidateFailureResponse(C::ERR_INVALID_SERVICE, $message),
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            // Get the service ticket
            // `getTicket` uses the unserializable method and Objects may throw Throwables in their
            // unserialization handlers.
            $serviceTicket = $this->ticketStore->getTicket($ticket);
            // Delete the ticket
            $this->ticketStore->deleteTicket($ticket);
        } catch (\Exception $e) {
            $messagePostfix = '';
            if (!empty($e->getMessage())) {
                $messagePostfix = ': ' . var_export($e->getMessage(), true);
            }
            $message = 'casserver:serviceValidate: internal server error' . $messagePostfix;
            Logger::error($message);

            return new XmlResponse(
                (string)$this->cas20Protocol->getValidateFailureResponse(C::ERR_INVALID_SERVICE, $message),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $failed  = false;
        $message = '';
        if (empty($serviceTicket)) {
            // No ticket
            $message = 'Ticket ' . var_export($ticket, true) . ' not recognized';
            $failed  = true;
        } elseif ($method === 'serviceValidate' && $this->ticketFactory->isProxyTicket($serviceTicket)) {
            $message = 'Ticket ' . var_export($ticket, true) .
                ' is a proxy ticket. Use proxyValidate instead.';
            $failed  = true;
        } elseif (!$this->ticketFactory->isServiceTicket($serviceTicket)) {
            // This is not a service ticket
            $message = 'Ticket ' . var_export($ticket, true) . ' is not a service ticket';
            $failed  = true;
        } elseif ($this->ticketFactory->isExpired($serviceTicket)) {
            // the ticket has expired
            $message = 'Ticket ' . var_export($ticket, true) . ' has expired';
            $failed  = true;
        } elseif ($this->sanitize($serviceTicket['service']) !== $this->sanitize($serviceUrl)) {
            // The service url we passed to the query parameters does not match the one in the ticket.
            $message = 'Mismatching service parameters: expected ' .
                var_export($serviceTicket['service'], true) .
                ' but was: ' . var_export($serviceUrl, true);
            $failed  = true;
        } elseif ($forceAuthn && !$serviceTicket['forceAuthn']) {
            // If `forceAuthn` is required but not set in the ticket
            $message = 'Ticket was issued from single sign on session';
            $failed  = true;
        }

        if ($failed) {
            $finalMessage = 'casserver:validate: ' . $message;
            Logger::error($finalMessage);

            return new XmlResponse(
                (string)$this->cas20Protocol->getValidateFailureResponse(C::ERR_INVALID_SERVICE, $message),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $attributes = $serviceTicket['attributes'];
        $this->cas20Protocol->setAttributes($attributes);

        if (isset($pgtUrl)) {
            $sessionTicket = $this->ticketStore->getTicket($serviceTicket['sessionId']);
            if (
                $sessionTicket !== null
                && $this->ticketFactory->isSessionTicket($sessionTicket)
                && !$this->ticketFactory->isExpired($sessionTicket)
            ) {
                $proxyGrantingTicket = $this->ticketFactory->createProxyGrantingTicket(
                    [
                        'userName' => $serviceTicket['userName'],
                        'attributes' => $attributes,
                        'forceAuthn' => false,
                        'proxies' => array_merge(
                            [$serviceUrl],
                            $serviceTicket['proxies'],
                        ),
                        'sessionId' => $serviceTicket['sessionId'],
                    ],
                );
                try {
                    // Here we assume that the fetch will throw on any error.
                    // The generation of the proxy-granting-ticket or the corresponding proxy granting ticket IOU may
                    // fail due to the proxy callback url failing to meet the minimum security requirements such as
                    // failure to establish trust between peers or unresponsiveness of the endpoint, etc.
                    // In case of failure, no proxy-granting ticket will be issued and the CAS service response
                    // as described in Section 2.5.2 MUST NOT contain a <proxyGrantingTicket> block.
                    // At this point, the issuance of a proxy-granting ticket is halted and service ticket
                    // validation will fail.
                    $data = $this->httpUtils->fetch(
                        $pgtUrl . '?pgtIou=' . $proxyGrantingTicket['iou'] . '&pgtId=' . $proxyGrantingTicket['id'],
                    );
                    Logger::debug(__METHOD__ . '::data: ' . var_export($data, true));
                    $this->cas20Protocol->setProxyGrantingTicketIOU($proxyGrantingTicket['iou']);
                    $this->ticketStore->addTicket($proxyGrantingTicket);
                } catch (\Exception $e) {
                    return new XmlResponse(
                        (string)$this->cas20Protocol->getValidateFailureResponse(
                            C::ERR_INVALID_SERVICE,
                            'Proxy callback url is failing.',
                        ),
                        Response::HTTP_BAD_REQUEST,
                    );
                }
            }
        }

        return new XmlResponse(
            (string)$this->cas20Protocol->getValidateSuccessResponse($serviceTicket['userName']),
            Response::HTTP_OK,
        );
    }
}
