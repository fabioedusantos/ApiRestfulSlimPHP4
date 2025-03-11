<?php

namespace App\Handlers;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerErrorException;
use App\Exceptions\UnauthorizedException;
use App\Helpers\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Slim\Handlers\ErrorHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Classe personalizada para manipulação de erros HTTP no SlimPHP.
 *
 * Esta classe estende o manipulador de erros do Slim e oferece um tratamento específico
 * para diferentes tipos de exceções, como BadRequest, Unauthorized, e InternalServerError.
 */
class HttpErrorHandler extends ErrorHandler
{
    /**
     * Método responsável por processar os erros e retornar uma resposta HTTP apropriada.
     *
     * O método verifica o tipo de exceção lançada e chama a resposta JSON correspondente
     * usando a classe JsonResponse para enviar uma mensagem estruturada ao cliente.
     *
     * @return ResponseInterface A resposta HTTP com o erro adequado em formato JSON.
     */
    protected function respond(): ResponseInterface
    {
        $exceptionMap = [
            BadRequestException::class => JsonResponse::class . '::badRequest',
            UnauthorizedException::class => JsonResponse::class . '::unauthorized',
            InternalServerErrorException::class => JsonResponse::class . '::internalServerError'
        ];

        foreach ($exceptionMap as $exceptionClass => $method) {
            if ($this->exception instanceof $exceptionClass) {
                $response = new SlimResponse();
                return call_user_func($method, $response, $this->exception->getMessage());
            }
        }

        // Caso a exceção não esteja mapeada, chama o método da classe mãe
        return parent::respond();
    }
}