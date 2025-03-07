<?php

use App\Controllers\AuthController;
use App\Controllers\NotificationController;
use App\Controllers\UserController;
use App\Middlewares\JwtMiddleware;
use App\Middlewares\UserActiveMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Services\UserService;
use PHPMailer\PHPMailer\PHPMailer;
use Predis\Client;

use function DI\autowire;

return [
    //injeções de depêndencias supimpas!
];