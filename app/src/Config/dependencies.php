<?php

use App\Controllers\AuthController;
use App\Repositories\UserRepository;
use App\Services\AuthService;

use function DI\autowire;

return [
    PDO::class => function () {
        $host = $_ENV['MYSQL_HOST'] ?: '';
        $port = $_ENV['MYSQL_PORT'] ?: '';
        $dbname = $_ENV['MYSQL_DATABASE'] ?: '';
        $username = $_ENV['MYSQL_USER'] ?: '';
        $password = $_ENV['MYSQL_PASSWORD'] ?: '';

        // Criando a conexão PDO
        $pdo = new PDO("mysql:host=$host:$port;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    },


    UserRepository::class => autowire(UserRepository::class),

    AuthService::class => autowire(AuthService::class),

    AuthController::class => autowire(AuthController::class),
];