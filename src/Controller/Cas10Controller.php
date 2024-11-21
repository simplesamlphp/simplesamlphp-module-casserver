<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module\casserver\Cas\Factories\TicketFactory;
use SimpleSAML\Module\casserver\Cas\Protocol\Cas10;
use SimpleSAML\Module\casserver\Controller\Traits\UrlTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

#[AsController]
class Cas10Controller
{
    use UrlTrait;

    /** @var Logger */
    protected Logger $logger;

    /** @var Configuration */
    protected Configuration $casConfig;

    /** @var Cas10 */
    protected Cas10 $cas10Protocol;

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
        Configuration $casConfig = null,
        $ticketStore = null,
    ) {
        $this->casConfig = $casConfig ?? Configuration::getConfig('module_casserver.php');
        $this->cas10Protocol = new Cas10($this->casConfig);
        /* Instantiate ticket factory */
        $this->ticketFactory = new TicketFactory($this->casConfig);
        /* Instantiate ticket store */
        $ticketStoreConfig = $this->casConfig->getOptionalValue(
            'ticketstore',
            ['class' => 'casserver:FileSystemTicketStore'],
        );
        $ticketStoreClass = 'SimpleSAML\\Module\\casserver\\Cas\\Ticket\\'
            . explode(':', $ticketStoreConfig['class'])[1];
        /** @psalm-suppress InvalidStringClass */
        $this->ticketStore = $ticketStore ?? new $ticketStoreClass($this->casConfig);
    }

    /**
     * @param   Request      $request
     * @param   bool         $renew
     * @param   string|null  $ticket
     * @param   string|null  $service
     *
     * @return Response
     */
    public function validate(
        Request $request,
        #[MapQueryParameter] bool $renew = false,
        #[MapQueryParameter] ?string $ticket = null,
        #[MapQueryParameter] ?string $service = null,
    ): Response {

        $forceAuthn = $renew;
        // Check if any of the required query parameters are missing
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
        } catch (\Exception $e) {
            Logger::error('casserver:validate: internal server error. ' . var_export($e->getMessage(), true));
            return new Response(
                $this->cas10Protocol->getValidateFailureResponse(),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $failed = false;
        $message = '';
        // No ticket
        if ($serviceTicket === null) {
            $message = 'ticket: ' . var_export($ticket, true) . ' not recognized';
            $failed = true;
            // This is not a service ticket
        } elseif (!$this->ticketFactory->isServiceTicket($serviceTicket)) {
            $message = 'ticket: ' . var_export($ticket, true) . ' is not a service ticket';
            $failed = true;
            // the ticket has expired
        } elseif ($this->ticketFactory->isExpired($serviceTicket)) {
            $message = 'Ticket has ' . var_export($ticket, true) . ' expired';
            $failed = true;
        } elseif ($this->sanitize($serviceTicket['service']) !== $this->sanitize($service)) {
            // The service we pass to the query parameters does not match the one in the ticket.
            $message = 'Mismatching service parameters: expected ' .
                var_export($serviceTicket['service'], true) .
                ' but was: ' . var_export($service, true);
            $failed = true;
        } elseif ($forceAuthn) {
            // If the forceAuthn/renew is true
            $message = 'Ticket was issued from single sign on session';
            $failed = true;
        }

        if ($failed) {
            Logger::error('casserver:validate: ' . $message, true);
            return new Response(
                $this->cas10Protocol->getValidateFailureResponse(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Get the username field
        $usernameField = $this->casConfig->getOptionalValue('attrname', 'eduPersonPrincipalName');

        // Fail if the username field is not present in the attribute list
        if (!\array_key_exists($usernameField, $serviceTicket['attributes'])) {
            Logger::error(
                'casserver:validate: internal server error. Missing user name attribute: '
                . var_export($usernameField, true),
            );
            return new Response(
                $this->cas10Protocol->getValidateFailureResponse(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Successful validation
        return new Response(
            $this->cas10Protocol->getValidateSuccessResponse($serviceTicket['attributes'][$usernameField][0]),
            Response::HTTP_OK,
        );
    }

    /**
     * @return mixed
     */
    public function getTicketStore(): mixed
    {
        return $this->ticketStore;
    }
}