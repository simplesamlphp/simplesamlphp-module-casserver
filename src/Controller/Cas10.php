<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use Exception;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Utils\Url as UrlUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function array_key_exist;
use function is_null;
use function var_export;

/**
 * Controller class for the casserver module.
 *
 * This class serves the different views available in the module.
 *
 * @package SimpleSAML\Module\casserver
 */
class Cas10
{
    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /** @var \SimpleSAML\Module\casserver\Utils\Url */
    protected UrlUtils $urlUtils;


    /**
     * Controller constructor.
     *
     * It initializes the global configuration for the controllers implemented here.
     *
     * @param \SimpleSAML\Configuration $config The configuration to use by the controllers.
     */
    public function __construct(
        Configuration $config
    ) {
        $this->config = $config;
        $this->urlUtils = new UrlUtils();
    }


    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function validate(Request $request): StreamedResponse
    {
        /* Load simpleSAMLphp, configuration and metadata */
        $casconfig = Configuration::getConfig('module_casserver.php');

        /* Instantiate protocol handler */
        $protocolClass = Module::resolveClass('casserver:Cas10', 'Cas\Protocol');

        /** @psalm-suppress InvalidStringClass */
        $protocol = new $protocolClass($casconfig);

        $response = new StreamedResponse();
        if ($request->query->has('service') && $request->query->has('ticket')) {
            $forceAuthn = $request->query->has('renew') && !!$request->query->get('renew');

            try {
                /* Instantiate ticket store */
                $ticketStoreConfig = $casconfig->getOptionalValue(
                    'ticketstore',
                    ['class' => 'casserver:FileSystemTicketStore']
                );

                $ticketStoreClass = Module::resolveClass($ticketStoreConfig['class'], 'Cas\Ticket');

                /** @psalm-suppress InvalidStringClass */
                $ticketStore = new $ticketStoreClass($casconfig);

                $ticketFactoryClass = Module::resolveClass('casserver:TicketFactory', 'Cas\Ticket');

                /** @psalm-suppress InvalidStringClass */
                $ticketFactory = new $ticketFactoryClass($casconfig);

                $ticket = $request->query->get('ticket');
                $serviceTicket = $ticketStore->getTicket($ticket);

                if (!is_null($serviceTicket) && $ticketFactory->isServiceTicket($serviceTicket)) {
                    $service = $request->query->get('service');
                    $ticketStore->deleteTicket($ticket);
                    $usernameField = $casconfig->getOptionalValue('attrname', 'eduPersonPrincipalName');

                    if (
                        !$ticketFactory->isExpired($serviceTicket) &&
                        $this->urlUtils->sanitize($serviceTicket['service']) === $this->urlUtils->sanitize($service) &&
                        (!$forceAuthn || $serviceTicket['forceAuthn']) &&
                        array_key_exists($usernameField, $serviceTicket['attributes'])
                    ) {
                        $response->setCallback(function() use ($protocol, $serviceTicket, $usernameField) {
                            echo $protocol->getValidateSuccessResponse($serviceTicket['attributes'][$usernameField][0]);
                        });
                    } else {
                        if (!array_key_exists($usernameField, $serviceTicket['attributes'])) {
                            Logger::error(sprintf(
                                'casserver:validate: internal server error. Missing user name attribute: %s',
                                var_export($usernameField, true)
                            ));

                            $response->setCallback(function() use ($protocol) {
                                echo $protocol->getValidateFailureResponse();
                            });
                        } else {
                            if ($ticketFactory->isExpired($serviceTicket)) {
                                $message = 'Ticket has ' . var_export($ticket, true) . ' expired';
                            } else {
                                if ($this->urlUtils->sanitize($serviceTicket['service']) === $this->urlUtils->sanitize($service)) {
                                    $message = 'Mismatching service parameters: expected ' .
                                    var_export($serviceTicket['service'], true) .
                                    ' but was: ' . var_export($service, true);
                                } else {
                                    $message = 'Ticket was issue from single sign on session';
                                }
                            }
                            Logger::debug('casserver:' . $message);

                            $response->setCallback(function() use ($protocol) {
                                echo $protocol->getValidateFailureResponse();
                            });
                        }
                    }
                } else {
                    if (is_null($serviceTicket)) {
                        $message = 'ticket: ' . var_export($ticket, true) . ' not recognized';
                    } else {
                        $message = 'ticket: ' . var_export($ticket, true) . ' is not a service ticket';
                    }

                    Logger::debug('casserver:' . $message);

                    $response->setCallback(function() use ($protocol) {
                        echo $protocol->getValidateFailureResponse();
                    });
                }
            } catch (Exception $e) {
                Logger::error('casserver:validate: internal server error. ' . var_export($e->getMessage(), true));

                $response->setCallback(function() use ($protocol) {
                    echo $protocol->getValidateFailureResponse();
                });
            }
        } else {
            if (!$request->query->has('service')) {
                $message = 'Missing service parameter: [service]';
            } else {
                $message = 'Missing ticket parameter: [ticket]';
            }

            Logger::debug('casserver:' . $message);
            $response->setCallback(function() use ($protocol) {
                echo $protocol->getValidateFailureResponse();
            });
        }

        return $response;
    }
}
