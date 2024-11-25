<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use SimpleSAML\CAS\Constants as C;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module\casserver\Cas\Factories\TicketFactory;
use SimpleSAML\Module\casserver\Cas\Protocol\Cas20;
use SimpleSAML\Module\casserver\Controller\Traits\UrlTrait;
use SimpleSAML\Module\casserver\Http\XmlResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

#[AsController]
class Cas20Controller
{
    use UrlTrait;

    /** @var Logger */
    protected Logger $logger;

    /** @var Configuration */
    protected Configuration $casConfig;

    /** @var Configuration */
    protected Configuration $sspConfig;

    /** @var Cas20 */
    protected Cas20 $cas20Protocol;

    /** @var TicketFactory */
    protected TicketFactory $ticketFactory;

    // this could be any configured ticket store
    protected mixed $ticketStore;

    /**
     * @param   Configuration|null  $casConfig
     * @param                       $ticketStore
     *
     * @throws \Exception
     */
    public function __construct(
        Configuration $sspConfig = null,
        Configuration $casConfig = null,
        $ticketStore = null,
    ) {
        $this->sspConfig     = $sspConfig ?? Configuration::getInstance();
        $this->casConfig     = $casConfig ?? Configuration::getConfig('module_casserver.php');
        $this->cas20Protocol = new Cas20($this->casConfig);
        /* Instantiate ticket factory */
        $this->ticketFactory = new TicketFactory($this->casConfig);
        /* Instantiate ticket store */
        $ticketStoreConfig = $this->casConfig->getOptionalValue(
            'ticketstore',
            ['class' => 'casserver:FileSystemTicketStore'],
        );
        $ticketStoreClass  = 'SimpleSAML\\Module\\casserver\\Cas\\Ticket\\'
            . explode(':', $ticketStoreConfig['class'])[1];
        /** @psalm-suppress InvalidStringClass */
        $this->ticketStore = $ticketStore ?? new $ticketStoreClass($this->casConfig);
    }

    /**
     * @param   Request      $request
     * @param   bool         $renew
     * @param   string|null  $ticket
     * @param   string|null  $service
     * @param   string|null  $pgtUrl
     *
     * @return XmlResponse
     */
    public function serviceValidate(
        Request $request,
        #[MapQueryParameter] bool $renew = false,
        #[MapQueryParameter] ?string $ticket = null,
        #[MapQueryParameter] ?string $service = null,
        #[MapQueryParameter] ?string $pgtUrl = null,
    ): XmlResponse {
        return $this->validate(
            $request,
            'serviceValidate',
            $renew,
            $ticket,
            $service,
            $pgtUrl,
        );
    }

    /**
     * @param   Request      $request
     * @param   bool         $renew
     * @param   string|null  $ticket
     * @param   string|null  $service
     * @param   string|null  $pgtUrl
     *
     * @return XmlResponse
     */
    public function proxyValidate(
        Request $request,
        #[MapQueryParameter] bool $renew = false,
        #[MapQueryParameter] ?string $ticket = null,
        #[MapQueryParameter] ?string $service = null,
        #[MapQueryParameter] ?string $pgtUrl = null,
    ): XmlResponse {
        return $this->validate(
            $request,
            'proxyValidate',
            $renew,
            $ticket,
            $service,
            $pgtUrl,
        );
    }

    /**
     * @param   Request      $request
     * @param   string       $method
     * @param   bool         $renew
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
        ?string $ticket = null,
        ?string $service = null,
        ?string $pgtUrl = null,
    ): XmlResponse {
        $forceAuthn = $renew;
        $serviceUrl = $service ?? $_GET['TARGET'] ?? null;

        // Check if any of the required query parameters are missing
        if ($serviceUrl === null || $ticket === null) {
            $messagePostfix = $serviceUrl === null ? 'service' : 'ticket';
            $message        = "casserver: Missing service parameter: [{$messagePostfix}]";
            Logger::debug($message);

            ob_start();
            echo $this->cas20Protocol->getValidateFailureResponse(C::ERR_INVALID_SERVICE, $message);
            $responseContent = ob_get_clean();

            return new XmlResponse(
                $responseContent,
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
            $message = 'casserver:serviceValidate: internal server error. ' . var_export($e->getMessage(), true);
            Logger::error($message);

            ob_start();
            echo $this->cas20Protocol->getValidateFailureResponse(C::ERR_INVALID_SERVICE, $message);
            $responseContent = ob_get_clean();

            return new XmlResponse(
                $responseContent,
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $failed  = false;
        $message = '';
        if (empty($serviceTicket)) {
            // No ticket
            $message = 'ticket: ' . var_export($ticket, true) . ' not recognized';
            $failed  = true;
        } elseif ($method === 'serviceValidate' && $this->ticketFactory->isProxyTicket($serviceTicket)) {
            $message = 'Ticket ' . var_export($_GET['ticket'], true) .
                ' is a proxy ticket. Use proxyValidate instead.';
            $failed  = true;
        } elseif (!$this->ticketFactory->isServiceTicket($serviceTicket)) {
            // This is not a service ticket
            $message = 'ticket: ' . var_export($ticket, true) . ' is not a service ticket';
            $failed  = true;
        } elseif ($this->ticketFactory->isExpired($serviceTicket)) {
            // the ticket has expired
            $message = 'Ticket has ' . var_export($ticket, true) . ' expired';
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

            ob_start();
            echo $this->cas20Protocol->getValidateFailureResponse(C::ERR_INVALID_SERVICE, $message);
            $responseContent = ob_get_clean();

            return new XmlResponse(
                $responseContent,
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
                    $this->httpUtils->fetch(
                        $pgtUrl . '?pgtIou=' . $proxyGrantingTicket['iou'] . '&pgtId=' . $proxyGrantingTicket['id'],
                    );

                    $this->cas20Protocol->setProxyGrantingTicketIOU($proxyGrantingTicket['iou']);

                    $this->ticketStore->addTicket($proxyGrantingTicket);
                } catch (Exception $e) {
                    // Fall through
                }
            }
        }

        ob_start();
        echo $this->cas20Protocol->getValidateSuccessResponse($serviceTicket['userName']);
        $successContent = ob_get_clean();

        return new XmlResponse(
            $successContent,
            Response::HTTP_OK,
        );
    }
}
