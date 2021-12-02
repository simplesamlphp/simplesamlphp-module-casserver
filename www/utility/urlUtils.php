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

use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\ServiceValidator;
use SimpleSAML\Module\casserver\Cas\TicketValidator;

/**
 * @deprecated
 * @see ServiceValidator
 * @param string $service
 * @param array $legal_service_urls
 * @return bool
 */
function checkServiceURL(string $service, array $legal_service_urls): bool
{
    //delegate to ServiceValidator until all references to this can be cleaned up
    $config = Configuration::loadFromArray(['legal_service_urls' => $legal_service_urls]);
    $serviceValidator = new ServiceValidator($config);
    return $serviceValidator->checkServiceURL($service) !== null;
}


/**
 * @param string $parameter
 * @return string
 */
function sanitize(string $parameter): string
{
    return TicketValidator::sanitize($parameter);
}
