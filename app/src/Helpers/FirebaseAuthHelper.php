<?php

namespace App\Helpers;

use App\Exceptions\InternalServerErrorException;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Factory;
use Throwable;

/**
 * Helper responsável pela interação com a autenticação do Firebase.
 *
 * A classe `FirebaseAuthHelper` fornece métodos para verificar tokens de autenticação do Firebase
 * e recuperar informações de usuários autenticados a partir desses tokens.
 */
class FirebaseAuthHelper
{
    /**
     * Verifica um token de ID do Firebase e retorna o objeto UserRecord correspondente.
     *
     * Este método valida o token de ID recebido e, se o token for válido, retorna um objeto `UserRecord`
     * contendo as informações do usuário associado ao token. Caso contrário, lança uma exceção de erro.
     *
     * @param string $firebaseIdToken O token de ID do Firebase a ser verificado.
     *
     * @return UserRecord|null O objeto `UserRecord` com os dados do usuário ou `null` caso o token seja inválido.
     *
     * @throws InternalServerErrorException Se o token não for válido ou ocorrer algum erro inesperado.
     */
    public static function verificarIdToken(string $firebaseIdToken): ?UserRecord
    {
        if (empty($firebaseIdToken)) {
            return null;
        }

        try {
            $credentialsArray = FirebaseCredentialsHelper::get();
            $factory = (new Factory)->withServiceAccount($credentialsArray);
            $auth = $factory->createAuth();

            // Verifica o token
            $verifiedIdToken = $auth->verifyIdToken($firebaseIdToken);
            $uid = $verifiedIdToken->claims()->get('sub');

            return $auth->getUser($uid);
        } catch (FailedToVerifyToken  $e) {
            throw new \Exception('[Firebase] Token inválido: ' . $e->getMessage());
        } catch (Throwable $e) {
            throw new \Exception('[Firebase] Erro inesperado: ' . $e->getMessage());
        }
    }
}