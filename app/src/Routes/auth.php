<?php

use App\Controllers\AuthController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/auth', function (RouteCollectorProxy $group) {
        $group->post(
            '/signup',
            [AuthController::class, 'signUp']
        );

        $group->post(
            '/resend_confirm_email',
            [AuthController::class, 'resendConfirmEmail']
        );

        $group->post(
            '/check_reset_code',
            [AuthController::class, 'checkResetCode']
        );
    });
};