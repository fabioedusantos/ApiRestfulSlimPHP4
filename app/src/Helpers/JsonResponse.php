<?php

namespace App\Helpers;

use Slim\Psr7\Response;

/**
 * JsonResponse
 *
 * UtilHelper para padronizar respostas JSON em APIs Slim.
 *
 * Essa classe oferece métodos estáticos para retornar respostas padronizadas
 * com status HTTP e corpo estruturado, seguindo o formato:
 *
 * {
 *   "status": "success" | "error",
 *   "message": "descrição da resposta",
 *   "data": { ... },          // opcional
 *   "campo_extra": valor      // opcional via $extra
 * }
 */
class JsonResponse
{
    /**
     * Método interno genérico que constrói a resposta JSON.
     *
     * @param Response $response Instância PSR-7 de resposta
     * @param int $code Código HTTP a ser retornado
     * @param string $status "success" ou "error"
     * @param string|null $message Mensagem principal da resposta
     * @param array|null $data Dados adicionais (opcional)
     * @param array $extra Campos extras personalizados (opcional)
     * @return Response Resposta formatada com JSON e código HTTP
     */
    private static function response(
        Response $response,
        int $code,
        string $status,
        ?string $message = null,
        ?array $data = null,
        array $extra = []
    ): Response {
        if ($code != 204) {
            $payload = [
                'status' => $status,
                'message' => $message
            ];
            if ($data !== null) {
                $payload['data'] = $data;
            }
            if (!empty($extra)) {
                $protected = ['status', 'message', 'data'];
                foreach ($extra as $key => $value) {
                    if (!in_array($key, $protected)) {
                        $payload[$key] = $value;
                    }
                }
            }
            $response->getBody()->write(json_encode($payload));
        }

        return $response
            ->withStatus($code)
            ->withHeader("Content-Type", "application/json");
    }

    /**
     * Retorna uma resposta HTTP 201 (Created), indicando que um recurso foi criado com sucesso.
     *
     * @param Response $response Objeto de resposta.
     * @param string $message Mensagem descritiva da criação.
     * @param array|null $data Dados do recurso criado (opcional).
     *
     * @return Response Resposta com status 201 e JSON formatado.
     */
    public static function created(
        Response $response,
        string $message,
        ?array $data = null
    ): Response
    {
        return self::response($response, 201, "success", $message, $data);
    }

    /**
     * Retorna uma resposta HTTP 200 (OK) com dados e campos adicionais se fornecidos.
     *
     * @param Response $response Objeto de resposta.
     * @param string $message Mensagem de sucesso.
     * @param array $data Dados de retorno.
     * @param array $extra Campos extras customizados.
     *
     * @return Response Resposta com status 200 e JSON estruturado.
     */
    public static function success(
        Response $response,
        string $message,
        ?array $data = null,
        array $extra = []
    ): Response
    {
        return self::response($response, 200, "success", $message, $data, $extra);
    }

    /**
     * Retorna uma resposta HTTP 204 (No Content), usada quando não há corpo de retorno.
     *
     * @param Response $response Objeto de resposta.
     * @return Response Resposta com status 204 e corpo vazio.
     */
    public static function successNoContent(Response $response): Response
    {
        return self::response($response, 204, "success");
    }

    /**
     * Retorna uma resposta HTTP 400 (Bad Request), normalmente usada para erros de validação.
     *
     * @param Response $response Objeto de resposta.
     * @param string $message Mensagem explicando o erro.
     * @param array $data Detalhes ou campos inválidos (opcional).
     *
     * @return Response Resposta com status 400 e estrutura de erro.
     */
    public static function badRequest(
        Response $response,
        string $message,
        ?array $data = null
    ): Response
    {
        return self::response($response, 400, "error", $message, $data);
    }

    /**
     * Retorna uma resposta HTTP 404 (Not Found), com mensagem padrão de recurso não localizado.
     *
     * @param Response $response Objeto de resposta.
     * @param string $message Ignorado, a mensagem "Não encontrado." será usada.
     * @param array $data Informações adicionais (opcional).
     *
     * @return Response Resposta com status 404 e mensagem fixa.
     */
    public static function notFound(
        Response $response,
        string $message,
        ?array $data = null
    ): Response
    {
        return self::response($response, 404, "error", "Não encontrado.", $data);
    }

    /**
     * Retorna uma resposta HTTP 401 (Unauthorized), usada quando o token está ausente ou inválido.
     *
     * @param Response $response Objeto de resposta.
     *
     * @return Response Resposta com status 401 e mensagem fixa de não autorizado.
     */
    public static function unauthorized(
        Response $response,
        string $message = "Não autenticado, token ausente ou inválido."
    ): Response {
        return self::response($response, 401, "error", $message);
    }

    /**
     * Retorna uma resposta HTTP 500 (Internal Server Error), para erros inesperados do servidor.
     *
     * @param Response $response Objeto de resposta.
     * @param string $message Mensagem de erro (padrão: "Erro inesperado, falha interna.").
     *
     * @return Response Resposta com status 500 e mensagem de erro.
     */
    public static function internalServerError(
        Response $response,
        string $message = "Erro inesperado, falha interna."
    ): Response {
        return self::response($response, 500, "error", $message);
    }
}