<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use RuntimeException;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\Factories\TicketFactory;
use SimpleSAML\Module\casserver\Cas\ServiceValidator;
use SimpleSAML\Module\casserver\Cas\Ticket\TicketStore;
use SimpleSAML\Module\casserver\Controller\Traits\UrlTrait;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

#[AsController]
class LogoutController
{
    use UrlTrait;

    /** @var \SimpleSAML\Logger */
    protected Logger $logger;

    /** @var \SimpleSAML\Configuration */
    protected Configuration $casConfig;

    /** @var \SimpleSAML\Module\casserver\Cas\Factories\TicketFactory */
    protected TicketFactory $ticketFactory;

    /** @var \SimpleSAML\Auth\Simple */
    protected Simple $authSource;

    /** @var \SimpleSAML\Utils\HTTP */
    protected Utils\HTTP $httpUtils;

    /** @var \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore */
    protected TicketStore $ticketStore;
    private ServiceValidator $serviceValidator;


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
        // We are using this work around in order to bypass Symfony's autowiring for cas configuration. Since
        // the configuration class is the same, it loads the ssp configuration twice. Still, we need the constructor
        // argument in order to facilitate testin.
        $this->casConfig = ($casConfig === null || $casConfig === $sspConfig)
            ? Configuration::getConfig('module_casserver.php') : $casConfig;
        $this->serviceValidator = new ServiceValidator($this->casConfig);

        $this->authSource = $source ?? new Simple($this->casConfig->getValue('authsource'));
        $this->httpUtils = $httpUtils ?? new Utils\HTTP();

        /* Instantiate ticket factory */
        $this->ticketFactory = new TicketFactory($this->casConfig);

        /* Instantiate ticket store */
        $ticketStoreConfig = $this->casConfig->getOptionalValue(
            'ticketstore',
            ['class' => 'casserver:FileSystemTicketStore'],
        );
        $ticketStoreClass = Module::resolveClass($ticketStoreConfig['class'], 'Cas\Ticket');
        $this->ticketStore = new $ticketStoreClass($this->casConfig);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string|null $url
     *
     * @return \SimpleSAML\XHTML\Template|\SimpleSAML\HTTP\RunnableResponse
     */
    public function logout(
        Request $request,
        #[MapQueryParameter] ?string $url = null,
        #[MapQueryParameter] ?string $service = null,
    ): Template|RunnableResponse {
        if (!$this->casConfig->getOptionalValue('enable_logout', false)) {
            $this->handleExceptionThrown('Logout not allowed');
        }

        // note: casv3 says to ignore the casv2 url parameter, however deployments will see a mix of cas v2 and
        // cas v3 clients so we support both.  casv3 makes a query parameter optional
        $isCasV3 = empty($url);
        $url = $isCasV3 ? $service : $url;

        // Validate the return $url is valid
        if (!is_null($url)) {
            $isValidReturnUrl = !is_null($this->serviceValidator->checkServiceURL($this->sanitize($url)));
            if (!$isValidReturnUrl) {
                try {
                    $url = $this->httpUtils->checkURLAllowed($url);
                    $isValidReturnUrl = true;
                } catch (\Exception $e) {
                    Logger::info('Invalid cas logout url ' . $e->getMessage());
                    $isValidReturnUrl = false;
                }
            }
            if (!$isValidReturnUrl) {
                // Protocol does not define behavior if invalid logout url sent
                // act like no url sent and show logout page
                Logger::info("Invalid logout url '$url'. Ignoring");
                $url = null;
            }
        }

        // Skip Logout Page configuration
        $skipLogoutPage = !is_null($url) && ($isCasV3 || $this->casConfig->getOptionalValue('skip_logout_page', false));


        // Delete the ticket from the session
        $session = $this->getSession();
        if ($session !== null) {
            $this->ticketStore->deleteTicket($session->getSessionId());
        }

        if ($this->authSource->isAuthenticated()) {
            // Logout and come back here to handle the logout
            return new RunnableResponse(
                [$this->authSource, 'logout'],
                [$this->httpUtils->getSelfURL()],
            );
        } elseif ($skipLogoutPage) {
            $params = [];
            return new RunnableResponse([$this->httpUtils, 'redirectTrustedURL'], [$url, $params]);
        } else {
            $t = new Template($this->sspConfig, 'casserver:loggedOut.twig');
            if ($url) {
                $t->data['url'] = $url;
            }
            return $t;
        }
    }

    /**
     * @return \SimpleSAML\Module\casserver\Cas\Ticket\TicketStore
     */
    public function getTicketStore(): TicketStore
    {
        return $this->ticketStore;
    }

    /**
     * @param string $message
     *
     * @return void
     */
    protected function handleExceptionThrown(string $message): void
    {
        Logger::debug('casserver:' . $message);
        throw new RuntimeException($message);
    }

    /**
     * Get the Session
     *
     * @return \SimpleSAML\Session|null
     */
    protected function getSession(): ?Session
    {
        return Session::getSession();
    }
}
