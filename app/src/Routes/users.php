<?php

use App\Controllers\UserController;
use App\Middlewares\JwtMiddleware;
use App\Middlewares\UserActiveMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/users', function (RouteCollectorProxy $group) {
        $group->get(
            '/me',
            [UserController::class, 'get']
        );
    })->add(UserActiveMiddleware::class)->add(JwtMiddleware::class);
};
