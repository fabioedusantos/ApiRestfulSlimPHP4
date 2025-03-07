<?php

use App\Controllers\BaseController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/', function (RouteCollectorProxy $group) {
        $group->get(
            '',
            [BaseController::class, "checkApi"]
        );
    });
};
