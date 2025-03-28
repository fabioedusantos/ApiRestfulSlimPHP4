<?php

namespace App\Controllers;

use App\Helpers\JsonResponse;
use App\Services\AuthService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class AuthController
{
    public function __construct(private AuthService $authService)
    {
    }

    public function signUp(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        $infoExpiration = $this->authService->signup(
            $data['name'] ?? '',
            $data['lastname'] ?? '',
            $data['email'] ?? '',
            $data['password'] ?? '',
            !empty($data['isTerms']),
            !empty($data['isPolicy']),
            $data['recaptchaToken'] ?? '',
            $data['recaptchaSiteKey'] ?? ''
        );

        return JsonResponse::created(
            $response,
            "Se o e-mail informado estiver correto, você receberá em breve as instruções para confirmar sua conta.",
            $infoExpiration
        );
    }

    public function resendConfirmEmail(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        $infoExpiration = $this->authService->resendConfirmEmail(
            $data['email'] ?? '',
            $data['recaptchaToken'] ?? '',
            $data['recaptchaSiteKey'] ?? ''
        );

        return JsonResponse::success(
            $response,
            "Se o e-mail informado estiver correto, você receberá em breve as instruções para redefinir sua senha.",
            $infoExpiration
        );
    }

    public function checkResetCode(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        $this->authService->checkResetPassword(
            $data['email'] ?? '',
            $data['code'] ?? '',
            $data['recaptchaToken'] ?? '',
            $data['recaptchaSiteKey'] ?? ''
        );

        return JsonResponse::success($response, "Código ativo.", null);
    }

    public function confirmEmail(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        $this->authService->confirmEmail(
            $data['email'] ?? '',
            $data['code'] ?? '',
            $data['recaptchaToken'] ?? '',
            $data['recaptchaSiteKey'] ?? ''
        );

        return JsonResponse::successNoContent($response);
    }

    public function forgotPassword(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        $infoExpiration = $this->authService->forgotPassword(
            $data['email'] ?? '',
            $data['recaptchaToken'] ?? '',
            $data['recaptchaSiteKey'] ?? ''
        );

        return JsonResponse::success(
            $response,
            "Se o e-mail informado estiver correto, você receberá em breve as instruções para redefinir sua senha.",
            $infoExpiration
        );
    }

    public function resetPassword(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        $this->authService->resetPassword(
            $data['email'] ?? '',
            $data['code'] ?? '',
            $data['password'] ?? '',
            $data['recaptchaToken'] ?? '',
            $data['recaptchaSiteKey'] ?? ''
        );

        return JsonResponse::successNoContent($response);
    }

    public function login(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents(); // Obtém o conteúdo cru do corpo da requisição
        $data = json_decode($body, true); // Decodifica o JSON manualmente

        $token = $this->authService->login(
            $data['email'] ?? '',
            $data['password'] ?? '',
            $data['recaptchaToken'] ?? '',
            $data['recaptchaSiteKey'] ?? ''
        );

        return JsonResponse::success(
            $response,
            "Login realizado com sucesso.",
            $token
        );
    }

    public function refreshToken(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        $token = $this->authService->refreshToken(
            $data['refreshToken'] ?? ''
        );

        return JsonResponse::success(
            $response,
            "Token atualizado realizado com sucesso.",
            $token
        );
    }
    public function isLoggedIn(Request $request, Response $response): Response
    {
        $this->authService->isLoggedIn(
            $request->getAttribute('user')?->sub?->id ?? null
        );
        return JsonResponse::successNoContent($response);
    }

    //google
    public function signUpGoogle(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        $token = $this->authService->signupGoogle(
            $data['idTokenFirebase'] ?? '',
            $data['name'] ?? '',
            $data['lastname'] ?? '',
            !empty($data['isTerms']),
            !empty($data['isPolicy']),
            $data['recaptchaToken'] ?? '',
            $data['recaptchaSiteKey'] ?? ''
        );

        return JsonResponse::created(
            $response,
            "Conta criada e logada com sucesso.",
            $token
        );
    }
}