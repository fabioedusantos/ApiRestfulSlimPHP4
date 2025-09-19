<?php

namespace Tests\Services;


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
}