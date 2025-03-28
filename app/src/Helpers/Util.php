<?php

namespace App\Helpers;

use Random\RandomException;
use Throwable;

/**
 * Classe Util
 *
 * A classe `Util` oferece uma coleção de métodos úteis para manipulação de variáveis de ambiente,
 * separação de nome e sobrenome, e conversão de imagens para BLOBs.
 */
class Util
{
    /**
     * Obtém o valor de uma variável de ambiente.
     *
     * Este método retorna o valor de uma variável de ambiente configurada, ou `null` se a variável não estiver definida.
     *
     * @param string $alias O nome da variável de ambiente a ser recuperada.
     *
     * @return mixed O valor da variável de ambiente ou `null` se não estiver definida.
     */
    public static function getEnv(string $alias): mixed
    {
        return isset($_ENV[$alias]) ? $_ENV[$alias] : null;
    }

    /**
     * Define o valor de uma variável de ambiente.
     *
     * Este método permite configurar o valor de uma variável de ambiente.
     *
     * @param string $alias O nome da variável de ambiente a ser definida.
     * @param mixed $value O valor a ser atribuído à variável de ambiente.
     */
    public static function setEnv(
        string $alias,
        mixed $value
    ): void {
        $_ENV[$alias] = $value;
    }

    /**
     * Gera uma string contendo um número aleatório com a quantidade de dígitos especificada.
     *
     * A função utiliza `random_int()` para gerar um número aleatório seguro e, em seguida,
     * preenche o resultado com zeros à esquerda (`0`) para atingir o comprimento total
     * definido por `$qtdDigitos`. O número máximo aleatório gerado internamente é 999999.
     *
     * @static
     * @param int $qtdDigitos O número desejado de dígitos para a string resultante.
     * @return string A string contendo o número aleatório preenchido com zeros.
     * @throws RandomException Se uma fonte de entropia adequada não puder ser encontrada (via random_int).
     */
    public static function generateRandomNumber(int $qtdDigitos): string
    {
        return str_pad(
            random_int(0, 999999),
            $qtdDigitos,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Converte uma URL de imagem para um BLOB.
     *
     * Este método baixa o conteúdo de uma URL e a converte em uma string binária (BLOB). Ele verifica se a URL
     * é válida e se o conteúdo é uma imagem, retornando o conteúdo da imagem ou `null` caso haja algum erro.
     *
     * @param string $url A URL da imagem a ser convertida.
     *
     * @return string|null O conteúdo da imagem em formato binário (BLOB) ou `null` em caso de falha.
     */
    public static function urlFotoToBlob(string $url): ?string
    {
        try {
            // Inicializa cURL
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => ($_ENV['APP_ENV'] != 'DEV'), //voltar para true para validar certificados, vamos deixar assim em DEV temporariamente para urls inseguras
                CURLOPT_HEADER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
            ]);

            $conteudo = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception("Erro ao baixar imagem: $error");
            }

            // Verifica se foi sucesso e se é imagem válida
            if ($httpCode === 200 && str_starts_with($contentType, 'image/')) {
                return $conteudo; // Retorna a imagem como string binária (BLOB)
            }

            return null;
        } catch (Throwable $e) {
            throw new \Exception("Erro ao baixar imagem: " . $e->getMessage());
        }
    }
}