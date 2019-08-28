<?php

namespace SimpleSAML\Module\casserver\Cas;

/**
 * CasException correspond to different cas error codes
 * @package SimpleSAML\Module\casserver\Cas
 */
class CasException extends \Exception
{
    // For list of cas codes see https://apereo.github.io/cas/5.2.x/protocol/CAS-Protocol-Specification.html#253-error-codes
    const INVALID_TICKET = 'INVALID_TICKET';
    const INVALID_SERVICE = 'INVALID_SERVICE';

    /** @var string */
    private $casCode;

    /**
     * CasException constructor.
     * @param string $casCode
     * @param string $message
     */
    public function __construct($casCode, $message)
    {
        parent::__construct($message);
        $this->casCode = $casCode;
    }

    /**
     * @return string
     */
    public function getCasCode()
    {
        return $this->casCode;
    }
}
