<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Cas;

use Exception;

/**
 * CasException correspond to different cas error codes
 * @package SimpleSAML\Module\casserver\Cas
 */
class CasException extends Exception
{
    /**
     * CasException constructor.
     *
     * @param string $casCode
     * @param string $message
     */
    public function __construct(
        protected string $casCode,
        string $message,
    ) {
        parent::__construct($message);
    }


    /**
     * @return string
     */
    public function getCasCode(): string
    {
        return $this->casCode;
    }
}
