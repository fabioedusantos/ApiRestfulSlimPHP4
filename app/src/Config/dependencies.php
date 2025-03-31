<?php

use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Helpers\Util;
use App\Middlewares\JwtMiddleware;
use App\Middlewares\UserActiveMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;

use App\Services\UserService;
use Predis\Client;

use function DI\autowire;

return [
    PDO::class => function () {
        $host = Util::getenv('MYSQL_HOST') ?: '';
        $port = Util::getenv('MYSQL_PORT') ?: '';
        $dbname = Util::getenv('MYSQL_DATABASE') ?: '';
        $username = Util::getenv('MYSQL_USER') ?: '';
        $password = Util::getenv('MYSQL_PASSWORD') ?: '';

        // Criando a conexão PDO
        $pdo = new PDO("mysql:host=$host:$port;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    },

    //REDIS - EMAIL
    Client::class => new Client([
        'scheme' => 'tcp',
        'host' => 'redis',
        'port' => 6379,
    ]),

    JwtMiddleware::class => autowire(JwtMiddleware::class),
    UserActiveMiddleware::class => autowire(UserActiveMiddleware::class),

    UserRepository::class => autowire(UserRepository::class),

    AuthService::class => autowire(AuthService::class),
    UserService::class => autowire(UserService::class),

    AuthController::class => autowire(AuthController::class),
    UserController::class => autowire(UserController::class),
];