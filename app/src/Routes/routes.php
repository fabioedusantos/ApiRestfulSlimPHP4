<?php

use Slim\App;

return function (App $app) {
    (require __DIR__ . '/base.php')($app);             // Rotas base da aplicação (Hello World)
};