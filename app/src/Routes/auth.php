<?php

use App\Controllers\AuthController;
use App\Middlewares\JwtMiddleware;
use App\Middlewares\UserActiveMiddleware;
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

        $group->post(
            '/confirm_email',
            [AuthController::class, 'confirmEmail']
        );

        $group->post(
            '/forgot_password',
            [AuthController::class, 'forgotPassword']
        );

        $group->post(
            '/reset_password',
            [AuthController::class, 'resetPassword']
        );

        $group->post(
            '/login',
            [AuthController::class, 'login']
        );

        $group->post(
            '/refresh_token',
            [AuthController::class, 'refreshToken']
        );

        $group->get(
            '/is_logged_in',
            [AuthController::class, 'isLoggedIn']
        )->add(UserActiveMiddleware::class)->add(JwtMiddleware::class);

        //google
        $group->post(
            '/google/signup',
            [AuthController::class, 'signUpGoogle']
        );
    });
};