<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use RuntimeException;
use SimpleSAML\Auth\ProcessingChain;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\AttributeExtractor;
use SimpleSAML\Module\casserver\Cas\Factories\ProcessingChainFactory;
use SimpleSAML\Module\casserver\Cas\Factories\TicketFactory;
use SimpleSAML\Module\casserver\Cas\Protocol\Cas20;
use SimpleSAML\Module\casserver\Cas\Protocol\SamlValidateResponder;
use SimpleSAML\Module\casserver\Cas\ServiceValidator;
use SimpleSAML\Module\casserver\Cas\Ticket\TicketStore;
use SimpleSAML\Module\casserver\Controller\Traits\TicketValidatorTrait;
use SimpleSAML\Module\casserver\Controller\Traits\UrlTrait;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

use in_array;
use http_build_query;
use var_export;

#[AsController]
class LoginController
{
    use UrlTrait;
    use TicketValidatorTrait;

    /** @var \SimpleSAML\Logger */
    protected Logger $logger;

    /** @var \SimpleSAML\Configuration */
    protected Configuration $casConfig;

    /** @var \SimpleSAML\Module\casserver\Cas\Factories\TicketFactory */
    protected TicketFactory $ticketFactory;

    /** @var \SimpleSAML\Auth\Simple  */
    protected Simple $authSource;

    /** @var \SimpleSAML\Utils\HTTP */
    protected Utils\HTTP $httpUtils;

    /** @var \SimpleSAML\Module\casserver\Cas\Protocol\Cas20 */
    protected Cas20 $cas20Protocol;

    /** @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore */
    protected TicketStore $ticketStore;

    /** @var \SimpleSAML\Module\casserver\Cas\ServiceValidator */
    protected ServiceValidator $serviceValidator;

    /** @var string[] */
    protected array $idpList;

    /** @var string|null */
    protected ?string $authProcId = null;

    /** @var string[] */
    protected array $postAuthUrlParameters = [];

    /** @var string[] */
    private const DEBUG_MODES = ['true', 'samlValidate'];

    /** @var \SimpleSAML\Module\casserver\Cas\AttributeExtractor */
    protected AttributeExtractor $attributeExtractor;

    /** @var \SimpleSAML\Module\casserver\Cas\Protocol\SamlValidateResponder */
    private SamlValidateResponder $samlValidateResponder;

    /**
     * @param \SimpleSAML\Configuration $sspConfig
     * @param \SimpleSAML\Configuration|null $casConfig
     * @param \SimpleSAML\Auth\Simple|null $source
     * @param \SimpleSAML\Utils\HTTP|null $httpUtils
     *
     * @throws \Exception
     */
    public function __construct(
        private readonly Configuration $sspConfig,
        // Facilitate testing
        ?Configuration $casConfig = null,
        ?Simple $source = null,
        ?Utils\HTTP $httpUtils = null,
    ) {
        $this->casConfig = ($casConfig === null || $casConfig === $sspConfig)
            ? Configuration::getConfig('module_casserver.php') : $casConfig;
        // Saml Validate Responsder
        $this->samlValidateResponder = new SamlValidateResponder();
        // Service Validator needs the generic casserver configuration.
        $this->serviceValidator = new ServiceValidator($this->casConfig);
        $this->authSource = $source ?? new Simple($this->casConfig->getValue('authsource'));
        $this->httpUtils = $httpUtils ?? new Utils\HTTP();
    }

    /**
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param bool $renew
     * @param bool $gateway
     * @param string|null $service
     * @param string|null $TARGET  Query parameter name for "service" used by older CAS clients'
     * @param string|null $scope
     * @param string|null $language
     * @param string|null $entityId
     * @param string|null $debugMode
     * @param string|null $method
     *
     * @return \SimpleSAML\HTTP\RunnableResponse|\SimpleSAML\XHTML\Template
     * @throws \SimpleSAML\Error\ConfigurationError
     * @throws \SimpleSAML\Error\NoState
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
    ): RunnableResponse|Template {
        $forceAuthn = $renew;
        $serviceUrl = $service ?? $TARGET ?? null;
        $redirect = !(isset($method) && $method === 'POST');

        // Set initial configurations, or fail
        $this->handleServiceConfiguration($serviceUrl);

        // Instantiate the classes that rely on the override configuration.
        // We do not do this in the constructor since we do not have the correct values yet.
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
                if (count($this->idpList) > 1) {
                    $params['saml:IDPList'] = $this->idpList;
                } else {
                    $params['saml:idp'] = $this->idpList[0];
                }
            }

            /*
             *  REDIRECT TO AUTHSOURCE LOGIN
             * */
            return new RunnableResponse(
                [$this->authSource, 'login'],
                [$params],
            );
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
            $loggedInUrl = Module::getModuleURL('casserver/loggedIn');
            return new RunnableResponse(
                [$this->httpUtils, 'redirectTrustedURL'],
                [$loggedInUrl, $this->postAuthUrlParameters],
            );
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
            [$templateName, $statusCode, $DebugModeXmlString] = $this->handleDebugMode(
                $request,
                $debugMode,
                $serviceTicket,
            );
            $t = new Template($this->sspConfig, (string)$templateName);
            $t->data['debugMode'] = $debugMode === 'true' ? 'Default' : $debugMode;
            if (!str_contains('error', (string)$templateName)) {
                $t->data['DebugModeXml'] = $DebugModeXmlString;
            }
            $t->data['statusCode'] = $statusCode;
            // Return an HTML View that renders the result
            return $t;
        }

        $ticketName = $this->calculateTicketName($service);
        $this->postAuthUrlParameters[$ticketName] = $serviceTicket['id'];

        // GET
        if ($redirect) {
            return new RunnableResponse(
                [$this->httpUtils, 'redirectTrustedURL'],
                [$serviceUrl, $this->postAuthUrlParameters],
            );
        }

        // POST
        return new RunnableResponse(
            [$this->httpUtils, 'submitPOSTData'],
            [$serviceUrl, $this->postAuthUrlParameters],
        );
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string|null $debugMode
     * @param array $serviceTicket
     *
     * @return array []
     */
    public function handleDebugMode(
        Request $request,
        ?string $debugMode,
        array $serviceTicket,
    ): array {
        // Check if the debugMode is supported
        if (!in_array($debugMode, self::DEBUG_MODES, true)) {
            return ['casserver:error.twig', Response::HTTP_BAD_REQUEST, 'Invalid/Unsupported Debug Mode'];
        }

        if ($debugMode === 'true') {
            // Service validate CAS20
            $xmlResponse = $this->validate(
                request: $request,
                method:  'serviceValidate',
                renew:   $request->get('renew', false),
                target:  $request->get('target'),
                ticket:  $serviceTicket['id'],
                service: $request->get('service'),
                pgtUrl:  $request->get('pgtUrl'),
            );
            return ['casserver:validate.twig', $xmlResponse->getStatusCode(), $xmlResponse->getContent()];
        }

        // samlValidate Mode
        $samlResponse = $this->samlValidateResponder->convertToSaml($serviceTicket);
        return [
            'casserver:validate.twig',
            Response::HTTP_OK,
            (string)$this->samlValidateResponder->wrapInSoap($samlResponse),
        ];
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
     * @param string|null $service
     *
     * @return string
     */
    public function calculateTicketName(?string $service): string
    {
        $defaultTicketName = $service !== null ? 'ticket' : 'SAMLart';
        return $this->casConfig->getOptionalValue('ticketName', $defaultTicketName);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param array|null $sessionTicket
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
     * @param string|null $serviceUrl
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

            throw new RuntimeException($message);
        }

        // Override the cas configuration to use for this service
        $this->casConfig = $serviceCasConfig;
    }

    /**
     * @param string|null $language
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
     * @param string|null $scope
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

            throw new RuntimeException($message);
        }

        // Set the idplist from the scopes
        $this->idpList = $scopes[$scope];
    }

    /**
     * Get the Session
     *
     * @return \SimpleSAML\Session|null
     * @throws \Exception
     */
    public function getSession(): ?Session
    {
        return Session::getSessionFromRequest();
    }

    /**
     * @return \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore
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
