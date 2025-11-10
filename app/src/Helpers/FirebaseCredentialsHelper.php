<?php

namespace App\Helpers;

use App\Exceptions\InternalServerErrorException;
use Throwable;

/**
 * Classe responsável pela obtenção das credenciais do Firebase.
 *
 * Esta classe auxilia na leitura e decodificação do arquivo de chave de conta de serviço do Firebase,
 * necessário para autenticar e interagir com os serviços do Firebase.
 */
class FirebaseCredentialsHelper
{
    /**
     * Obtém as credenciais de serviço do Firebase.
     *
     * Este método localiza o arquivo de chave do Firebase a partir do caminho configurado
     * na variável de ambiente `FIREBASE_KEY_PATH`, lê o arquivo e retorna as credenciais
     * como um array associativo. Caso o arquivo de chave não seja encontrado ou ocorra
     * algum erro durante a leitura, uma exceção será lançada.
     *
     * @return array As credenciais de autenticação do Firebase.
     *
     * @throws InternalServerErrorException Se o caminho da chave não for configurado corretamente
     *         ou o arquivo não for encontrado.
     */
    public static function get(): array
    {
        try {
            $firebaseKeyPath = __DIR__ . '/../../../' . $_ENV['FIREBASE_KEY_PATH'];

            if (empty($_ENV['FIREBASE_KEY_PATH']) || !file_exists($firebaseKeyPath)) {
                throw new InternalServerErrorException('[Firebase] Chave de conta de serviço não definida.');
            }

            // Decodifica o JSON da chave inline
            $credentialsArray = file_get_contents($firebaseKeyPath);
            return json_decode($credentialsArray, true);
        } catch (Throwable $e) {
            throw new InternalServerErrorException('[Firebase] Erro ao obter credencial: ' . $e->getMessage());
        }
    }
}