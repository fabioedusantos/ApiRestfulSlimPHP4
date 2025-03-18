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
}