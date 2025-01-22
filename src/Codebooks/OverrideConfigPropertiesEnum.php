<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Codebooks;

enum OverrideConfigPropertiesEnum: string
{
    case Attributes = 'attributes';
    case Attrname = 'attrname';
    case AttributesToTransfer = 'attributes_to_transfer';
    case Authproc = 'authproc';
    case ServiceTicketExpireTime = 'service_ticket_expire_time';
}
