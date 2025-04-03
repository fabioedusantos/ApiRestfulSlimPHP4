<?php

namespace App\Controllers;

use App\Helpers\JsonResponse;
use App\Services\NotificationService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class NotificationController
{

    public function __construct(private NotificationService $notificationService)
    {
    }

    public function getPushChannels(Request $request, Response $response): Response {
        $ret = $this->notificationService->getChannels();

        return JsonResponse::success(
            $response,
            "Push Channels.",
            $ret
        );
    }

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