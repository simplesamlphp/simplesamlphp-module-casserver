<?php

declare(strict_types=1);

/*
 * This file is part of the simplesamlphp-module-casserver.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SimpleSAML\Module\casserver\Cas\Factories;

use SimpleSAML\Auth\ProcessingChain;
use SimpleSAML\Configuration;

class ProcessingChainFactory
{
    /** @var Configuration */
    private readonly Configuration $casconfig;

    public function __construct(
        Configuration $casconfig,
    ) {
        $this->casconfig = $casconfig;
    }

    /**
     * @codeCoverageIgnore
     * @throws \Exception
     */
    public function build(array $state): ProcessingChain
    {
        return new ProcessingChain(
            $state['Source'],
            $state['Destination'],
            'casserver',
        );
    }
}
