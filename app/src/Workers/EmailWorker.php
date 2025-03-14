<?php

namespace App\Workers;

use App\Services\EmailService;
use Exception;
use Predis\Client;
use Predis\Connection\ConnectionException;

class EmailWorker
{
    private $redis;

    public function __construct(private EmailService $notificationService)
    {
        $this->connect();
    }

    private function connect(): void
    {
        $this->redis = new Client([
            'scheme' => 'tcp',
            'host' => 'redis',
            'port' => 6379,
        ]);
    }

    public function run(): void
    {
        echo "Email Worker iniciado...\n";

        while (true) {
            try {
                $job = $this->redis->blpop('email_queue', 0);

                if (!empty($job)) {
                    $data = json_decode($job[1], true);

                    echo "Início do envio de email {$data['type']} para {$data['email']}...\n";
                    switch ($data['type']) {
                        case 'accountConfirmation':
                            $this->notificationService->sendAccountConfirmation(
                                $data['email'],
                                $data['nome'],
                                $data['codigo'],
                                $data['tempoDuracao']
                            );
                            break;
                        case 'passwordReset':
                            $this->notificationService->sendPasswordReset(
                                $data['email'],
                                $data['nome'],
                                $data['codigo'],
                                $data['tempoDuracao']
                            );
                            break;
                        default:
                            throw new Exception("Tipo de envio inesperado: {$data['type']}");
                    }

                    echo "Email {$data['type']} enviado para {$data['email']}...\n";
                }
            } catch (ConnectionException $e) {
                echo "Conexão perdida com Redis. Tentando reconectar...\n";
                sleep(2); // espera antes de tentar reconectar
                $this->connect();
            } catch (\Exception $e) {
                echo "Erro inesperado: {$e->getMessage()}\n";
                sleep(2);
            }
        }
    }
}