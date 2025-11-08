<?php

namespace Tests\E2E;

use App\Helpers\FirebaseMessagingHelper;
use Mockery;
use Tests\Fixtures\UserFixture;

class NotificationFlowTest extends BaseFlow
{
    use UserFixture;

    private string $useNewSenha = "123@#!senhA";

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }


    protected function firebaseMessagingSendNotificationToDeviceFake(
        string $channelId,
        string $deviceToken,
        string $title,
        string $body,
        ?string $link,
    ) {
        $recaptchaHelper = Mockery::mock('overload:' . FirebaseMessagingHelper::class);
        $recaptchaHelper->shouldReceive('sendNotificationToDevice')
            ->once()
            ->with(
                $this->equalTo($channelId),
                $this->equalTo($deviceToken),
                $this->equalTo($title),
                $this->equalTo($body),
                $this->equalTo($link),
            )
            ->andReturn('{"name": "projects/projectname-3as234/messages/6587734535230942853"}');
    }

    protected function firebaseMessagingSendNotificationToAllFake(
        string $channelId,
        string $title,
        string $body,
        ?string $link,
    ) {
        $recaptchaHelper = Mockery::mock('overload:' . FirebaseMessagingHelper::class);
        $recaptchaHelper->shouldReceive('sendNotificationToAll')
            ->once()
            ->with(
                $this->equalTo($channelId),
                $this->equalTo($title),
                $this->equalTo($body),
                $this->equalTo($link),
            )
            ->andReturn('{"name": "projects/projectname-3as234/messages/6587734535230942853"}');
    }

    public function testGetPushChannelsSucesso(): array
    {
        $token = $this->baseCreateAndLogin();

        $request = $this->createRequest(
            'GET',
            '/notifications/push_channels',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token['token']
            ]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $this->assertSuccess($response);

        $this->assertEquals('Push Channels.', $responseBody['message']);

        $this->assertNotEmpty($responseBody['data']);

        foreach ($responseBody['data'] as $value) {
            $this->assertIsString($value);
        }

        return $responseBody['data'];
    }

    public function testSendPushToDeviceSucesso(): void
    {
        $channels = $this->testGetPushChannelsSucesso();
        $token = $this->baseLogin();

        $body = [
            "channelId" => $channels[0],
            //tem que ter no minimo 152 caracteres
            "deviceToken" => "u9xG7qzTf5lJ0hX2UqK4vZcA1Y8bRd3pL6mVnWsQbI7kCz9H2dO3aXc0vNwF1jLt5pU7YzqMw6d3pL"
                . "6mVnWsQbIdO3aX7kCz9H2dO3aXc0vNwF1jLt5pU7YzqMw6oJbTgZK0V9sLkWf8EuCn3Rz2jPv4",
            "title" => "Teste de Mensagem Supimpa",
            "body" => "Corpo da mensagem naravilhosa",
            "link" => "teste/link/app"
        ];

        $this->firebaseMessagingSendNotificationToDeviceFake(
            $body['channelId'],
            $body['deviceToken'],
            $body['title'],
            $body['body'],
            $body['link']
        );

        $request = $this->createRequest(
            'POST',
            '/notifications/send_push_to_device',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token['token']
            ],
            body: $body
        );
        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());

        $responseBody = $this->assertSuccess($response);

        $this->assertEquals(
            "Notificação push enviada com sucesso para {$body['deviceToken']}.",
            $responseBody['message']
        );

        $data = $responseBody['data'];

        $this->assertArrayHasKey('name', $data);
        $this->assertIsString($data['name']);
        $this->assertNotEmpty($data['name']);
    }

    public function testSendPushToAllSucesso(): void
    {
        $channels = $this->testGetPushChannelsSucesso();
        $token = $this->baseLogin();

        $body = [
            "channelId" => $channels[0],
            "title" => "Teste de Mensagem Supimpa",
            "body" => "Corpo da mensagem naravilhosa",
            "link" => "teste/link/app"
        ];

        $this->firebaseMessagingSendNotificationToAllFake(
            $body['channelId'],
            $body['title'],
            $body['body'],
            $body['link']
        );

        $request = $this->createRequest(
            'POST',
            '/notifications/send_push_to_all',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token['token']
            ],
            body: $body
        );
        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());

        $responseBody = $this->assertSuccess($response);

        $this->assertEquals(
            "Notificação push enviada com sucesso para {$body['channelId']}.",
            $responseBody['message']
        );

        $data = $responseBody['data'];

        $this->assertArrayHasKey('name', $data);
        $this->assertIsString($data['name']);
        $this->assertNotEmpty($data['name']);
    }

    public function testGetPushChannelsFalhaNaoAutorizado(): void
    {
        $request = $this->createRequest(
            'GET',
            '/notifications/push_channels',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer token-falso-aqui'
            ]
        );
        $response = $this->app->handle($request);
        $this->assertJwtNaoAutorizado($response);
    }

    public function testGetPushChannelsFalhaAutenticacaoUsuarioInexistente(): void
    {
        $fakeToken = $this->generateInvalidToken();
        $request = $this->createRequest(
            'GET',
            '/notifications/push_channels',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $fakeToken
            ]
        );
        $response = $this->app->handle($request);
        $this->assertNaoAutorizadoUsuarioInativoOuInexistente($response);
    }

    public function testSendPushToDeviceFalhaNaoAutorizado(): void
    {
        $request = $this->createRequest(
            'POST',
            '/notifications/send_push_to_device',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer token-falso-aqui'
            ]
        );
        $response = $this->app->handle($request);
        $this->assertJwtNaoAutorizado($response);
    }

    public function testSendPushToDeviceFalhaAutenticacaoUsuarioInexistente(): void
    {
        $fakeToken = $this->generateInvalidToken();
        $request = $this->createRequest(
            'POST',
            '/notifications/send_push_to_device',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $fakeToken
            ]
        );
        $response = $this->app->handle($request);
        $this->assertNaoAutorizadoUsuarioInativoOuInexistente($response);
    }

    public function testSendPushToAllFalhaNaoAutorizado(): void
    {
        $request = $this->createRequest(
            'POST',
            '/notifications/send_push_to_all',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer token-falso-aqui'
            ]
        );
        $response = $this->app->handle($request);
        $this->assertJwtNaoAutorizado($response);
    }

    public function testSendPushToAllFalhaAutenticacaoUsuarioInexistente(): void
    {
        $fakeToken = $this->generateInvalidToken();
        $request = $this->createRequest(
            'POST',
            '/notifications/send_push_to_all',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $fakeToken
            ]
        );
        $response = $this->app->handle($request);
        $this->assertNaoAutorizadoUsuarioInativoOuInexistente($response);
    }
}