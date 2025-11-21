<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller\Traits;

use Exception;
use SimpleSAML\CAS\Constants as C;
use SimpleSAML\Logger;
use SimpleSAML\Module\casserver\Http\XmlResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_merge;
use function var_export;

trait TicketValidatorTrait
{
    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string $method
     * @param bool $renew
     * @param string|null $target
     * @param string|null $ticket
     * @param string|null $service
     * @param string|null $pgtUrl
     *
     * @return \SimpleSAML\Module\casserver\Http\XmlResponse
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
            $message = "casserver: Missing {$messagePostfix} parameter: [{$messagePostfix}]";
            Logger::debug($message);

            return new XmlResponse(
                (string)$this->cas20Protocol->getValidateFailureResponse(C::ERR_INVALID_SERVICE, $message),
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            // Get the service ticket
            // `getTicket` uses the unserializable method and Objects may throw "Throwables" in their
            // un-serialization handlers.
            $serviceTicket = $this->ticketStore->getTicket($ticket);
        } catch (Exception $e) {
            $messagePostfix = '';
            if (!empty($e->getMessage())) {
                $messagePostfix = ': ' . var_export($e->getMessage(), true);
            }
            $message = 'casserver:serviceValidate: internal server error' . $messagePostfix;
            Logger::error(__METHOD__ . '::' . $message);

            return new XmlResponse(
                (string)$this->cas20Protocol->getValidateFailureResponse(C::ERR_INTERNAL_ERROR, $message),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $failed  = false;
        $message = '';
        // Below, we do not have a ticket or the ticket does not meet the very basic criteria that allow
        // any further handling
        if ($message = $this->validateServiceTicket($serviceTicket, $ticket, $method)) {
            $finalMessage = 'casserver:validate: ' . $message;
            Logger::error(__METHOD__ . '::' . $finalMessage);

            return new XmlResponse(
                (string)$this->cas20Protocol->getValidateFailureResponse(C::ERR_INVALID_SERVICE, $message),
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Delete the ticket
        $this->ticketStore->deleteTicket($ticket);

        // Check if the ticket
        // - has expired
        // - does not pass sanitization
        // - forceAutnn criteria are not met
        if ($this->ticketFactory->isExpired($serviceTicket)) {
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
            Logger::error(__METHOD__ . '::' . $finalMessage);

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
                } catch (Exception $e) {
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

    /**
     * @param array{'id': string}|null $serviceTicket
     *
     * @return ?string Message on failure, null on success
     */
    private function validateServiceTicket(?array $serviceTicket, string $ticket, string $method): ?string
    {
        if (empty($serviceTicket)) {
            return 'Ticket ' . var_export($ticket, true) . ' not recognized';
        }

        $isServiceTicket = $this->ticketFactory->isServiceTicket($serviceTicket);
        if ($method === 'serviceValidate' && !$isServiceTicket) {
            return 'Ticket ' . var_export($ticket, true) . ' is not a service ticket.';
        }

        if ($method === 'proxyValidate' && !$isServiceTicket && !$this->ticketFactory->isProxyTicket($serviceTicket)) {
            return 'Ticket ' . var_export($ticket, true) . ' is not a proxy ticket.';
        }

        return null;
    }
}
