<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Codebooks;

enum LegacyRoutesEnum: string
{
    case LegacyCas = 'cas.php';
    case LegacyLoggedIn = 'loggedIn.php';
    case LegacyLoggedOut = 'loggedOut.php';
    case LegacyLogin = 'login.php';
    case LegacyLogout = 'logout.php';
    case LegacyProxy = 'proxy.php';
    case LegacyProxyValidate = 'proxyValidate.php';
    case LegacySamlValidate = 'samlValidate.php';
    case LegacyServiceValidate = 'serviceValidate.php';
    case LegacyValidate = 'validate.php';
}
