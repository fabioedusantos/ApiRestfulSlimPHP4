<?php

namespace App\Helpers;

/**
 * Helper para conversão de imagens (URL e base64) em BLOBs binários.
 */
class PhotoHelper
{
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

    /**
     * Converte uma string base64 com prefixo "data:image/..." para um BLOB binário.
     *
     * Este método recebe uma string base64 de uma imagem e a converte em uma string binária (BLOB),
     * verificando se a base64 é válida e se é uma imagem compatível (JPEG, PNG ou GIF).
     *
     * @param string $photoBlob A string base64 da imagem a ser convertida.
     *
     * @return string|null O conteúdo da imagem em formato binário (BLOB) ou `null` em caso de falha.
     *
     * @throws Exception Se a base64 for inválida ou o conteúdo não for uma imagem válida.
     */
    public static function photoBase64ToBlob(string $photoBlob): ?string
    {
        // Decodifica
        $binaryData = base64_decode($photoBlob, true);
        if ($binaryData === false) {
            throw new \Exception("O arquivo de imagem não é válido ou está corrompido.");
        }

        // Verifica se é uma imagem válida
        $finfo = finfo_open();
        $mimeType = finfo_buffer($finfo, $binaryData, FILEINFO_MIME_TYPE);
        finfo_close($finfo);

        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'])) {
            throw new \Exception("Conteúdo não é uma imagem válida (os tipos aceitos são: .jpeg, .png e ,.gif).");
        }

        return $binaryData;
    }
}