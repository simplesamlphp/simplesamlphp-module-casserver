<?php

/*
 *    simpleSAMLphp-casserver is a CAS 1.0 and 2.0 compliant CAS server in the form of a simpleSAMLphp module
 *
 *    Copyright (C) 2013  Bjorn R. Jensen
 *
 *    This library is free software; you can redistribute it and/or
 *    modify it under the terms of the GNU Lesser General Public
 *    License as published by the Free Software Foundation; either
 *    version 2.1 of the License, or (at your option) any later version.
 *
 *    This library is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 *    Lesser General Public License for more details.
 *
 *    You should have received a copy of the GNU Lesser General Public
 *    License along with this library; if not, write to the Free Software
 *    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Cas\Protocol;

use DateTimeImmutable;
use SimpleSAML\CAS\XML\cas\Attributes;
use SimpleSAML\CAS\XML\cas\AuthenticationDate;
use SimpleSAML\CAS\XML\cas\AuthenticationFailure;
use SimpleSAML\CAS\XML\cas\AuthenticationSuccess;
use SimpleSAML\CAS\XML\cas\IsFromNewLogin;
use SimpleSAML\CAS\XML\cas\LongTermAuthenticationRequestTokenUsed;
use SimpleSAML\CAS\XML\cas\ProxyFailure;
use SimpleSAML\CAS\XML\cas\ProxyGrantingTicket;
use SimpleSAML\CAS\XML\cas\ProxySuccess;
use SimpleSAML\CAS\XML\cas\ProxyTicket;
use SimpleSAML\CAS\XML\cas\ServiceResponse;
use SimpleSAML\CAS\XML\cas\User;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\XML\Chunk;
use SimpleSAML\XML\DOMDocumentFactory;

use function base64_encode;
use function count;
use function filter_var;
use function is_null;
use function is_string;
use function str_replace;

class Cas20
{
    /** @var bool $sendAttributes */
    private bool $sendAttributes;

    /** @var bool $base64EncodeAttributes */
    private bool $base64EncodeAttributes;

    /** @var string|null $base64IndicatorAttribute */
    private ?string $base64IndicatorAttribute;

    /** @var array $attributes */
    private array $attributes = [];

    /** @var string|null $proxyGrantingTicketIOU */
    private ?string $proxyGrantingTicketIOU = null;


    /**
     * @param \SimpleSAML\Configuration $config
     */
    public function __construct(Configuration $config)
    {
        $this->sendAttributes = $config->getOptionalValue('attributes', false);
        $this->base64EncodeAttributes = $config->getOptionalValue('base64attributes', false);
        $this->base64IndicatorAttribute = $config->getOptionalValue('base64_attributes_indicator_attribute', null);
    }


    /**
     * @param array $attributes
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }


    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }


    /**
     * @param string $proxyGrantingTicketIOU
     */
    public function setProxyGrantingTicketIOU(string $proxyGrantingTicketIOU): void
    {
        $this->proxyGrantingTicketIOU = $proxyGrantingTicketIOU;
    }


    /**
     * @return string|null
     */
    public function getProxyGrantingTicketIOU(): ?string
    {
        return $this->proxyGrantingTicketIOU;
    }


    /**
     * @param string $username
     * @return \SimpleSAML\CAS\XML\cas\ServiceResponse
     */
    public function getValidateSuccessResponse(string $username): ServiceResponse
    {
        $user = new User($username);

        $proxyGrantingTicket = null;
        if (is_string($this->proxyGrantingTicketIOU)) {
            $proxyGrantingTicket = new ProxyGrantingTicket($this->proxyGrantingTicketIOU);
        }

        $attr = [];
        if ($this->sendAttributes && count($this->attributes) > 0) {
            foreach ($this->attributes as $name => $values) {
                // Fix the most common cause of invalid XML elements
                $_name = str_replace(':', '_', $name);
                if ($this->isValidXmlName($_name) === true) {
                    foreach ($values as $value) {
                        $attr[] = $this->generateCas20Attribute($_name, $value);
                    }
                } else {
                    Logger::warning("DOMException creating attribute '$_name'. Continuing without attribute'");
                }
            }

            if (!is_null($this->base64IndicatorAttribute)) {
                $attr[] = $this->generateCas20Attribute(
                    $this->base64IndicatorAttribute,
                    $this->base64EncodeAttributes ? "true" : "false",
                );
            }
        }

        $attributes = new Attributes(
            new AuthenticationDate(new DateTimeImmutable('now')),
            new LongTermAuthenticationRequestTokenUsed('true'),
            new IsFromNewLogin('true'),
            $attr,
        );

        $authenticationSuccess = new AuthenticationSuccess($user, $attributes, $proxyGrantingTicket);
        $serviceResponse = new ServiceResponse($authenticationSuccess);

        return $serviceResponse;
    }


    /**
     * @param string $errorCode
     * @param string $explanation
     * @return \SimpleSAML\CAS\XML\cas\ServiceResponse
     */
    public function getValidateFailureResponse(string $errorCode, string $explanation): ServiceResponse
    {
        $authenticationFailure = new AuthenticationFailure($explanation, $errorCode);
        $serviceResponse = new ServiceResponse($authenticationFailure);

        return $serviceResponse;
    }


    /**
     * @param string $proxyTicketId
     * @return \SimpleSAML\CAS\XML\cas\ServiceResponse
     */
    public function getProxySuccessResponse(string $proxyTicketId): ServiceResponse
    {
        $proxyTicket = new ProxyTicket($proxyTicketId);
        $proxySuccess = new ProxySuccess($proxyTicket);
        $serviceResponse = new ServiceResponse($proxySuccess);

        return $serviceResponse;
    }


    /**
     * @param string $errorCode
     * @param string $explanation
     * @return \SimpleSAML\CAS\XML\cas\ServiceResponse
     */
    public function getProxyFailureResponse(string $errorCode, string $explanation): ServiceResponse
    {
        $proxyFailure = new ProxyFailure($explanation, $errorCode);
        $serviceResponse = new ServiceResponse($proxyFailure);

        return $serviceResponse;
    }


    /**
     * @param string $attributeName
     * @param string $attributeValue
     * @return \SimpleSAML\XML\Chunk
     */
    private function generateCas20Attribute(
        string $attributeName,
        string $attributeValue,
    ): Chunk {
        $xmlDocument = DOMDocumentFactory::create();

        $attributeValue = $this->base64EncodeAttributes ? base64_encode($attributeValue) : $attributeValue;
        $attributeElement = $xmlDocument->createElementNS(Attributes::NS, 'cas:' . $attributeName, $attributeValue);

        return new Chunk($attributeElement);
    }


    /**
     * XML element names have a lot of rules and not every SAML attribute name can be converted.
     * Ref: https://www.w3.org/TR/REC-xml/#NT-NameChar
     * https://stackoverflow.com/q/2519845/54396
     * must only start with letter or underscore
     * cannot start with 'xml' (or maybe it can - stackoverflow commenters don't agree)
     * cannot contain a ':' since those are for namespaces
     * cannot contains space
     * can only  contain letters, digits, hyphens, underscores, and periods
     * @param string $name The attribute name to be used as an element
     * @return bool true if $name would make a valid xml element.
     */
    private function isValidXmlName(string $name): bool
    {
        return filter_var(
            $name,
            FILTER_VALIDATE_REGEXP,
            ['options' => ['regexp' => '/^[a-zA-Z_][\w.-]*$/']],
        ) !== false;
    }
}
