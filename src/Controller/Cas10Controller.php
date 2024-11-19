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

/**
 * Controller class for the casserver module.
 *
 * This class serves the different views available in the module.
 *
 * @package SimpleSAML\Module\casserver
 */
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
     * Controller constructor.
     *
     * It initializes the global configuration for the controllers implemented here.
     *
     */
    public function __construct()
    {
        $this->casConfig = Configuration::getConfig('module_casserver.php');
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
        $this->ticketStore = new $ticketStoreClass($this->casConfig);
    }

    /**
     * @param   Request  $request
     *
     * @return Response
     */
    public function validate(Request $request): Response
    {
        // Check if any of the required query parameters are missing
        if (!$request->query->has('service')) {
            Logger::debug('casserver: Missing service parameter: [service]');
            return new Response(
                $this->cas10Protocol->getValidateFailureResponse(),
                Response::HTTP_BAD_REQUEST,
            );
        } elseif (!$request->query->has('ticket')) {
            Logger::debug('casserver: Missing service parameter: [ticket]');
            return new Response(
                $this->cas10Protocol->getValidateFailureResponse(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Check if we are required to force an authentication
        $forceAuthn = $request->query->has('renew') && $request->query->get('renew');
        // Get the ticket
        $ticket = $request->query->get('ticket');
        // Get the service
        $service = $request->query->get('service');

        try {
            // Get the service ticket
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
        } elseif ($this->sanitize($serviceTicket['service']) === $this->sanitize($service)) {
            $message = 'Mismatching service parameters: expected ' .
                var_export($serviceTicket['service'], true) .
                ' but was: ' . var_export($service, true);
            $failed = true;
        } elseif ($forceAuthn && isset($serviceTicket['forceAuthn']) && $serviceTicket['forceAuthn']) {
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
        }

        // Successful validation
        return new Response(
            $this->cas10Protocol->getValidateSuccessResponse($serviceTicket['attributes'][$usernameField][0]),
            Response::HTTP_OK,
        );
    }
}
