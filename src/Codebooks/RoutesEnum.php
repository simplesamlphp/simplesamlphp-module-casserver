<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Codebooks;

enum RoutesEnum: string
{
    case Cas = 'cas';
    case LoggedIn = 'loggedIn';
    case LoggedOut = 'loggedOut';
    case Login = 'login';
    case Logout = 'logout';
    case Proxy = 'proxy';
    case ProxyValidate = 'proxyValidate';
    case SamlValidate = 'samlValidate';
    case ServiceValidate = 'serviceValidate';
    case Validate = 'validate';
}
