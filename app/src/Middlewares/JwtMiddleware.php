<?php

namespace App\Middlewares;

use App\Exceptions\UnauthorizedException;
use App\Helpers\EnvHelper;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Middleware para autenticação via JWT (JSON Web Token).
 *
 * O middleware `JwtMiddleware` é responsável por verificar o token JWT no cabeçalho `Authorization`
 * da requisição e validá-lo. Caso o token seja válido, ele permite o acesso ao próximo middleware ou
 * rota, caso contrário, lança uma exceção de autorização.
 */
class JwtMiddleware
{
    private string $jwtSecret;
    public function __construct()
    {
        $this->jwtSecret = EnvHelper::getEnv("JWT_SECRET") ?? '';
    }

    public function __invoke(Request $request, Handler $handler): Response
    {
        $authHeader = $request->getHeader('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader[0], $matches)) {
            throw new UnauthorizedException("Jwt não autorizado.");
        }

        $token = $matches[1];

        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $request = $request->withAttribute('user', $decoded);
        } catch (\Exception $e) {
            throw new UnauthorizedException("Jwt não autorizado.");
        }

        return $handler->handle($request);
    }
}
