<?php

/**
 * CasServer module routes file.
 */

declare(strict_types=1);

use SimpleSAML\Module\casserver\Codebooks\RoutesEnum;
use SimpleSAML\Module\casserver\Codebooks\LegacyRoutesEnum;
use SimpleSAML\Module\casserver\Controller\Cas10Controller;
use SimpleSAML\Module\casserver\Controller\LogoutController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/** @psalm-suppress InvalidArgument */
return static function (RoutingConfigurator $routes): void {

    // We support both the new and the legacy routes
    // New Routes
    $routes->add(RoutesEnum::Validate->name, RoutesEnum::Validate->value)
        ->controller([Cas10Controller::class, 'validate']);
    $routes->add(RoutesEnum::Validate->name, RoutesEnum::Logout->value)
        ->controller([LogoutController::class, 'logout']);

    // Legacy Routes
    $routes->add(LegacyRoutesEnum::LegacyValidate->name, LegacyRoutesEnum::LegacyValidate->value)
        ->controller([Cas10Controller::class, 'validate']);
    $routes->add(LegacyRoutesEnum::LegacyValidate->name, LegacyRoutesEnum::LegacyLogout->value)
        ->controller([LogoutController::class, 'logout']);
};
