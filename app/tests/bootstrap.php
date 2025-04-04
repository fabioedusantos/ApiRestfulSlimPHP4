<?php

use Dotenv\Dotenv;

date_default_timezone_set('America/Sao_Paulo');

// Carrega variáveis de ambiente da pasta store
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../store');
$dotenv->load();