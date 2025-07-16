<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use SimpleSAML\Configuration;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class LoggedOutController
{
    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /**
     * Controller constructor.
     *
     * It initializes the global configuration for the controllers implemented here.
     *
     * @param \SimpleSAML\Configuration|null $config
     *
     * @throws \Exception
     */
    public function __construct(?Configuration $config = null)
    {
        $this->config = $config ?? Configuration::getInstance();
    }

    /**
     * Show Log out view.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function main(Request $request): Response
    {
        $t = new Template($this->config, 'casserver:loggedOut.twig');

        if ($request->query->has('url')) {
            $t->data['url'] = $request->query->get('url');
        }

        return $t;
    }
}
