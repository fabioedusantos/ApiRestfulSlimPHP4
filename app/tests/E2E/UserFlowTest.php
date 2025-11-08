<?php

namespace Tests\E2E;

use Mockery;
use Tests\Fixtures\UserFixture;

class UserFlowTest extends BaseFlow
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


    public function testGetMeSucesso(
        string $expectedNome = "Fábio",
        string $expectedSobrenome = "Santos",
        ?string $expectedPhotoBlob = null,
        ?array $token = null
    ): array {
        if (empty($token)) {
            $token = $this->baseCreateAndLogin();
        }

        $request = $this->createRequest(
            'GET',
            '/users/me',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token['token']
            ]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $this->assertSuccess($response);

        $this->assertEquals('Sucesso.', $responseBody['message']);

        $userData = $responseBody['data'];

        $this->assertArrayHasKey('nome', $userData);
        $this->assertIsString($userData['nome']);
        $this->assertEquals($expectedNome, $userData['nome']);

        $this->assertArrayHasKey('sobrenome', $userData);
        $this->assertIsString($userData['sobrenome']);
        $this->assertEquals($expectedSobrenome, $userData['sobrenome']);

        $this->assertArrayHasKey('email', $userData);
        $this->assertIsString($userData['email']);
        $this->assertEquals("fabioedusantos@gmail.com", $userData['email']);

        $this->assertArrayHasKey('photoBlob', $userData);
        $this->assertEquals($expectedPhotoBlob, $userData['photoBlob']);

        $this->assertArrayHasKey('ultimoAcesso', $userData);
        $this->assertContains(gettype($userData['ultimoAcesso']), ['NULL', 'string']);

        $this->assertArrayHasKey('criadoEm', $userData);
        $this->assertIsString($userData['criadoEm']);

        $this->assertArrayHasKey('alteradoEm', $userData);
        $this->assertIsString($userData['alteradoEm']);

        $this->assertArrayHasKey('isContaGoogle', $userData);
        $this->assertIsBool($userData['isContaGoogle']);
        $this->assertEquals(false, $userData['isContaGoogle']);

        return $token;
    }

    public function testSetMeSucesso(): array
    {
        $token = $this->testGetMeSucesso();

        $body = [
            "name" => "José",
            "lastname" => "Silva",
            "password" => $this->useNewSenha,
            "photoBlob" => base64_encode($this->userData['photo_blob']),
            "isRemovePhoto" => false
        ];

        //alteramos
        $request = $this->createRequest(
            'PATCH',
            '/users/me',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token['token']
            ],
            body: $body
        );
        $response = $this->app->handle($request);
        $this->assertEquals(204, $response->getStatusCode());

        //checamos os novos dados
        $this->testGetMeSucesso(
            $body['name'],
            $body['lastname'],
            $body['photoBlob'],
            $token
        );

        //checamos a nova senha
        $this->baseLogin($body['password']);

        return $token;
    }

    public function testSetMeRemoverFotoSucesso(): void
    {
        //executamos o teste que adiciona foto com sucesso
        $token = $this->testSetMeSucesso();

        //mandamos remover a foto
        $body = [
            "isRemovePhoto" => true
        ];

        $request = $this->createRequest(
            'PATCH',
            '/users/me',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token['token']
            ],
            body: $body
        );
        $response = $this->app->handle($request);
        $this->assertEquals(204, $response->getStatusCode());

        //checamos a remoção da foto
        $this->testGetMeSucesso(
            expectedNome: "José",           //nome do teste incluido no teste testSetMeSucesso()
            expectedSobrenome: "Silva",     //sobrenome do teste incluido no teste testSetMeSucesso()
            token: $token
        );
    }

    public function testGetMeFalhaNaoAutorizado(): void
    {
        $request = $this->createRequest(
            'GET',
            '/users/me',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer token-falso-aqui'
            ]
        );
        $response = $this->app->handle($request);
        $this->assertJwtNaoAutorizado($response);
    }

    public function testGetMeFalhaAutenticacaoUsuarioInexistente(): void
    {
        $fakeToken = $this->generateInvalidToken();
        $request = $this->createRequest(
            'GET',
            '/users/me',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $fakeToken
            ]
        );
        $response = $this->app->handle($request);
        $this->assertNaoAutorizadoUsuarioInativoOuInexistente($response);
    }

    public function testSetMeFalhaNaoAutorizado(): void
    {
        $request = $this->createRequest(
            'PATCH',
            '/users/me',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer token-falso-aqui'
            ]
        );
        $response = $this->app->handle($request);
        $this->assertJwtNaoAutorizado($response);
    }

    public function testSetMeFalhaAutenticacaoUsuarioInexistente(): void
    {
        $fakeToken = $this->generateInvalidToken();
        $request = $this->createRequest(
            'PATCH',
            '/users/me',
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