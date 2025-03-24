<?php

namespace App\Helpers;

use Firebase\JWT\JWT;

/**
 * Classe auxiliar para manipulação de JSON Web Tokens (JWT).
 *
 * Esta classe fornece métodos para gerar tokens JWT para autenticação de usuários,
 * tanto para acesso normal quanto para tokens de refresh.
 */
class JwtHelper
{
    /**
     * Gera um token de acesso JWT.
     *
     * Este método gera um token JWT com um tempo de expiração configurado (padrão 1 hora)
     * e inclui o ID do usuário no payload do token. O token é assinado com o segredo fornecido.
     *
     * @param string $userId O ID do usuário para incluir no payload do token.
     * @param string $secret O segredo usado para assinar o token.
     * @param int $expiration O tempo de expiração do token em segundos (padrão: 3600 segundos).
     *
     * @return string O token JWT gerado.
     *
     * @throws \Exception Se houver algum erro ao gerar o token.
     */
    public static function generateToken(
        string $userId,
        string $secret,
        int $expiration = 3600
    ): string // 1 HORA
    {
        $now = time();
        $payload = [
            'iat' => $now,
            'exp' => $now + $expiration,
            'sub' => ["id" => $userId]
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * Gera um token de refresh JWT.
     *
     * Este método gera um token JWT com um tempo de expiração configurado (padrão 30 dias)
     * e inclui o ID do usuário no payload. O token de refresh é assinado com o segredo fornecido.
     *
     * @param string $userId O ID do usuário para incluir no payload do token.
     * @param string $secret O segredo usado para assinar o token.
     * @param int $expiration O tempo de expiração do token de refresh em segundos (padrão: 2592000 segundos).
     *
     * @return string O token de refresh JWT gerado.
     *
     * @throws \Exception Se houver algum erro ao gerar o token.
     */
    public static function generateRefreshToken(
        string $userId,
        string $secret,
        int $expiration = 2592000
    ): string // 30 dias
    {
        $now = time();
        $payload = [
            'iat' => $now,
            'exp' => $now + $expiration,
            'sub' => ["id" => $userId]
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }
}