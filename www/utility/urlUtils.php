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

function checkServiceURL($service, array $legal_service_urls)
{
    foreach ($legal_service_urls as $legalUrl) {
        if (empty($legalUrl)) {
            SimpleSAML\Logger::warning("Ignoring empty CAS legal service url '$legalUrl'.");
            continue;
        }
        if (!ctype_alnum($legalUrl[0])) {
            // Probably a regex. Suppress errors incase the format is invalid
            $result =  @preg_match($legalUrl, $service);
            if ($result === 1) {
                return true;
            } elseif ($result === false) {
                SimpleSAML\Logger::warning("Invalid CAS legal service url '$legalUrl'. Error " . preg_last_error());
            }
        } elseif (strpos($service, $legalUrl) === 0) {
            return true;
        }
    }

    return false;
}

function sanitize($parameter)
{
    return preg_replace('/;jsessionid=.*[^?].*$/', '', preg_replace('/;jsessionid=.*[?]/', '?', urldecode($parameter)));
}
