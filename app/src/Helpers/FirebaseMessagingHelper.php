<?php

namespace App\Helpers;

use App\Exceptions\InternalServerErrorException;
use Firebase\JWT\JWT;

/**
 * Classe auxiliar para enviar notificações via Firebase Cloud Messaging (FCM).
 *
 * Esta classe fornece métodos para obter o token de acesso ao Firebase e enviar
 * notificações para dispositivos ou grupos de dispositivos usando o Firebase Cloud Messaging.
 */
class FirebaseMessagingHelper
{
    /**
     * Obtém o token de acesso para a autenticação do Firebase.
     *
     * Este método gera um JWT (JSON Web Token) com base nas credenciais do Firebase e o utiliza
     * para obter um token de acesso necessário para enviar notificações via Firebase Cloud Messaging.
     *
     * @return string O token de acesso do Firebase.
     *
     * @throws InternalServerErrorException Se ocorrer um erro ao obter o token de acesso.
     */
    public static function getAccessToken(): string
    {
        $credentialsArray = FirebaseCredentialsHelper::get();
        $now = time();

        $payload = [
            'iss' => $credentialsArray['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600
        ];

        $jwt = JWT::encode($payload, $credentialsArray['private_key'], 'RS256');

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));

        $result = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (empty($result['access_token'])) {
            throw new InternalServerErrorException('[Firebase] Falha ao obter Access Token.');
        }

        return $result['access_token'];
    }

    /**
     * Envia uma notificação para um único dispositivo.
     *
     * Este método utiliza o token de acesso do Firebase para enviar uma notificação para um dispositivo
     * específico com base no seu token de registro. A notificação inclui um título, corpo e dados adicionais.
     *
     * @param string $channelId O ID do canal (tópico) no qual o dispositivo está inscrito.
     * @param string $deviceToken O token do dispositivo de destino.
     * @param string $title O título da notificação.
     * @param string $body O corpo da notificação.
     * @param string $link Um link que será enviado junto com a notificação.
     *
     * @return string A resposta da solicitação para enviar a notificação.
     */
    public static function sendNotificationToDevice(
        string $channelId,
        string $deviceToken,
        string $title,
        string $body,
        ?string $link,
    ): string
    {
        $accessToken = self::getAccessToken();
        $credentialsArray = FirebaseCredentialsHelper::get();
        $projectId = $credentialsArray['project_id'] ?? null;;

        $message = [
            'message' => [
                'token' => $deviceToken,
                'data' => [
                    'title' => $title,
                    'body' => $body,
                    'link' => $link,
                    'channelId' => $channelId
                ]
            ]
        ];

        $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}",
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * Envia uma notificação para todos os dispositivos inscritos em um tópico.
     *
     * Este método envia uma notificação para todos os dispositivos que estão inscritos
     * em um canal de tópico no Firebase Cloud Messaging (FCM).
     *
     * @param string $channelId O ID do canal (tópico) no qual os dispositivos estão inscritos.
     * @param string $title O título da notificação.
     * @param string $body O corpo da notificação.
     * @param string $link Um link que será enviado junto com a notificação.
     *
     * @return string A resposta da solicitação para enviar a notificação.
     */
    public static function sendNotificationToAll(
        string $channelId,
        string $title,
        string $body,
        ?string $link
    ): string
    {
        $accessToken = self::getAccessToken();
        $credentialsArray = FirebaseCredentialsHelper::get();
        $projectId = $credentialsArray['project_id'] ?? null;;

        $message = [
            'message' => [
                'topic' => $channelId,
                'data' => [
                    'title' => $title,
                    'body' => $body,
                    'link' => $link,
                    'channelId' => $channelId
                ]
            ]
        ];

        $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$accessToken}",
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }
}
