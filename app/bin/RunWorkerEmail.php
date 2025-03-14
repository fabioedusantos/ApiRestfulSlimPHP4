<?php

namespace App\Bin;

use App\Services\EmailService;
use App\Workers\EmailWorker;
use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/../vendor/autoload.php';

// Carrega variáveis de ambiente da pasta store
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../store');
$dotenv->load();

$phpMailer = new PHPMailer(true);
$notificationService = new EmailService($phpMailer);
$worker = new EmailWorker($notificationService);
$worker->run();