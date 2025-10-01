<?php

namespace Tests\Services;


use App\Helpers\FirebaseMessagingHelper;
use App\Services\NotificationService;
use Mockery;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\UserFixture;

class NotificationServiceTest extends TestCase
{
    use UserFixture;

    private NotificationService $notificationService;
    private array $userData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userData = $this->getUserData();
        $this->notificationService = new NotificationService();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }


    //getChannels()
    public function testGetChannelsSucesso(): void
    {
        $arrayExperado = array("gerais", "promocoes", "atualizacoes");

        $response = $this->notificationService->getChannels();

        $this->assertIsArray($response);
        $this->assertEquals($arrayExperado, $response);
    }


    //sendToAll()
    public function testSendToAllSucesso(): void
    {
        $channelId = "gerais";
        $title = "Teste de envio de email";
        $body = "Teste de corpo de email";
        $link = "link/qualquer";

        $firebaseMessagingHelper = Mockery::mock('overload:' . FirebaseMessagingHelper::class);

        $firebaseMessagingHelper->shouldReceive('sendNotificationToAll')
            ->once()
            ->with(
                $this->equalTo($channelId),
                $this->equalTo($title),
                $this->equalTo($body),
                $this->equalTo($link)
            )
            ->andReturn('{"name": "projects/projectname-3as234/messages/6587734535230942853"}');

        $response = $this->notificationService->sendToAll(
            $channelId,
            $title,
            $body,
            $link
        );

        $this->assertIsArray($response);

        $this->assertArrayHasKey('name', $response);
        $this->assertNotEmpty($response['name']);
        $this->assertIsString($response['name']);
    }

    public function testSendToAllFalhaChannelIdNaoPermitido(): void
    {
        $channelId = "batatinha";
        $title = "Teste de envio de email";
        $body = "Teste de corpo de email";
        $link = "link/qualquer";

        $this->expectExceptionMessage("ChannelId não permitido.");

        $this->notificationService->sendToAll(
            $channelId,
            $title,
            $body,
            $link
        );
    }

    public function testSendToAllFalhaTitleMuitoCurto(): void
    {
        $channelId = "gerais";
        $title = "T";
        $body = "Teste de corpo de email";
        $link = "link/qualquer";

        $this->expectExceptionMessage("Title muito curto.");

        $this->notificationService->sendToAll(
            $channelId,
            $title,
            $body,
            $link
        );
    }

    public function testSendToAllFalhaBodyMuitoCurto(): void
    {
        $channelId = "gerais";
        $title = "Teste de envio de email";
        $body = "T";
        $link = "link/qualquer";

        $this->expectExceptionMessage("Body muito curto.");

        $this->notificationService->sendToAll(
            $channelId,
            $title,
            $body,
            $link
        );
    }

    public function testSendToAllFalhaDesconhecida(): void
    {
        $channelId = "gerais";
        $title = "Teste de envio de email";
        $body = "Teste de corpo de email";
        $link = "link/qualquer";

        $firebaseMessagingHelper = Mockery::mock('overload:' . FirebaseMessagingHelper::class);

        $firebaseMessagingHelper->shouldReceive('sendNotificationToAll')
            ->once()
            ->with(
                $this->equalTo($channelId),
                $this->equalTo($title),
                $this->equalTo($body),
                $this->equalTo($link)
            )
            ->andThrow(new \Exception("Erro do Firebase Bonitão."));


        $this->expectExceptionMessage("Houve um erro desconhecido.");

        $this->notificationService->sendToAll(
            $channelId,
            $title,
            $body,
            $link
        );
    }


    //sendNotificationToDevice()
    public function testSendNotificationToDeviceSucesso(): void
    {
        $channelId = "gerais";
        //tem que ter no minimo 152 caracteres
        $deviceToken = "u9xG7qzTf5lJ0hX2UqK4vZcA1Y8bRd3pL6mVnWsQbI7kCz9H2dO3aXc0vNwF1jLt5pU7YzqMw6d3pL"
            . "6mVnWsQbIdO3aX7kCz9H2dO3aXc0vNwF1jLt5pU7YzqMw6oJbTgZK0V9sLkWf8EuCn3Rz2jPv4";
        $title = "Teste de envio de email";
        $body = "Teste de corpo de email";
        $link = "link/qualquer";

        $firebaseMessagingHelper = Mockery::mock('overload:' . FirebaseMessagingHelper::class);

        $firebaseMessagingHelper->shouldReceive('sendNotificationToDevice')
            ->once()
            ->with(
                $this->equalTo($channelId),
                $this->equalTo($deviceToken),
                $this->equalTo($title),
                $this->equalTo($body),
                $this->equalTo($link)
            )
            ->andReturn('{"name": "projects/projectname-3as234/messages/6587734535230942853"}');

        $response = $this->notificationService->sendNotificationToDevice(
            $channelId,
            $deviceToken,
            $title,
            $body,
            $link
        );

        $this->assertIsArray($response);

        $this->assertArrayHasKey('name', $response);
        $this->assertNotEmpty($response['name']);
        $this->assertIsString($response['name']);
    }

    public function testSendNotificationToDeviceFalhaChannelIdNaoPermitido(): void
    {
        $channelId = "batatinha";
        //tem que ter no minimo 152 caracteres
        $deviceToken = "u9xG7qzTf5lJ0hX2UqK4vZcA1Y8bRd3pL6mVnWsQbI7kCz9H2dO3aXc0vNwF1jLt5pU7YzqMw6d3pL"
            . "6mVnWsQbIdO3aX7kCz9H2dO3aXc0vNwF1jLt5pU7YzqMw6oJbTgZK0V9sLkWf8EuCn3Rz2jPv4";
        $title = "Teste de envio de email";
        $body = "Teste de corpo de email";
        $link = "link/qualquer";

        $this->expectExceptionMessage("ChannelId não permitido.");

        $this->notificationService->sendNotificationToDevice(
            $channelId,
            $deviceToken,
            $title,
            $body,
            $link
        );
    }

    public function testSendNotificationToDeviceFalhaDeviceTokenInvalido(): void
    {
        $channelId = "gerais";
        //tem que ter no minimo 152 caracteres
        $deviceToken = "batatinha";
        $title = "Teste de envio de email";
        $body = "Teste de corpo de email";
        $link = "link/qualquer";

        $this->expectExceptionMessage("DeviceToken inválido.");

        $responseJson = $this->notificationService->sendNotificationToDevice(
            $channelId,
            $deviceToken,
            $title,
            $body,
            $link
        );

        $this->assertIsArray($responseJson);

        $this->assertArrayHasKey('name', $responseJson);
        $this->assertNotEmpty($responseJson['name']);
        $this->assertIsString($responseJson['name']);
    }
}