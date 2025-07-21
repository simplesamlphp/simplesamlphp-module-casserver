<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Http;

use Symfony\Component\HttpFoundation\Response;

use function array_merge;

class XmlResponse extends Response
{
    /**
     * @param string|null $content
     * @param int $status
     * @param string[] $headers
     */
    public function __construct(?string $content = '', int $status = 200, array $headers = [])
    {
        parent::__construct($content, $status, array_merge($headers, [
            'Content-Type' => 'text/xml; charset=UTF-8',
        ]));
    }
}
