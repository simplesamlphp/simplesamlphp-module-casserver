<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use SimpleSAML\Auth\ProcessingChain;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\AttributeExtractor;
use SimpleSAML\Module\casserver\Cas\Factories\ProcessingChainFactory;
use SimpleSAML\Module\casserver\Cas\Factories\TicketFactory;
use SimpleSAML\Module\casserver\Cas\Protocol\Cas20;
use SimpleSAML\Module\casserver\Cas\Protocol\SamlValidateResponder;
use SimpleSAML\Module\casserver\Cas\ServiceValidator;
use SimpleSAML\Module\casserver\Cas\Ticket\TicketStore;
use SimpleSAML\Module\casserver\Controller\Traits\UrlTrait;
use SimpleSAML\Module\casserver\Http\XmlResponse;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

#[AsController]
class LoginController
{
    use UrlTrait;

    /** @var Logger */
    protected Logger $logger;

    /** @var Configuration */
    protected Configuration $casConfig;

    /** @var TicketFactory */
    protected TicketFactory $ticketFactory;

    /** @var Simple  */
    protected Simple $authSource;

    /** @var Utils\HTTP */
    protected Utils\HTTP $httpUtils;

    /** @var Cas20 */
    protected Cas20 $cas20Protocol;

    /** @var TicketStore */
    protected TicketStore $ticketStore;

    /** @var ServiceValidator */
    protected ServiceValidator $serviceValidator;

    /** @var array */
    protected array $idpList;

    /** @var string|null */
    protected ?string $authProcId = null;

    protected array $postAuthUrlParameters = [];

    /** @var string[] */
    private const DEBUG_MODES = ['true', 'samlValidate'];

    /** @var AttributeExtractor */
    protected AttributeExtractor $attributeExtractor;

    /** @var SamlValidateResponder */
    private SamlValidateResponder $samlValidateResponder;

    /**
     * @param   Configuration       $sspConfig
     * @param   Configuration|null  $casConfig
     * @param   Simple|null         $source
     * @param   Utils\HTTP|null     $httpUtils
     *
     * @throws \Exception
     */
    public function __construct(
        private readonly Configuration $sspConfig,
        // Facilitate testing
        Configuration $casConfig = null,
        Simple $source = null,
        Utils\HTTP $httpUtils = null,
    ) {
        $this->casConfig = ($casConfig === null || $casConfig === $sspConfig)
            ? Configuration::getConfig('module_casserver.php') : $casConfig;
        // Saml Validate Responsder
        $this->samlValidateResponder = new SamlValidateResponder();
        // Service Validator needs the generic casserver configuration. We do not need
        $this->serviceValidator = new ServiceValidator($this->casConfig);
        $this->authSource = $source ?? new Simple($this->casConfig->getValue('authsource'));
        $this->httpUtils = $httpUtils ?? new Utils\HTTP();
    }

    /**
     *
     * @param   Request      $request
     * @param   bool         $renew
     * @param   bool         $gateway
     * @param   string|null  $service
     * @param   string|null  $TARGET
     * @param   string|null  $scope
     * @param   string|null  $language
     * @param   string|null  $entityId
     * @param   string|null  $debugMode
     * @param   string|null  $method
     *
     * @return RedirectResponse|XmlResponse|null
     * @throws NoState
     * @throws \Exception
     */
    public function login(
        Request $request,
        #[MapQueryParameter] bool $renew = false,
        #[MapQueryParameter] bool $gateway = false,
        #[MapQueryParameter] ?string $service = null,
        #[MapQueryParameter] ?string $TARGET = null,
        #[MapQueryParameter] ?string $scope = null,
        #[MapQueryParameter] ?string $language = null,
        #[MapQueryParameter] ?string $entityId = null,
        #[MapQueryParameter] ?string $debugMode = null,
        #[MapQueryParameter] ?string $method = null,
    ): RedirectResponse|XmlResponse|null {
        $forceAuthn = $renew;
        $serviceUrl = $service ?? $TARGET ?? null;
        $redirect = !(isset($method) && $method === 'POST');

        // Set initial configurations, or fail
        $this->handleServiceConfiguration($serviceUrl);
        $this->instantiateClassDependencies();
        $this->handleScope($scope);
        $this->handleLanguage($language);

        // Get the ticket from the session
        $session = $this->getSession();
        $sessionTicket = $this->ticketStore->getTicket($session->getSessionId());
        $sessionRenewId = $sessionTicket['renewId'] ?? null;
        $requestRenewId = $this->getRequestParam($request, 'renewId');
        // if this parameter is true, single sign-on will be bypassed and authentication will be enforced
        $requestForceAuthenticate = $forceAuthn && $sessionRenewId !== $requestRenewId;

        if ($request->query->has(ProcessingChain::AUTHPARAM)) {
            $this->authProcId = $request->query->get(ProcessingChain::AUTHPARAM);
        }

        // Construct the ReturnTo URL
        // This will be used to come back from the AuthSource login or from the Processing Chain
        $returnToUrl = $this->getReturnUrl($request, $sessionTicket);

        // Authenticate
        if (
            $requestForceAuthenticate || !$this->authSource->isAuthenticated()
        ) {
            $params = [
                'ForceAuthn' => $forceAuthn,
                'isPassive' => $gateway,
                'ReturnTo' => $returnToUrl,
            ];

            if (isset($entityId)) {
                $params['saml:idp'] = $entityId;
            }

            if (isset($this->idpList)) {
                if (sizeof($this->idpList) > 1) {
                    $params['saml:IDPList'] = $this->idpList;
                } else {
                    $params['saml:idp'] = $this->idpList[0];
                }
            }

            /*
             *  REDIRECT TO AUTHSOURCE LOGIN
             * */
            $this->authSource->login($params);
            // We should never get here.This is to facilitate testing.
            return null;
        }

        // We are Authenticated.

        $sessionExpiry = $this->authSource->getAuthData('Expire');
        // Create a new ticket if we do not have one alreday, or if we are in a forced Authentitcation mode
        if (!\is_array($sessionTicket) || $forceAuthn) {
            $sessionTicket = $this->ticketFactory->createSessionTicket($session->getSessionId(), $sessionExpiry);
            $this->ticketStore->addTicket($sessionTicket);
        }

        /*
         *  We are done. REDIRECT TO LOGGEDIN
         * */
        if (!isset($serviceUrl) && $this->authProcId === null) {
            $urlParameters = $this->httpUtils->addURLParameters(
                Module::getModuleURL('casserver/loggedIn'),
                $this->postAuthUrlParameters,
            );
            $this->httpUtils->redirectTrustedURL($urlParameters);
            // We should never get here.This is to facilitate testing.
            return null;
        }

        // Get the state.
        $state = $this->getState();
        $state['ReturnTo'] = $returnToUrl;
        if ($this->authProcId !== null) {
            $state[ProcessingChain::AUTHPARAM] = $this->authProcId;
        }
        // Attribute Handler
        $mappedAttributes = $this->attributeExtractor->extractUserAndAttributes($state);
        $serviceTicket = $this->ticketFactory->createServiceTicket([
                                                                 'service' => $serviceUrl,
                                                                 'forceAuthn' => $forceAuthn,
                                                                 'userName' => $mappedAttributes['user'],
                                                                 'attributes' => $mappedAttributes['attributes'],
                                                                 'proxies' => [],
                                                                 'sessionId' => $sessionTicket['id'],
                                                             ]);
        $this->ticketStore->addTicket($serviceTicket);

        // Check if we are in debug mode.
        if ($debugMode !== null && $this->casConfig->getOptionalBoolean('debugMode', false)) {
            return $this->handleDebugMode($request, $debugMode, $serviceTicket);
        }

        $ticketName = $this->calculateTicketName($service);
        $this->postAuthUrlParameters[$ticketName] = $serviceTicket['id'];

        // GET
        if ($redirect) {
            $this->httpUtils->redirectTrustedURL(
                $this->httpUtils->addURLParameters($serviceUrl, $this->postAuthUrlParameters),
            );
            // We should never get here.This is to facilitate testing.
            return null;
        }
        // POST
        $this->httpUtils->submitPOSTData($serviceUrl, $this->postAuthUrlParameters);
        // We should never get here.This is to facilitate testing.
        return null;
    }

    /**
     * @param   Request      $request
     * @param   string|null  $debugMode
     * @param   array        $serviceTicket
     *
     * @return XmlResponse
     */
    public function handleDebugMode(
        Request $request,
        ?string $debugMode,
        array $serviceTicket,
    ): XmlResponse {
        // Check if the debugMode is supported
        if (!\in_array($debugMode, self::DEBUG_MODES, true)) {
            return new XmlResponse(
                'invalid debug mode',
                Response::HTTP_BAD_REQUEST,
            );
        }

        if ($debugMode === 'true') {
            // Service validate CAS20
            return $this->validate(
                request: $request,
                method:  'serviceValidate',
                renew:   $request->get('renew', false),
                target:  $request->get('target'),
                ticket:  $serviceTicket['id'],
                service: $request->get('service'),
                pgtUrl:  $request->get('pgtUrl'),
            );
        }

        // samlValidate Mode
        $samlResponse = $this->samlValidateResponder->convertToSaml($serviceTicket);
        return new XmlResponse(
            (string)$this->samlValidateResponder->wrapInSoap($samlResponse),
            Response::HTTP_OK,
        );
    }

    /**
     * @return array|null
     * @throws \SimpleSAML\Error\NoState
     */
    public function getState(): ?array
    {
        // If we come from an authproc filter, we will load the state from the stateId.
        // If not, we will get the state from the AuthSource Data

        return $this->authProcId !== null ?
            $this->attributeExtractor->manageState($this->authProcId) :
            $this->authSource->getAuthDataArray();
    }

    /**
     * Construct the ticket name
     *
     * @param   string|null  $service
     *
     * @return string
     */
    public function calculateTicketName(?string $service): string
    {
        $defaultTicketName = $service !== null ? 'ticket' : 'SAMLart';
        return $this->casConfig->getOptionalValue('ticketName', $defaultTicketName);
    }

    /**
     * @param   Request     $request
     * @param   array|null  $sessionTicket
     *
     * @return string
     */
    public function getReturnUrl(Request $request, ?array $sessionTicket): string
    {
        // Parse the query parameters and return them in an array
        $query = $this->parseQueryParameters($request, $sessionTicket);
        // Construct the ReturnTo URL
        return $this->httpUtils->getSelfURLNoQuery() . '?' . http_build_query($query);
    }

    /**
     * @param   string|null  $serviceUrl
     *
     * @return void
     * @throws \RuntimeException
     */
    public function handleServiceConfiguration(?string $serviceUrl): void
    {
        if ($serviceUrl === null) {
            return;
        }
        $serviceCasConfig = $this->serviceValidator->checkServiceURL($this->sanitize($serviceUrl));
        if (!isset($serviceCasConfig)) {
            $message = 'Service parameter provided to CAS server is not listed as a legal service: [service] = ' .
                var_export($serviceUrl, true);
            Logger::debug('casserver:' . $message);

            throw new \RuntimeException($message);
        }

        // Override the cas configuration to use for this service
        $this->casConfig = $serviceCasConfig;
    }

    /**
     * @param   string|null  $language
     *
     * @return void
     */
    public function handleLanguage(?string $language): void
    {
        // If null, do nothing
        if ($language === null) {
            return;
        }

        $this->postAuthUrlParameters['language'] = $language;
    }

    /**
     * @param   string|null  $scope
     *
     * @return void
     * @throws \RuntimeException
     */
    public function handleScope(?string $scope): void
    {
        // If null, do nothing
        if ($scope === null) {
            return;
        }

        // Get the scopes from the configuration
        $scopes = $this->casConfig->getOptionalValue('scopes', []);

        // Fail
        if (!isset($scopes[$scope])) {
            $message = 'Scope parameter provided to CAS server is not listed as legal scope: [scope] = ' .
                var_export($scope, true);
            Logger::debug('casserver:' . $message);

            throw new \RuntimeException($message);
        }

        // Set the idplist from the scopes
        $this->idpList = $scopes[$scope];
    }

    /**
     * Get the Session
     *
     * @return Session|null
     * @throws \Exception
     */
    public function getSession(): ?Session
    {
        return Session::getSessionFromRequest();
    }

    /**
     * @return TicketStore
     */
    public function getTicketStore(): TicketStore
    {
        return $this->ticketStore;
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function instantiateClassDependencies(): void
    {
        $this->cas20Protocol = new Cas20($this->casConfig);

        /* Instantiate ticket factory */
        $this->ticketFactory = new TicketFactory($this->casConfig);
        /* Instantiate ticket store */
        $ticketStoreConfig = $this->casConfig->getOptionalValue(
            'ticketstore',
            ['class' => 'casserver:FileSystemTicketStore'],
        );
        $ticketStoreClass = Module::resolveClass($ticketStoreConfig['class'], 'Cas\Ticket');
        // Ticket Store
        $this->ticketStore = new $ticketStoreClass($this->casConfig);
        // Processing Chain Factory
        $processingChainFactory = new ProcessingChainFactory($this->casConfig);
        // Attribute Extractor
        $this->attributeExtractor = new AttributeExtractor($this->casConfig, $processingChainFactory);
    }
}
