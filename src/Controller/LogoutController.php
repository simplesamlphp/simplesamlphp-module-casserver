<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module\casserver\Cas\Factories\TicketFactory;
use SimpleSAML\Module\casserver\Controller\Traits\UrlTrait;
use SimpleSAML\Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

class LogoutController
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

    // this could be any configured ticket store
    /** @var mixed */
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
        /* Instantiate ticket factory */
        $this->ticketFactory = new TicketFactory($this->casConfig);
        /* Instantiate ticket store */
        $ticketStoreConfig = $this->casConfig->getOptionalValue(
            'ticketstore',
            ['class' => 'casserver:FileSystemTicketStore'],
        );
        $ticketStoreClass = 'SimpleSAML\\Module\\casserver\\Cas\\Ticket\\'
            . explode(':', $ticketStoreConfig['class'])[1];
        $this->ticketStore = new $ticketStoreClass($this->casConfig);
        $this->authSource = new Simple($this->casConfig->getValue('authsource'));
    }

    /**
     *
     * @param   string|null  $url
     *
     * @return RedirectResponse|null
     */
    public function logout(
        #[MapQueryParameter] ?string $url = null,
    ): RedirectResponse|null {
        if (!$this->casConfig->getOptionalValue('enable_logout', false)) {
            $this->handleExceptionThrown('Logout not allowed');
        }

        // Skip Logout Page configuration
        $skipLogoutPage = $this->casConfig->getOptionalValue('skip_logout_page', false);

        if ($skipLogoutPage && $url === null) {
            $this->handleExceptionThrown('Required URL query parameter [url] not provided. (CAS Server)');
        }

        // Construct the logout redirect url
        $logoutRedirectUrl =  ($skipLogoutPage || $url === null) ? $url
            : $url . '?' . http_build_query(['url' => $url]);

        // Delete the ticket from the session
        $session = $this->getSession();
        if ($session !== null) {
            $this->ticketStore->deleteTicket($session->getSessionId());
        }

        // Redirect
        if (!$this->authSource->isAuthenticated()) {
            $this->redirect($logoutRedirectUrl);
        }

        // Logout and redirect
        $this->authSource->logout($logoutRedirectUrl);

        // We should never get here
        return null;
    }

    /**
     * @param   string  $message
     *
     * @return void
     */
    protected function handleExceptionThrown(string $message): void
    {
        Logger::debug('casserver:' . $message);
        throw new \RuntimeException($message);
    }

    /**
     * Get the Session
     *
     * @return Session|null
     */
    protected function getSession(): ?Session
    {
        return Session::getSession();
    }
}
