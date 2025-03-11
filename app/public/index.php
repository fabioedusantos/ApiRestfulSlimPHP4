<?php

use App\Handlers\HttpErrorHandler;
use App\Handlers\ShutdownHandler;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

date_default_timezone_set('America/Sao_Paulo'); // Exemplo para horário de Brasília

require __DIR__ . '/../vendor/autoload.php';

// Carregando .env na raiz
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../store');
$dotenv->load();

// Criação do container de dependências usando PHP-DI
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(require __DIR__ . '/../src/Config/dependencies.php');

// Adicionando as definições de dependências ao container
$container = $containerBuilder->build();
AppFactory::setContainer($container);

// Criação da aplicação
$app = AppFactory::create();

// Gerenciamento de erros da aplicação
$displayErrorDetails = true;
$callableResolver = $app->getCallableResolver();
$responseFactory = $app->getResponseFactory();
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();
$errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);
$shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrorDetails);
register_shutdown_function($shutdownHandler);
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, false, false);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// Carregar as configurações
//$config = require __DIR__ . '/config/config.php';
//$settings = require __DIR__ . '/config/settings.php';
//$settings($app); // Passar o objeto $app para a função anônima

//configuração de subpasta para que funcione no servidor
//não esquecer de ajustar o .htaccess
$app->setBasePath($_ENV['BASE_PATH']); //subpasta no servidor (vazio se for no root)

//cors
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', 'https://localhost')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

// Middlewares para tratar requisições OPTIONS separadamente
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

// Registrar rotas
(require __DIR__ . '/../src/Routes/routes.php')($app);

$app->run();