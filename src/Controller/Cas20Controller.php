<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use SimpleSAML\CAS\Constants as C;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\Factories\TicketFactory;
use SimpleSAML\Module\casserver\Cas\Protocol\Cas20;
use SimpleSAML\Module\casserver\Cas\Ticket\TicketStore;
use SimpleSAML\Module\casserver\Controller\Traits\TicketValidatorTrait;
use SimpleSAML\Module\casserver\Controller\Traits\UrlTrait;
use SimpleSAML\Module\casserver\Http\XmlResponse;
use SimpleSAML\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

#[AsController]
class Cas20Controller
{
    use UrlTrait;
    use TicketValidatorTrait;


    /** @var \SimpleSAML\Logger */
    protected Logger $logger;

    /** @var \SimpleSAML\Utils\HTTP */
    protected Utils\HTTP $httpUtils;

    /** @var \SimpleSAML\Configuration */
    protected Configuration $casConfig;

    /** @var \SimpleSAML\Module\casserver\Cas\Protocol\Cas20 */
    protected Cas20 $cas20Protocol;

    /** @var \SimpleSAML\Module\casserver\Cas\Factories\TicketFactory */
    protected TicketFactory $ticketFactory;

    /** @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore */
    protected TicketStore $ticketStore;


    /**
     * @param \SimpleSAML\Configuration $sspConfig
     * @param \SimpleSAML\Configuration|null $casConfig
     * @param \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore|null $ticketStore
     * @param \SimpleSAML\Utils\HTTP|null $httpUtils
     *
     * @throws \Exception
     */
    public function __construct(
        private readonly Configuration $sspConfig,
        ?Configuration $casConfig = null,
        ?TicketStore $ticketStore = null,
        ?Utils\HTTP $httpUtils = null,
    ) {
        // We are using this work around in order to bypass Symfony's autowiring for cas configuration. Since
        // the configuration class is the same, it loads the ssp configuration twice. Still, we need the constructor
        // argument in order to facilitate testing
        $this->casConfig = ($casConfig === null || $casConfig === $sspConfig)
            ? Configuration::getConfig('module_casserver.php') : $casConfig;
        $this->cas20Protocol = new Cas20($this->casConfig);

        /* Instantiate ticket factory */
        $this->ticketFactory = new TicketFactory($this->casConfig);

        /* Instantiate ticket store */
        $ticketStoreConfig = $this->casConfig->getOptionalValue(
            'ticketstore',
            ['class' => 'casserver:FileSystemTicketStore'],
        );
        $ticketStoreClass = Module::resolveClass($ticketStoreConfig['class'], 'Cas\Ticket');
        $this->ticketStore = $ticketStore ?? new $ticketStoreClass($this->casConfig);
        $this->httpUtils = $httpUtils ?? new Utils\HTTP();
    }


    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string|null $TARGET   Query parameter name for "service" used by older CAS clients'
     * @param bool $renew           [OPTIONAL] - if this parameter is set, ticket validation will only succeed
     *                                 if the service ticket was issued from the presentation of the user’s primary
     *                                 credentials. It will fail if the ticket was issued from a single sign-on session.
     * @param string|null $ticket   [REQUIRED] - the service ticket issued by /login
     * @param string|null $service  [REQUIRED] - the identifier of the service for which the ticket was issued
     * @param string|null $pgtUrl   [OPTIONAL] - the URL of the proxy callback
     *
     * @return \SimpleSAML\Module\casserver\Http\XmlResponse
     */
    public function serviceValidate(
        Request $request,
        #[MapQueryParameter] ?string $TARGET = null,
        #[MapQueryParameter] bool $renew = false,
        #[MapQueryParameter] ?string $ticket = null,
        #[MapQueryParameter] ?string $service = null,
        #[MapQueryParameter] ?string $pgtUrl = null,
    ): XmlResponse {
        return $this->validate(
            request: $request,
            method:  'serviceValidate',
            renew:   $renew,
            target:  $TARGET,
            ticket:  $ticket,
            service: $service,
            pgtUrl:  $pgtUrl,
        );
    }


    /**
     * /proxy provides proxy tickets to services that have
     * acquired proxy-granting tickets and will be proxying authentication to back-end services.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string|null $targetService  [REQUIRED] - the service identifier of the back-end service.
     * @param string|null $pgt            [REQUIRED] - the proxy-granting ticket acquired by the service
     *                                       during service ticket or proxy ticket validation.
     *
     * @return \SimpleSAML\Module\casserver\Http\XmlResponse
     * @throws \ErrorException
     */
    public function proxy(
        Request $request,
        #[MapQueryParameter] ?string $targetService = null,
        #[MapQueryParameter] ?string $pgt = null,
    ): XmlResponse {
        // NOTE: Here we do not override the configuration
        $legal_target_service_urls = $this->casConfig->getOptionalValue('legal_target_service_urls', []);
        // Fail if
        $message = match (true) {
            // targetService parameter is not defined
            $targetService === null => 'Missing target service parameter [targetService]',
            // pgt parameter is not defined
            $pgt === null => 'Missing proxy granting ticket parameter: [pgt]',
            !$this->checkServiceURL($this->sanitize($targetService), $legal_target_service_urls) =>
                "Target service parameter not listed as a legal service: [targetService] = {$targetService}",
            default => null,
        };

        if (!empty($message)) {
            return new XmlResponse(
                (string)$this->cas20Protocol->getValidateFailureResponse(C::ERR_INVALID_REQUEST, $message),
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Get the ticket
        $proxyGrantingTicket = $this->ticketStore->getTicket($pgt);
        $message = match (true) {
            // targetService parameter is not defined
            $proxyGrantingTicket === null => "Ticket {$pgt} not recognized",
            // pgt parameter is not defined
            !$this->ticketFactory->isProxyGrantingTicket($proxyGrantingTicket)
            => "Not a valid proxy granting ticket id: {$pgt}",
            default => null,
        };

        if (!empty($message)) {
            return new XmlResponse(
                (string)$this->cas20Protocol->getValidateFailureResponse('BAD_PGT', $message),
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Get the session id from the ticket
        $sessionTicket = $this->ticketStore->getTicket($proxyGrantingTicket['sessionId']);

        if (
            $sessionTicket === null
            || $this->ticketFactory->isSessionTicket($sessionTicket) === false
            || $this->ticketFactory->isExpired($sessionTicket)
        ) {
            $message = "Ticket {$pgt} has expired";
            Logger::debug('casserver:' . $message);

            return new XmlResponse(
                (string)$this->cas20Protocol->getValidateFailureResponse('BAD_PGT', $message),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $proxyTicket = $this->ticketFactory->createProxyTicket(
            [
                'service' => $targetService,
                'forceAuthn' => $proxyGrantingTicket['forceAuthn'],
                'attributes' => $proxyGrantingTicket['attributes'],
                'proxies' => $proxyGrantingTicket['proxies'],
                'sessionId' => $proxyGrantingTicket['sessionId'],
            ],
        );

        $this->ticketStore->addTicket($proxyTicket);

        return new XmlResponse(
            (string)$this->cas20Protocol->getProxySuccessResponse($proxyTicket['id']),
            Response::HTTP_OK,
        );
    }


    /**
     * @param \Symfony\Component\HttpFoundation\Request      $request
     * @param string|null $TARGET   Query parameter name for "service" used by older CAS clients'
     * @param bool $renew           [OPTIONAL] - if this parameter is set, ticket validation will only succeed
     *                                 if the service ticket was issued from the presentation of the user’s primary
     *                                 credentials. It will fail if the ticket was issued from a single sign-on session.
     * @param string|null $ticket   [REQUIRED] - the service ticket issued by /login
     * @param string|null $service  [REQUIRED] - the identifier of the service for which the ticket was issued
     * @param string|null $pgtUrl   [OPTIONAL] - the URL of the proxy callback
     *
     * @return \SimpleSAML\Module\casserver\Http\XmlResponse
     */
    public function proxyValidate(
        Request $request,
        #[MapQueryParameter] ?string $TARGET = null,
        #[MapQueryParameter] bool $renew = false,
        #[MapQueryParameter] ?string $ticket = null,
        #[MapQueryParameter] ?string $service = null,
        #[MapQueryParameter] ?string $pgtUrl = null,
    ): XmlResponse {
        return $this->validate(
            request: $request,
            method:  'proxyValidate',
            renew:   $renew,
            target:  $TARGET,
            ticket:  $ticket,
            service: $service,
            pgtUrl:  $pgtUrl,
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
