<?php

namespace Tests\E2E;

use App\Helpers\FirebaseAuthHelper;
use Mockery;

class AuthFlowTest extends BaseFlow
{
    private string $useNewSenha = "123@#!senhA";

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    private function firebaseFake(
        string $firebaseIdToken
    ) {
        $recaptchaHelper = Mockery::mock('overload:' . FirebaseAuthHelper::class);
        $recaptchaHelper->shouldReceive('verificarIdToken')
            ->once()
            ->with(
                $this->equalTo($firebaseIdToken)
            )
            ->andReturn($this->firebaseUserData);
    }

    public function testSignupSucesso(): string
    {
        $body = [
            'name' => $this->userData['nome'],
            'lastname' => $this->userData['sobrenome'],
            'email' => $this->userData['email'],
            'password' => $this->useSenha,
            'isTerms' => true,
            'isPolicy' => true,
            'recaptchaToken' => 'fake-token',
            'recaptchaSiteKey' => 'fake-site-key'
        ];

        $this->recaptchaFake($body['recaptchaToken'], $body['recaptchaSiteKey']);

        $request = $this->createRequest('POST', '/auth/signup', body: $body);
        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());

        $responseBody = $this->assertSuccess($response);

        $this->assertEquals(
            'Se o e-mail informado estiver correto, você receberá em breve as instruções para confirmar sua conta.',
            $responseBody['message']
        );

        $this->assertArrayHasKey('expirationInHours', $responseBody['data']);
        $this->assertEquals(2, $responseBody['data']['expirationInHours']);
        $this->assertIsInt($responseBody['data']['expirationInHours']);

        return $this->resetCode;
    }

    public function testResendConfirmEmailSucesso(): void
    {
        $this->testSignupSucesso();
        $body = [
            'email' => $this->userData['email'],
            'recaptchaToken' => 'fake-token',
            'recaptchaSiteKey' => 'fake-site-key'
        ];

        $this->recaptchaFake($body['recaptchaToken'], $body['recaptchaSiteKey']);

        $request = $this->createRequest('POST', '/auth/resend_confirm_email', body: $body);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $this->assertSuccess($response);

        $this->assertEquals(
            'Se o e-mail informado estiver correto, você receberá em breve as instruções para redefinir sua senha.',
            $responseBody['message']
        );

        $this->assertArrayHasKey('expirationInHours', $responseBody['data']);
        $this->assertEquals(2, $responseBody['data']['expirationInHours']);
        $this->assertIsInt($responseBody['data']['expirationInHours']);
    }

    public function testConfirmEmailSucesso(): void
    {
        $resetCode = $this->testSignupSucesso();
        $body = [
            'email' => $this->userData['email'],
            'code' => $resetCode,
            'recaptchaToken' => 'fake-token',
            'recaptchaSiteKey' => 'fake-site-key'
        ];

        $this->recaptchaFake($body['recaptchaToken'], $body['recaptchaSiteKey']);

        $request = $this->createRequest('POST', '/auth/confirm_email', body: $body);
        $response = $this->app->handle($request);

        $this->assertEquals(204, $response->getStatusCode());
    }

    private function login(string $senha): array
    {
        $body = [
            'email' => $this->userData['email'],
            'password' => $senha,
            'recaptchaToken' => 'fake-token',
            'recaptchaSiteKey' => 'fake-site-key'
        ];

        $this->recaptchaFake($body['recaptchaToken'], $body['recaptchaSiteKey']);

        $request = $this->createRequest('POST', '/auth/login', body: $body);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $this->assertSuccess($response);

        $this->assertEquals(
            'Login realizado com sucesso.',
            $responseBody['message']
        );

        $this->assertArrayHasKey('token', $responseBody['data']);
        $this->assertIsString($responseBody['data']['token']);
        $this->assertNotEmpty($responseBody['data']['token']);

        $this->assertArrayHasKey('refreshToken', $responseBody['data']);
        $this->assertIsString($responseBody['data']['refreshToken']);

        $this->assertNotEmpty($responseBody['data']['refreshToken']);

        return $responseBody['data'];
    }

    public function testLoginSucesso(): array
    {
        $this->testConfirmEmailSucesso();
        return $this->login($this->useSenha);
    }

    public function testForgotPasswordSucesso(): void
    {
        $this->testSignupSucesso();
        $body = [
            'email' => $this->userData['email'],
            'recaptchaToken' => 'fake-token',
            'recaptchaSiteKey' => 'fake-site-key'
        ];

        $this->recaptchaFake($body['recaptchaToken'], $body['recaptchaSiteKey']);

        $request = $this->createRequest('POST', '/auth/forgot_password', body: $body);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $this->assertSuccess($response);

        $this->assertEquals(
            'Se o e-mail informado estiver correto, você receberá em breve as instruções para redefinir sua senha.',
            $responseBody['message']
        );

        $this->assertArrayHasKey('expirationInHours', $responseBody['data']);
        $this->assertEquals(2, $responseBody['data']['expirationInHours']);
        $this->assertIsInt($responseBody['data']['expirationInHours']);
    }

    public function testResetPasswordSucesso(): void
    {
        $this->testForgotPasswordSucesso();
        $body = [
            'email' => $this->userData['email'],
            'code' => $this->resetCode,
            'password' => $this->useNewSenha,
            'recaptchaToken' => 'fake-token',
            'recaptchaSiteKey' => 'fake-site-key'
        ];

        $this->recaptchaFake($body['recaptchaToken'], $body['recaptchaSiteKey']);

        $request = $this->createRequest('POST', '/auth/reset_password', body: $body);
        $response = $this->app->handle($request);

        $this->assertEquals(204, $response->getStatusCode());

        //testamos com a nova senha
        $this->login($this->useNewSenha);
    }

    private function testCheckResetCodeAtivo(): void
    {
        $body = [
            'email' => $this->userData['email'],
            'code' => $this->resetCode,
            'recaptchaToken' => 'fake-token',
            'recaptchaSiteKey' => 'fake-site-key'
        ];

        $this->recaptchaFake($body['recaptchaToken'], $body['recaptchaSiteKey']);

        $request = $this->createRequest('POST', '/auth/check_reset_code', body: $body);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $this->assertSuccess($response);

        $this->assertEquals(
            'Código ativo.',
            $responseBody['message']
        );
    }

    public function testCheckResetCodeDeCriacaoDeContaEstaAtivoSucesso(): void
    {
        $this->testSignupSucesso();
        $this->testCheckResetCodeAtivo();
    }

    public function testCheckResetCodeDeRedefinicaoDeSenhaEstaAtivoSucesso(): void
    {
        $this->testForgotPasswordSucesso();
        $this->testCheckResetCodeAtivo();
    }

    private function isLoggedIn(array $token): void
    {
        $request = $this->createRequest(
            'GET',
            '/auth/is_logged_in',
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token['token']
            ]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testIsLoggedInBySignupSucesso(): void
    {
        $token = $this->testLoginSucesso();
        $this->isLoggedIn($token);
    }

    public function testIsLoggedInByLoginSucesso(): void
    {
        $token = $this->testLoginSucesso();
        $this->isLoggedIn($token);
    }

    private function refreshToken(array $token): array
    {
        $body = [
            'refreshToken' => $token['refreshToken']
        ];

        $request = $this->createRequest(
            'POST',
            '/auth/refresh_token',
            body: $body
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = $this->assertSuccess($response);

        $this->assertEquals(
            'Token atualizado realizado com sucesso.',
            $responseBody['message']
        );

        $this->assertArrayHasKey('token', $responseBody['data']);
        $this->assertIsString($responseBody['data']['token']);
        $this->assertNotEmpty($responseBody['data']['token']);

        $this->assertArrayHasKey('refreshToken', $responseBody['data']);
        $this->assertIsString($responseBody['data']['refreshToken']);
        $this->assertNotEmpty($responseBody['data']['refreshToken']);

        return $responseBody['data'];
    }

    public function testRefreshTokenLoginSucesso(): array
    {
        $token = $this->testLoginSucesso();
        return $this->refreshToken($token);
    }

    public function testIsLoggedInByRefreshTokenLoginSucesso(): void
    {
        $token = $this->testRefreshTokenLoginSucesso();
        $this->isLoggedIn($token);
    }

    public function testSignupGoogleSucesso(): array
    {
        $body = [
            'idTokenFirebase' => $this->firebaseUserData->uid,
            'name' => $this->userData['nome'],
            'lastname' => $this->userData['sobrenome'],
            'isTerms' => true,
            'isPolicy' => true,
            'recaptchaToken' => 'fake-token',
            'recaptchaSiteKey' => 'fake-site-key'
        ];

        $this->recaptchaFake($body['recaptchaToken'], $body['recaptchaSiteKey']);
        $this->firebaseFake($body['idTokenFirebase']);

        $request = $this->createRequest('POST', '/auth/google/signup', body: $body);
        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());

        $responseBody = $this->assertSuccess($response);

        $this->assertEquals(
            'Conta criada e logada com sucesso.',
            $responseBody['message']
        );

        $this->assertArrayHasKey('token', $responseBody['data']);
        $this->assertIsString($responseBody['data']['token']);
        $this->assertNotEmpty($responseBody['data']['token']);

        $this->assertArrayHasKey('refreshToken', $responseBody['data']);
        $this->assertIsString($responseBody['data']['refreshToken']);
        $this->assertNotEmpty($responseBody['data']['refreshToken']);

        return $responseBody['data'];
    }

}