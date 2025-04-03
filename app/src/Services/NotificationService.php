<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerErrorException;
use App\Helpers\FirebaseMessagingHelper;

class NotificationService
{
    private const CANAIS_PERMITIDOS = array("gerais", "promocoes", "atualizacoes");

    public function __construct()
    {
    }

    public function getChannels(): array
    {
        return self::CANAIS_PERMITIDOS;
    }

    public function sendToAll(
        string $channelId,
        string $title,
        ?string $body,
        ?string $link
    ): array {
        if (!in_array($channelId, self::CANAIS_PERMITIDOS)) {
            throw new BadRequestException("ChannelId não permitido.");
        }

        if (mb_strlen($title) < 2) {
            throw new BadRequestException("Title muito curto.");
        }

        if (!empty($body) && mb_strlen($body) < 2) {
            throw new BadRequestException("Body muito curto.");
        }

        if (empty($body)) {
            $body = $title;
        }

        try {
            return json_decode(
                FirebaseMessagingHelper::sendNotificationToAll(
                    $channelId,
                    $title,
                    $body,
                    $link
                ),
                true
            );
        } catch (\Exception $e) {
            throw new InternalServerErrorException("Houve um erro desconhecido." . $e->getMessage());
        }
    }

    public function sendNotificationToDevice(
        string $channelId,
        string $deviceToken,
        string $title,
        ?string $body,
        ?string $link
    ): array {
        if (!in_array($channelId, self::CANAIS_PERMITIDOS)) {
            throw new BadRequestException("ChannelId não permitido.");
        }

        if (mb_strlen($deviceToken) < 152) {
            throw new BadRequestException("DeviceToken inválido.");
        }

        if (mb_strlen($title) < 2) {
            throw new BadRequestException("Title muito curto.");
        }

        if (!empty($body) && mb_strlen($body) < 2) {
            throw new BadRequestException("Body muito curto.");
        }

        if (empty($body)) {
            $body = $title;
        }

        try {
            return json_decode(
                FirebaseMessagingHelper::sendNotificationToDevice(
                    $channelId,
                    $deviceToken,
                    $title,
                    $body,
                    $link
                ),
                true
            );
        } catch (\Exception $e) {
            throw new InternalServerErrorException("Houve um erro desconhecido." . $e->getMessage());
        }
    }
}