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
 * Frontend for login.php, validate.php, serviceValidate.php, logout.php, proxy and proxyValidate. It allows them to be
 * called as cas.php/login, cas.php/validate, cas.php/serviceValidate, cas.php/logout, cas.php/proxy and
 * cas.php/proxyValidate and is meant for clients like phpCAS which expects one configured prefix which it appends login,
 * validate, serviceValidate and logout to.
 *
 */

$validFunctions = [
    'login' => 'login',
    'validate' => 'validate',
    'serviceValidate' => 'serviceValidate',
    'logout' => 'logout',
    'proxy' => 'proxy',
    'proxyValidate' => 'proxyValidate',
];

$function = substr($_SERVER['PATH_INFO'], 1);

if (!isset($validFunctions[$function])) {
    $message = 'Not a valid function for cas.php.';

    \SimpleSAML\Logger::debug('casserver:'.$message);

    throw new \Exception($message);
}

include(dirname(__FILE__).'/'.strval($validFunctions[$function]).'.php');
