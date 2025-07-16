<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use Exception;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\Factories\TicketFactory;
use SimpleSAML\Module\casserver\Cas\Protocol\Cas10;
use SimpleSAML\Module\casserver\Cas\Ticket\TicketStore;
use SimpleSAML\Module\casserver\Controller\Traits\UrlTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

use function var_export;

#[AsController]
class Cas10Controller
{
    use UrlTrait;

    /** @var \SimpleSAML\Logger */
    protected Logger $logger;

    /** @var \SimpleSAML\Configuration */
    protected Configuration $casConfig;

    /** @var \SimpleSAML\Module\casserver\Cas\Protocol\Cas10 */
    protected Cas10 $cas10Protocol;

    /** @var \SimpleSAML\Module\casserver\Cas\Factories\TicketFactory */
    protected TicketFactory $ticketFactory;

    /** @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore */
    protected TicketStore $ticketStore;

    /**
     * @param \SimpleSAML\Configuration $sspConfig
     * @param \SimpleSAML\Configuration|null $casConfig
     * @param \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore|null $ticketStore
     *
     * @throws \Exception
     */
    public function __construct(
        private readonly Configuration $sspConfig,
        ?Configuration $casConfig = null,
        ?TicketStore $ticketStore = null,
    ) {
        // We are using this work around in order to bypass Symfony's autowiring for cas configuration. Since
        // the configuration class is the same, it loads the ssp configuration twice. Still, we need the constructor
        // argument in order to facilitate testin.
        $this->casConfig = ($casConfig === null || $casConfig === $sspConfig)
            ? Configuration::getConfig('module_casserver.php') : $casConfig;
        $this->cas10Protocol = new Cas10($this->casConfig);
        /* Instantiate ticket factory */
        $this->ticketFactory = new TicketFactory($this->casConfig);
        /* Instantiate ticket store */
        $ticketStoreConfig = $this->casConfig->getOptionalValue(
            'ticketstore',
            ['class' => 'casserver:FileSystemTicketStore'],
        );
        $ticketStoreClass = Module::resolveClass($ticketStoreConfig['class'], 'Cas\Ticket');
        $this->ticketStore = $ticketStore ?? new $ticketStoreClass($this->casConfig);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param bool $renew          [OPTIONAL] - if this parameter is set, ticket validation will only succeed if the
     *                             service ticket was issued from the presentation of the user’s primary credentials.
     *                             It will fail if the ticket was issued from a single sign-on session.
     * @param string|null $ticket  [REQUIRED] - the service ticket issued by /login.
     * @param string|null $service [REQUIRED] - the identifier of the service for which the ticket was issued
     *
     * @return Response
     */
    public function validate(
        Request $request,
        #[MapQueryParameter] ?string $ticket = null,
        #[MapQueryParameter] bool $renew = false,
        #[MapQueryParameter] ?string $service = null,
    ): Response {
        $forceAuthn = $renew;
        // Check if any of the required query parameters are missing
        // Even though we can delegate the check to Symfony's `MapQueryParameter` we cannot return
        // the failure response needed. As a result, we allow a default value, and we handle the missing
        // values afterward.
        if ($service === null || $ticket === null) {
            $messagePostfix = $service === null ? 'service' : 'ticket';
            Logger::debug("casserver: Missing service parameter: [{$messagePostfix}]");
            return new Response(
                $this->cas10Protocol->getValidateFailureResponse(),
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
        } catch (Exception $e) {
            Logger::error('casserver:validate: internal server error. ' . var_export($e->getMessage(), true));
            return new Response(
                $this->cas10Protocol->getValidateFailureResponse(),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $failed = false;
        $message = '';
        if (empty($serviceTicket)) {
            // No ticket
            $message = 'ticket: ' . var_export($ticket, true) . ' not recognized';
            $failed = true;
        } elseif (!$this->ticketFactory->isServiceTicket($serviceTicket)) {
            // This is not a service ticket
            $message = 'ticket: ' . var_export($ticket, true) . ' is not a service ticket';
            $failed = true;
        } elseif ($this->ticketFactory->isExpired($serviceTicket)) {
            // the ticket has expired
            $message = 'Ticket has ' . var_export($ticket, true) . ' expired';
            $failed = true;
        } elseif ($this->sanitize($serviceTicket['service']) !== $this->sanitize($service)) {
            // The service url we passed to the query parameters does not match the one in the ticket.
            $message = 'Mismatching service parameters: expected ' .
                var_export($serviceTicket['service'], true) .
                ' but was: ' . var_export($service, true);
            $failed = true;
        } elseif ($forceAuthn && !$serviceTicket['forceAuthn']) {
            // If `forceAuthn` is required but not set in the ticket
            $message = 'Ticket was issued from single sign on session';
            $failed = true;
        }

        if ($failed) {
            Logger::error('casserver:validate: ' . $message);
            return new Response(
                $this->cas10Protocol->getValidateFailureResponse(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Fail if the username is not present in the ticket
        if (empty($serviceTicket['userName'])) {
            return new Response(
                $this->cas10Protocol->getValidateFailureResponse(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Successful validation
        return new Response(
            $this->cas10Protocol->getValidateSuccessResponse($serviceTicket['userName']),
            Response::HTTP_OK,
        );
    }

    /**
     * Used by the unit tests
     *
     * @return \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore
     */
    public function getTicketStore(): TicketStore
    {
        return $this->ticketStore;
    }
}
