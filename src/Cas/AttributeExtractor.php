<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Cas;

use SimpleSAML\Auth\ProcessingChain;
use SimpleSAML\Auth\State;
use SimpleSAML\Configuration;
use SimpleSAML\Error\NoState;
use SimpleSAML\Module;
use SimpleSAML\Module\casserver\Cas\Factories\ProcessingChainFactory;
use SimpleSAML\Utils;

/**
 * Extract the user and any mapped attributes from the AuthSource attributes
 */
class AttributeExtractor
{
    /** @var \SimpleSAML\Auth\State */
    private State $authState;

    /** @var \SimpleSAML\Utils\HTTP */
    protected Utils\HTTP $httpUtils;

    /**
     * ID of the Authentication Source used during authn.
     */
    private ?string $authSourceId = null;


    public function __construct(
        protected Configuration $casconfig,
        protected ProcessingChainFactory $processingChainFactory,
        // Facilitate testing
        ?Utils\HTTP $httpUtils = null,
    ) {
        $this->authState = new State();
        $this->httpUtils = $httpUtils ?? new Utils\HTTP();
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
     * @throws \Exception
     */
    public function extractUserAndAttributes(?array $state): array
    {
        if (
            !isset($state[ProcessingChain::AUTHPARAM])
            && $this->casconfig->hasValue('authproc')
        ) {
            $this->runAuthProcs($state);
        }

        // Get the attributes from the state
        $attributes = $state['Attributes'];

        $casUsernameAttribute = $this->casconfig->getOptionalValue('attrname', 'eduPersonPrincipalName');

        $userName = $attributes[$casUsernameAttribute][0];
        if (empty($userName)) {
            throw new \Exception("No cas user defined for attribute $casUsernameAttribute");
        }

        $casAttributes = [];
        if ($this->casconfig->getOptionalValue('attributes', true)) {
            $attributesToTransfer = $this->casconfig->getOptionalValue('attributes_to_transfer', []);

            if (sizeof($attributesToTransfer) > 0) {
                foreach ($attributesToTransfer as $key) {
                    if (\array_key_exists($key, $attributes)) {
                        $casAttributes[$key] = $attributes[$key];
                    }
                }
            } else {
                $casAttributes = $attributes;
            }
        }

        return [
            'user' => $userName,
            'attributes' => $casAttributes,
        ];
    }


    /**
     * Run authproc filters with the processing chain
     * Creating the ProcessingChain require metadata.
     * - For the idp metadata use the configured casserver entity ID as the entityId (and the authprocs
     *   from the casserver config file)
     * - For the sp metadata use the CAS service URL as the entityId (and don’t set authprocs).
     *
     * @param   array  $state
     *
     * @return void
     * @throws \SimpleSAML\Error\UnserializableException
     * @throws \Exception
     */
    protected function runAuthProcs(array &$state): void
    {
        $filters = $this->casconfig->getOptionalArray('authproc', []);

        // Preserve any other metadata the caller may have seeded, and only take over the keys we own.
        $idpMetadata = \is_array($state['Source'] ?? null) ? $state['Source'] : [];
        $spMetadata = \is_array($state['Destination'] ?? null) ? $state['Destination'] : [];

        $idpMetadata['entityid'] = $this->resolveIdpEntityId($idpMetadata['entityid'] ?? null);
        // ProcessChain needs to know the list of authproc filters we defined in the casserver configuration
        $idpMetadata['authproc'] = $filters;
        // The CAS service URL is the closest analogue to an SP entity ID. It is seeded into the state by
        // the login controller, which has already validated it against the legal service URLs.
        $spMetadata['entityid'] = (string)($spMetadata['entityid'] ?? '');
        // The authproc filters are owned by the casserver configuration alone. Anything the caller may
        // have seeded here is a control key rather than metadata, and must not add filters to the chain.
        unset($spMetadata['authproc']);

        // Get the ReturnTo from the state or fallback to the login page
        $state['ReturnURL'] = $state['ReturnTo'] ?? Module::getModuleURL('casserver/login.php');
        $state['Destination'] = $spMetadata;
        $state['Source'] = $idpMetadata;

        $this->processingChainFactory->build($state)->processState($state);
    }


    /**
     * Resolve the entity ID that casserver presents to the authproc filters as the authenticating IdP.
     *
     * casserver has no entity ID of its own, so the value is resolved in the following order:
     * whatever the state already carries, then the 'idp_entity_id' configuration option, and finally
     * the SimpleSAMLphp base URL. Without this, filters that key off the authenticating entity (for
     * example federation logging or per-entity authorization) would silently observe an empty string.
     *
     * @param   mixed  $stateEntityId  The entity ID already present in the state, if any.
     *
     * @return string
     * @throws \Exception
     */
    private function resolveIdpEntityId(mixed $stateEntityId): string
    {
        if (\is_string($stateEntityId) && $stateEntityId !== '') {
            return $stateEntityId;
        }

        $configuredEntityId = $this->casconfig->getOptionalString('idp_entity_id', null);
        if (!empty($configuredEntityId)) {
            return $configuredEntityId;
        }

        return $this->httpUtils->getBaseURL();
    }


    /**
     * This is a wrapper around Auth/State::loadState that facilitates testing by
     * hiding the static method
     *
     * @param   string  $stateId
     *
     * @return array|null
     * @throws \SimpleSAML\Error\NoState
     */
    public function manageState(string $stateId): ?array
    {
        if (empty($stateId)) {
            throw new NoState();
        }

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
    protected function loadState(string $id, string $stage, bool $allowMissing = false): ?array
    {
        return $this->authState::loadState($id, $stage, $allowMissing);
    }
}
