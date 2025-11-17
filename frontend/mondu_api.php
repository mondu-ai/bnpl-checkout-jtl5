<?php declare(strict_types=1);

use Plugin\MonduPayment\Src\Services\RoutesService;

/** @var PluginInterface $oPlugin */
$routes = new RoutesService();
$routes->frontEndRoutes($oPlugin);

