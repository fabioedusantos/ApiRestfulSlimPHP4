<?php

use App\Controllers\NotificationController;
use App\Middlewares\JwtMiddleware;
use App\Middlewares\UserActiveMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/notifications', function (RouteCollectorProxy $group) {
        $group->get(
            '/push_channels',
            [NotificationController::class, 'getPushChannels']
        );
        $group->post(
            '/send_push_to_device',
            [NotificationController::class, 'sendPushToDevice']
        );
        $group->post(
            '/send_push_to_all',
            [NotificationController::class, 'sendPushToAll']
        );
    })->add(UserActiveMiddleware::class)->add(JwtMiddleware::class);
};
