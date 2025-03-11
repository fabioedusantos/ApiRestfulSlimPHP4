<?php

namespace App\Handlers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\ResponseEmitter;

/**
 * Classe ShutdownHandler
 *
 * O ShutdownHandler lida com erros fatais que ocorrem durante a execução do aplicativo.
 * Ele é chamado quando um erro de execução (fatal) ocorre após o processamento da requisição.
 * O objetivo é capturar os erros e gerar uma resposta adequada para o cliente, com base no tipo de erro ocorrido.
 */
class ShutdownHandler
{
    private $request;
    private $errorHandler;
    private $displayErrorDetails;

    public function __construct(Request $request, HttpErrorHandler $errorHandler, bool $displayErrorDetails)
    {
        $this->request = $request;
        $this->errorHandler = $errorHandler;
        $this->displayErrorDetails = $displayErrorDetails;
    }

    /**
     * Método invocado no encerramento do script para lidar com erros fatais.
     *
     * O método captura qualquer erro fatal gerado, verifica seu tipo e, em seguida,
     * utiliza o manipulador de erros para gerar uma resposta adequada. O erro é
     * emitido ao cliente, com base nas configurações e se os detalhes do erro
     * devem ser exibidos.
     * */
    public function __invoke(): void
    {
        $error = error_get_last();
        if ($error) {
            $errorFile = $error['file'];
            $errorLine = $error['line'];
            $errorMessage = $error['message'];
            $errorType = $error['type'];
            $message = 'An error while processing your request. Please try again later.';

            if ($this->displayErrorDetails) {
                switch ($errorType) {
                    case E_USER_ERROR:
                        $message = "FATAL ERROR: {$errorMessage}. ";
                        $message .= " on line {$errorLine} in file {$errorFile}.";
                        break;

                    case E_USER_WARNING:
                        $message = "WARNING: {$errorMessage}";
                        break;

                    case E_USER_NOTICE:
                        $message = "NOTICE: {$errorMessage}";
                        break;

                    default:
                        $message = "ERROR: {$errorMessage}";
                        $message .= " on line {$errorLine} in file {$errorFile}.";
                        break;
                }
            }

            $exception = new HttpInternalServerErrorException($this->request, $message);
            $response = $this->errorHandler->__invoke(
                $this->request,
                $exception,
                $this->displayErrorDetails,
                false,
                false
            );

            if (ob_get_length()) {
                ob_clean();
            }

            $responseEmitter = new ResponseEmitter();
            $responseEmitter->emit($response);
        }
    }
}