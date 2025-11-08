<?php

namespace App\Controllers;

use App\Helpers\JsonResponse;
use App\Services\NotificationService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use OpenApi\Attributes as OA;

class NotificationController
{

    public function __construct(private NotificationService $notificationService)
    {
    }

    #[OA\Get(
        path: "/notifications/push_channels",
        summary: "Retorna os canais permitidos para enviar notificações push",
        description: "Retorna os canais permitidos para enviar notificações push",
        tags: ["Notifications"],
        security: [["BearerAuth" => []]]
    )]
    #[OA\Response(
        response: 200,
        description: "Canais permitidos.",
        content: new OA\JsonContent(
            example: '{
                        "status": "success",
                        "message": "Push Channels.",
                        "data": [
                            "channelID1",
                            "channelID2",
                            "channelIDN"
                        ]
                    }'
        )
    )]
    public function getPushChannels(Request $request, Response $response): Response
    {
        $ret = $this->notificationService->getChannels();

        return JsonResponse::success(
            $response,
            "Push Channels.",
            $ret
        );
    }

    #[OA\Post(
        path: "/notifications/send_push_to_device",
        summary: "Realiza o envio de notificações push para um dispositivo/usuário",
        description: "Realiza o envio de notificações push para um dispositivo/usuário",
        tags: ["Notifications"],
        security: [["BearerAuth" => []]]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: true,
        content: new OA\JsonContent(
            required: [
                "channelId",
                "deviceToken",
                "title",
                "body",
                "link"
            ],
            properties: [
                new OA\Property(
                    property: "channelId",
                    type: "string",
                    description: "Canal da notificação push (/notifications/push_channels retorna os permitidos)"
                ),
                new OA\Property(
                    property: "deviceToken",
                    type: "string",
                    description: "Id firebase do device/usuário que receberá a notificação push."
                ),
                new OA\Property(
                    property: "title",
                    type: "string",
                    description: "Titulo da notificação push"
                ),
                new OA\Property(
                    property: "body",
                    type: "string",
                    description: "Corpo da notificação push (se omitida, será o mesmo que o titulo)"
                ),
                new OA\Property(
                    property: "link",
                    type: "string",
                    description: "Url ou link que aplicação abrirá ao tocar (ação) na notificação push. Pode ser omitido."
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "Notificação Push enviada",
        content: new OA\JsonContent(
            example: '{
                        "status": "success",
                        "message": "Notificação push enviada com sucesso para DEVICETOKENAQUI.",
                        "data": {
                            "name": "projects/projectname-3as234/messages/6587734535230942853"
                        }
                    }'
        )
    )]
    public function sendPushToDevice(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        $channelId = $data['channelId'] ?? '';
        $deviceToken = $data['deviceToken'] ?? '';
        $title = $data['title'] ?? '';
        $body = $data['body'] ?? null;
        $link = $data['link'] ?? null;

        $ret = $this->notificationService->sendNotificationToDevice(
            $channelId,
            $deviceToken,
            $title,
            $body,
            $link
        );

        return JsonResponse::created(
            $response,
            "Notificação push enviada com sucesso para {$deviceToken}.",
            $ret
        );
    }

    #[OA\Post(
        path: "/notifications/send_push_to_all",
        summary: "Realiza o envio de notificações push para todos os dispositivos/usuários",
        description: "Realiza o envio de notificações push para todos os dispositivos/usuários",
        tags: ["Notifications"],
        security: [["BearerAuth" => []]]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: true,
        content: new OA\JsonContent(
            required: [
                "channelId",
                "title",
                "body",
                "link"
            ],
            properties: [
                new OA\Property(
                    property: "channelId",
                    type: "string",
                    description: "Canal da notificação push (/notifications/push_channels retorna os permitidos)"
                ),
                new OA\Property(
                    property: "title",
                    type: "string",
                    description: "Titulo da notificação push"
                ),
                new OA\Property(
                    property: "body",
                    type: "string",
                    description: "Corpo da notificação push (se omitida, será o mesmo que o titulo)"
                ),
                new OA\Property(
                    property: "link",
                    type: "string",
                    description: "Url ou link que aplicação abrirá ao tocar (ação) na notificação push. Pode ser omitido."
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "Notificação Push enviada",
        content: new OA\JsonContent(
            example: '{
                        "status": "success",
                        "message": "Notificação push enviada com sucesso para CHANNELIDAQUI.",
                        "data": {
                            "name": "projects/projectname-3as234/messages/6587734535230942853"
                        }
                    }'
        )
    )]
    public function sendPushToAll(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        $channelId = $data['channelId'] ?? '';
        $title = $data['title'] ?? '';
        $body = $data['body'] ?? null;
        $link = $data['link'] ?? null;

        $ret = $this->notificationService->sendToAll(
            $channelId,
            $title,
            $body,
            $link
        );

        return JsonResponse::created(
            $response,
            "Notificação push enviada com sucesso para {$channelId}.",
            $ret
        );
    }
}