<?php

namespace Tests\E2E;

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
}