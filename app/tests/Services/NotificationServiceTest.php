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
}