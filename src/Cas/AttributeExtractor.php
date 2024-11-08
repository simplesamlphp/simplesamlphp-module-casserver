<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Cas;

use SimpleSAML\Auth;
use SimpleSAML\Auth\ProcessingChain;
use SimpleSAML\Configuration;
use SimpleSAML\Error\Exception;
use SimpleSAML\Error\NoState;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\Factories\ProcessingChainFactory;

/**
 * Extract the user and any mapped attributes from the AuthSource attributes
 */
class AttributeExtractor
{
    /** @var Configuration */
    private readonly Configuration $casconfig;

    /** @var ProcessingChainFactory  */
    private readonly ProcessingChainFactory $processingChainFactory;

    public function __construct(
        Configuration $casconfig,
        ProcessingChainFactory $processingChainFactory
    ) {
        $this->casconfig = $casconfig;
        $this->processingChainFactory = $processingChainFactory;
    }

    /**
     * Determine the user and any CAS attributes based on the attributes from the
     * authsource and the CAS configuration.
     *
     * The result is an array
     * [
     *   'user' => 'user_value',
     *   'attributes' => [
     *    // any attributes
     * ]
     *
     * If no CAS attributes are configured, then the attributes' array is empty
     *
     * @param   array|null  $state
     *
     * @return array
     * @throws Exception
     */
    public function extractUserAndAttributes(?array $state): array
    {
        if ($this->casconfig->hasValue('authproc')) {
            $this->runAuthProcs($state);
        }

        // Get the attributes from the state
        $attributes = $state['Attributes'];

        $casUsernameAttribute = $this->casconfig->getOptionalValue('attrname', 'eduPersonPrincipalName');

        $userName = $attributes[$casUsernameAttribute][0];
        if (empty($userName)) {
            throw new \Exception("No cas user defined for attribute $casUsernameAttribute");
        }

        if ($this->casconfig->getOptionalValue('attributes', true)) {
            $attributesToTransfer = $this->casconfig->getOptionalValue('attributes_to_transfer', []);

            if (sizeof($attributesToTransfer) > 0) {
                $casAttributes = [];

                foreach ($attributesToTransfer as $key) {
                    if (\array_key_exists($key, $attributes)) {
                        $casAttributes[$key] = $attributes[$key];
                    }
                }
            } else {
                $casAttributes = $attributes;
            }
        } else {
            $casAttributes = [];
        }

        return [
            'user' => $userName,
            'attributes' => $casAttributes,
        ];
    }

    /**
     * Run authproc filters with the processing chain
     * Creating the ProcessingChain require metadata.
     * - For the idp metadata use the OIDC issuer as the entityId (and the authprocs from the main config file)
     * - For the sp metadata use the client id as the entityId (and don’t set authprocs).
     *
     * @param   array  $state
     *
     * @return void
     * @throws Exception
     * @throws Error\UnserializableException
     * @throws \Exception
     */
    protected function runAuthProcs(array &$state): void
    {
        $filters = $this->casconfig->getOptionalArray('authproc', []);
        $idpMetadata = [
            'entityid' => $state['Source']['entityid'] ?? '',
            // ProcessChain needs to know the list of authproc filters we defined in module_oidc configuration
            'authproc' => $filters,
        ];
        $spMetadata = [
            'entityid' => $state['Destination']['entityid'] ?? '',
        ];

        $state['ReturnURL'] = Module::getModuleURL('casserver/login.php');
        $state['Destination'] = $spMetadata;
        $state['Source'] = $idpMetadata;

        $this->processingChainFactory->build($state)->processState($state);
    }

    /**
     * This is a wrapper around Auth/State::loadState that facilitates testing by
     * hiding the static method
     *
     * @param   array  $queryParameters
     *
     * @return array|null
     * @throws NoState
     */
    public function manageState(array $queryParameters): ?array
    {
        if (empty($queryParameters[ProcessingChain::AUTHPARAM])) {
            throw new NoState();
        }

        $stateId = (string)$queryParameters[ProcessingChain::AUTHPARAM];
        $state = $this->loadState($stateId, ProcessingChain::COMPLETED_STAGE);

        if (!empty($state['authSourceId'])) {
            $this->authSourceId = (string)$state['authSourceId'];
            unset($state['authSourceId']);
        }

        return $state;
    }

    /**
     * @param   string  $id
     * @param   string  $stage
     * @param   bool    $allowMissing
     *
     * @return array|null
     * @throws \SimpleSAML\Error\NoState
     */
    public function loadState(string $id, string $stage, bool $allowMissing = false): ?array
    {
        return $this->authState::loadState($id, $stage, $allowMissing);
    }
}
