<?php

namespace App\Middlewares;

use App\Exceptions\UnauthorizedException;
use App\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Middleware para verificar se o usuário está ativo.
 *
 * Este middleware é responsável por verificar se o usuário está ativo no sistema.
 * Ele utiliza o repositório `UserRepository` para consultar o status do usuário e
 * lança uma exceção `UnauthorizedException` caso o usuário não exista ou não esteja ativo.
 *
 * @package App\Middlewares
 */
class UserActiveMiddleware
{
    public function __construct(private UserRepository $userRepository)
    {}

    public function __invoke(Request $request, Handler $handler): Response
    {
        $userId = $request->getAttribute('user')?->sub?->id ?? null;

        if (empty($userId) || !$this->userRepository->isActive($userId)) {
            throw new UnauthorizedException("Usuário inexistente ou inativo.");
        }

        return $handler->handle($request);
    }
}