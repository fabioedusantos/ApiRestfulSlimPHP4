<?php

namespace App\Helpers;

use App\Exceptions\InternalServerErrorException;
use Exception;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\Client\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\CreateAssessmentRequest;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason;

/**
 * Classe auxiliar para verificar tokens do Google reCAPTCHA.
 *
 * A classe `GoogleRecaptchaHelper` é responsável por integrar a verificação de tokens do reCAPTCHA
 * usando a API do Google Recaptcha Enterprise. Ela valida se o token do reCAPTCHA enviado é válido
 * e possui um risco de pontuação aceitável.
 */
class GoogleRecaptchaHelper
{
    /**
     * Obtém o caminho para as credenciais do reCAPTCHA.
     *
     * Este método recupera o caminho da chave de conta de serviço do Google reCAPTCHA, que é necessário
     * para autenticar a requisição da API.
     *
     * @return string Caminho do arquivo de credenciais.
     *
     * @throws InternalServerErrorException Se o caminho da chave não for configurado corretamente
     *         ou o arquivo não for encontrado.
     */
    private static function getCredentialPath(): string
    {
        $recaptchaKeyPath = __DIR__ . '/../../' . $_ENV['RECAPTCHA_GOOGLE_KEY_PATH'];

        if (empty($_ENV['RECAPTCHA_GOOGLE_KEY_PATH']) || !file_exists($recaptchaKeyPath)) {
            throw new InternalServerErrorException('[Recaptcha] Chave de conta de serviço não definida.');
        }

        return $recaptchaKeyPath;
    }

    /**
     * Obtém as credenciais de autenticação do reCAPTCHA.
     *
     * Este método lê e decodifica as credenciais do arquivo de chave JSON fornecido pelo Google.
     *
     * @return array As credenciais do Google reCAPTCHA.
     *
     * @throws InternalServerErrorException Se ocorrer erro ao ler as credenciais.
     */
    private static function getCredentials(): array
    {
        try {
            $recaptchaKeyPath = self::getCredentialPath();

            // Decodifica o JSON da chave inline
            $credentialsArray = file_get_contents($recaptchaKeyPath);
            return json_decode($credentialsArray, true);
        } catch (Throwable $e) {
            throw new InternalServerErrorException(
                '[Recaptcha] Erro ao obter credencial: ' . $e->getMessage()
            );
        }
    }

    /**
     * Verifica se o token do reCAPTCHA é válido.
     *
     * Este método valida um token do reCAPTCHA recebido do cliente, verificando sua integridade
     * e pontuação de risco. Se o token for válido e a pontuação de risco for suficiente, retorna `true`.
     *
     * @param string $token O token do reCAPTCHA enviado pelo cliente.
     * @param string $siteKey A chave do site do reCAPTCHA configurado.
     *
     * @return bool `true` se o token for válido, `false` caso contrário.
     *
     * @throws InternalServerErrorException Se ocorrer erro ao verificar o token.
     */
    public static function isValid(
        string $token,
        string $siteKey
    ): bool
    {
        if (empty($token) || empty($siteKey)) {
            return false;
        }

        //bypass em modo dev para o Recaptcha
        if (isset($_ENV['APP_ENV']) && mb_strtoupper($_ENV['APP_ENV']) == "DEV") {
            return true;
        }

        // Cria um arquivo temporário com o conteúdo da chave
        $recaptchaKeyPath = self::getCredentialPath();
        putenv("GOOGLE_APPLICATION_CREDENTIALS={$recaptchaKeyPath}");

        // Extrai project_id do JSON
        $keyData = self::getCredentials();
        $projectId = $keyData['project_id'] ?? null;
        if (!$projectId) {
            throw new InternalServerErrorException('[Recaptcha] Project ID não encontrado no JSON.');
        }

        try {
            $client = new RecaptchaEnterpriseServiceClient();
            $projectName = $client->projectName($projectId);

            $event = (new Event())
                ->setSiteKey($siteKey)
                ->setToken($token);

            $assessment = (new Assessment())->setEvent($event);
            $request = (new CreateAssessmentRequest())
                ->setParent($projectName)
                ->setAssessment($assessment);

            $response = $client->createAssessment($request);

            $props = $response->getTokenProperties();

            if (!$props->getValid()) {
                throw new InternalServerErrorException(
                    '[Recaptcha] Token inválido: ' . InvalidReason::name($props->getInvalidReason())
                );
            }

            return $response->getRiskAnalysis()->getScore() >= 0.5;
        } catch (Exception $e) {
            throw new InternalServerErrorException('[Recaptcha] Erro: ' . $e->getMessage());
        }
    }
}