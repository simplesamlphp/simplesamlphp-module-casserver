<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module\casserver\Cas\Protocol\Cas20;
use SimpleSAML\Module\casserver\Cas\Protocol\SamlValidateResponder;
use SimpleSAML\Module\casserver\Cas\TicketValidator;
use SimpleSAML\Module\casserver\Controller\Traits\UrlTrait;
use SimpleSAML\Module\casserver\Http\XmlResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

#[AsController]
class Cas30Controller
{
    use UrlTrait;

    /** @var Logger */
    protected Logger $logger;

    /** @var Configuration */
    protected Configuration $casConfig;

    /** @var Cas20 */
    protected Cas20 $cas20Protocol;

    /** @var TicketValidator */
    protected TicketValidator $ticketValidator;

    /** @var SamlValidateResponder */
    protected SamlValidateResponder $validateResponder;

    /**
     * @param   Configuration       $sspConfig
     * @param   Configuration|null  $casConfig
     *
     * @throws \Exception
     */
    public function __construct(
        private readonly Configuration $sspConfig,
        Configuration $casConfig = null,
        TicketValidator $ticketValidator = null,
    ) {
        // We are using this work around in order to bypass Symfony's autowiring for cas configuration. Since
        // the configuration class is the same, it loads the ssp configuration twice. Still, we need the constructor
        // argument in order to facilitate testin.
        $this->casConfig = ($casConfig === null || $casConfig === $sspConfig)
            ? Configuration::getConfig('module_casserver.php') : $casConfig;
        $this->cas20Protocol = new Cas20($this->casConfig);
        $this->ticketValidator   = $ticketValidator ?? new TicketValidator($this->casConfig);
        $this->validateResponder = new SamlValidateResponder();
    }


    /**
     * POST /casserver/samlValidate?TARGET=
     * Host: cas.example.com
     * Content-Length: 491
     * Content-Type: text/xml
     *
     * @param   Request  $request
     * @param   string   $TARGET  URL encoded service identifier of the back-end service.
     *
     * @throws \RuntimeException
     * @return XmlResponse
     * @link https://apereo.github.io/cas/7.1.x/protocol/CAS-Protocol-Specification.html#42-samlvalidate-cas-30
     */
    public function samlValidate(
        Request $request,
        #[MapQueryParameter] string $TARGET,
    ): XmlResponse {
        // From SAML2\SOAP::receive()
        $postBody = $request->getContent();
        if (empty($postBody)) {
            throw new \RuntimeException('samlValidate expects a soap body.');
        }

        // SAML request values
        //
        // samlp:Request
        //  - RequestID [REQUIRED] - unique identifier for the request
        //  - IssueInstant [REQUIRED] - timestamp of the request
        // samlp:AssertionArtifact [REQUIRED] - the valid CAS Service

        $ticketParser = xml_parser_create();
        xml_parser_set_option($ticketParser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($ticketParser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($ticketParser, $postBody, $values, $tags);
        xml_parser_free($ticketParser);

        // Check for the required saml attributes
        $samlRequestAttributes = $values[ $tags['samlp:Request'][0] ]['attributes'];
        if (!isset($samlRequestAttributes['RequestID'])) {
            throw new \RuntimeException('Missing RequestID samlp:Request attribute.');
        } elseif (!isset($samlRequestAttributes['IssueInstant'])) {
            throw new \RuntimeException('Missing IssueInstant samlp:Request attribute.');
        }

        if (
            !isset($tags['samlp:AssertionArtifact'])
            || empty($values[$tags['samlp:AssertionArtifact'][0]]['value'])
        ) {
            throw new \RuntimeException('Missing ticketId in AssertionArtifact');
        }

        $ticketId = $values[$tags['samlp:AssertionArtifact'][0]]['value'];
        Logger::debug('samlvalidate: Checking ticket ' . $ticketId);

        try {
            // validateAndDeleteTicket might throw a CasException. In order to avoid third party modules
            // dependencies, we will catch and rethrow the Exception.
            $ticket = $this->ticketValidator->validateAndDeleteTicket($ticketId, $TARGET);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
        if (!\is_array($ticket)) {
            throw new \RuntimeException('Error loading ticket');
        }

        $response = $this->validateResponder->convertToSaml($ticket);
        $soap     = $this->validateResponder->wrapInSoap($response);

        return new XmlResponse(
            (string)$soap,
            Response::HTTP_OK,
        );
    }
}