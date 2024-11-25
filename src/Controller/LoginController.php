<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use SimpleSAML\Auth\ProcessingChain;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\Locale\Language;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\Factories\ProcessingChainFactory;
use SimpleSAML\Module\casserver\Cas\Factories\TicketFactory;
use SimpleSAML\Module\casserver\Cas\Protocol\SamlValidateResponder;
use SimpleSAML\Module\casserver\Cas\ServiceValidator;
use SimpleSAML\Module\casserver\Controller\Traits\UrlTrait;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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

    /** @var Configuration */
    protected Configuration $sspConfig;

    /** @var TicketFactory */
    protected TicketFactory $ticketFactory;

    /** @var Simple  */
    protected Simple $authSource;

    /** @var Utils\HTTP */
    protected Utils\HTTP $httpUtils;

    // this could be any configured ticket store
    /** @var mixed */
    protected mixed $ticketStore;

    /** @var ServiceValidator */
    protected ServiceValidator $serviceValidator;

    /** @var array */
    protected array $idpList;

    /** @var string */
    protected string $authProcId;

    /** @var AttributeExtractor */
    protected AttributeExtractor $attributeExtractor;

    /**
     * @param   Configuration|null  $casConfig
     * @param   Simple|null         $source
     * @param   SspContainer|null   $httpUtils
     *
     * @throws \Exception
     */
    public function __construct(
        Configuration $sspConfig = null,
        // Facilitate testing
        Configuration $casConfig = null,
        Simple $source = null,
        Utils\HTTP $httpUtils = null,
    ) {
        $this->sspConfig = $sspConfig ?? Configuration::getInstance();
        $this->casConfig = $casConfig ?? Configuration::getConfig('module_casserver.php');
        $this->authSource = $source ?? new Simple($this->casConfig->getValue('authsource'));
        $this->httpUtils = $httpUtils ?? new Utils\HTTP();

        $this->serviceValidator = new ServiceValidator($this->casConfig);
        /* Instantiate ticket factory */
        $this->ticketFactory = new TicketFactory($this->casConfig);
        /* Instantiate ticket store */
        $ticketStoreConfig = $this->casConfig->getOptionalValue(
            'ticketstore',
            ['class' => 'casserver:FileSystemTicketStore'],
        );
        $ticketStoreClass = 'SimpleSAML\\Module\\casserver\\Cas\\Ticket\\'
            . explode(':', $ticketStoreConfig['class'])[1];
        // Ticket Store
        $this->ticketStore = new $ticketStoreClass($this->casConfig);
        // Processing Chain Factory
        $processingChainFactory = new ProcessingChainFactory($this->casconfig);
        // Attribute Extractor
        $this->attributeExtractor = new AttributeExtractor($this->casconfig, $processingChainFactory);
    }

    /**
     *
     * @param   Request      $request
     * @param   bool         $renew
     * @param   bool         $gateway
     * @param   string|null  $service
     * @param   string|null  $scope
     * @param   string|null  $language
     * @param   string|null  $entityId
     *
     * @return RedirectResponse|null
     * @throws \Exception
     */
    public function login(
        Request $request,
        #[MapQueryParameter] bool $renew = false,
        #[MapQueryParameter] bool $gateway = false,
        #[MapQueryParameter] string $service = null,
        #[MapQueryParameter] string $scope = null,
        #[MapQueryParameter] string $language = null,
        #[MapQueryParameter] string $entityId = null,
        #[MapQueryParameter] string $debugMode = null,
    ): RedirectResponse|null {
        $this->handleServiceConfiguration($service);
        $this->handleScope($scope);
        $this->handleLanguage($language);

        if ($request->query->has(ProcessingChain::AUTHPARAM)) {
            $this->authProcId = $request->query->get(ProcessingChain::AUTHPARAM);
        }

        // Get the ticket from the session
        $session = Session::getSessionFromRequest();
        $sessionTicket = $this->ticketStore->getTicket($session->getSessionId());

        // Construct the ticket name
        $defaultTicketName = isset($service) ? 'ticket' : 'SAMLart';
        $ticketName = $this->casconfig->getOptionalValue('ticketName', $defaultTicketName);


        $sessionRenewId = $sessionTicket ? $sessionTicket['renewId'] : null;
    }

    public function handleDebugMode(
        Request $request,
        ?string $debugMode,
        string $ticketName,
        array $serviceTicket,
    ): void {
        // Check if the debugMode is supported
        if (!\in_array($debugMode, ['true', 'samlValidate'], true)) {
            return;
        }

        if ($debugMode === 'true') {
            // Service validate CAS20
            $this->httpUtils->redirectTrustedURL(
                Module::getModuleURL('/cas/serviceValidate.php'),
                [ ...$request->getQueryParams(), $ticketName => $serviceTicket['id'] ],
            );
        }

        // samlValidate Mode
        $samlValidate = new SamlValidateResponder();
        $samlResponse = $samlValidate->convertToSaml($serviceTicket);
        $soap = $samlValidate->wrapInSoap($samlResponse);
        echo '<pre>' . htmlspecialchars((string)$soap) . '</pre>';
    }

    /**
     * @param   array|null  $sessionTicket
     *
     * @return string
     */
    public function getReturnUrl(?array $sessionTicket): string
    {
        // Parse the query parameters and return them in an array
        $query = parseQueryParameters($sessionTicket);
        // Construct the ReturnTo URL
        return $this->httpUtils->getSelfURLNoQuery() . '?' . http_build_query($query);
    }

    /**
     * @param   string|null  $service
     *
     * @return void
     * @throws \Exception
     */
    public function handleServiceConfiguration(?string $service): void
    {
        // todo: Check request objec the TARGET
        $serviceUrl = $service ?? $_GET['TARGET'] ?? null;
        if ($serviceUrl === null) {
            return;
        }
        $serviceCasConfig = $this->serviceValidator->checkServiceURL(sanitize($serviceUrl));
        if (!isset($serviceCasConfig)) {
            $message = 'Service parameter provided to CAS server is not listed as a legal service: [service] = ' .
                var_export($serviceUrl, true);
            Logger::debug('casserver:' . $message);

            throw new \Exception($message);
        }

        // Override the cas configuration to use for this service
        $this->casconfig = $serviceCasConfig;
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

        Language::setLanguageCookie($language);
    }

    /**
     * @param   string|null  $scope
     *
     * @return void
     * @throws \Exception
     */
    public function handleScope(?string $scope): void
    {
        // If null, do nothing
        if ($scope === null) {
            return;
        }

        // Get the scopes from the configuration
        $scopes = $this->casconfig->getOptionalValue('scopes', []);

        // Fail
        if (!isset($scopes[$scope])) {
            $message = 'Scope parameter provided to CAS server is not listed as legal scope: [scope] = ' .
                var_export($_GET['scope'], true);
            Logger::debug('casserver:' . $message);

            throw new \Exception($message);
        }

        // Set the idplist from the scopes
        $this->idpList = $scopes[$scope];
    }
}
