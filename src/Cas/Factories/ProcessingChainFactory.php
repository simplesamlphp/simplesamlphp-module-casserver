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
        $idpMetadata = [
            'entityid' => $state['Source']['entityid'] ?? '',
            // ProcessChain needs to know the list of authproc filters we defined in casserver configuration
            'authproc' => $this->casconfig->getOptionalArray('authproc', []),
        ];
        $spMetadata = [
            'entityid' => $state['Destination']['entityid'] ?? '',
        ];

        return new ProcessingChain(
            $idpMetadata,
            $spMetadata,
            'casserver',
        );
    }
}