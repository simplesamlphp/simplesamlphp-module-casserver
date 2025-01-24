<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use SimpleSAML\Configuration;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class LoggedInController
{
    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /**
     * Controller constructor.
     *
     * It initializes the global configuration for the controllers implemented here and injects the session service.
     *
     * @param   Configuration|null  $config
     *
     * @throws \Exception
     */
    public function __construct(Configuration $config = null)
    {
        $this->config = $config ?? Configuration::getInstance();
    }

    /**
     * Show Log out view.
     *
     * @param   Request  $request
     * @return Response
     * @throws \Exception
     */
    public function main(Request $request): Response
    {
        session_cache_limiter('nocache');
        return new Template($this->config, 'casserver:loggedIn.twig');
    }
}
