<?php

use Slim\App;

return function (App $app) {
    (require __DIR__ . '/base.php')($app);             // Rotas base da aplicação (Hello World)
    (require __DIR__ . '/auth.php')($app);             // Rotas de autenticação
    (require __DIR__ . '/users.php')($app);            // Rotas de usuários
    (require __DIR__ . '/notifications.php')($app);    // Rotas de notificações
};