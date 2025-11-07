<?php

namespace App\Controllers;

use App\Helpers\JsonResponse;
use App\Services\AuthService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use OpenApi\Attributes as OA;

class AuthController
{
    public function __construct(private AuthService $authService)
    {
    }

    #[OA\Post(
        path: "/auth/signup",
        summary: "Realiza a criação de conta de usuário com conta padrão (email e senha)",
        description: "Endpoint responsável por realizar a criação de conta de usuário e enviar um e-mail com o código de confirmação para autorizar a conta",
        tags: ["Auth"]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: true,
        content: new OA\JsonContent(
            required: [
                "name",
                "lastname",
                "email",
                "password",
                "isTerms",
                "isPolicy",
                "recaptchaToken",
                "recaptchaSiteKey"
            ],
            properties: [
                new OA\Property(
                    property: "name",
                    type: "string",
                    description: "Nome do usuário"
                ),
                new OA\Property(
                    property: "lastname",
                    type: "string",
                    description: "Sobrenome do usuário"
                ),
                new OA\Property(
                    property: "email",
                    type: "string",
                    format: "email",
                    description: "Email do usuário"
                ),
                new OA\Property(
                    property: "password",
                    type: "string",
                    description: "Senha do usuário"
                ),
                new OA\Property(
                    property: "isTerms",
                    type: "boolean",
                    description: "Aceito os termos da aplicação"
                ),
                new OA\Property(
                    property: "isPolicy",
                    type: "boolean",
                    description: "Aceito a política de privacidade da aplicação"
                ),
                new OA\Property(
                    property: "recaptchaToken",
                    type: "string",
                    description: "Token do Google reCAPTCHA"
                ),
                new OA\Property(
                    property: "recaptchaSiteKey",
                    type: "string",
                    description: "Site key do Google reCAPTCHA"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "Criação bem sucedida",
        content: new OA\JsonContent(
            example: '{
                        "status": "success",
                        "message": "Se o e-mail informado estiver correto, você receberá em breve as instruções para confirmar sua conta.",
                        "data": {
                            "expirationInHours": 2
                        }
                    }'
        )
    )]
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

    #[OA\Post(
        path: "/auth/resend_confirm_email",
        summary: "Realiza o reenvio do código de confirmação de conta padrão (email e senha) por e-mail",
        description: "Endpoint responsável por realizar o envio do novo código de confirmação de conta padrão por e-mail.",
        tags: ["Auth"]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: true,
        content: new OA\JsonContent(
            required: ["email", "recaptchaToken", "recaptchaSiteKey"],
            properties: [
                new OA\Property(
                    property: "email",
                    type: "string",
                    format: "email",
                    description: "Email do usuário"
                ),
                new OA\Property(
                    property: "recaptchaToken",
                    type: "string",
                    description: "Token do Google reCAPTCHA"
                ),
                new OA\Property(
                    property: "recaptchaSiteKey",
                    type: "string",
                    description: "Site key do Google reCAPTCHA"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Código de confirmação enviado com sucesso",
        content: new OA\JsonContent(
            example: '{
                        "status": "success",
                        "message": "Se o e-mail informado estiver correto, você receberá em breve as instruções para redefinir sua senha.",
                        "data": {
                            "expirationInHours": 2
                        }
                    }'
        )
    )]
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

    #[OA\Post(
        path: "/auth/check_reset_code",
        summary: "Realiza a checagem se o código de redefinição de senha ou ativação de conta padrão (email e senha) está ativo",
        description: "Realiza a checagem se o código de redefinição de senha ou ativação de conta padrão (email e senha) está ativo. Este endpoint faz parte do ciclo de redefinição de senha a ação iniciada por /auth/forgot_password.",
        tags: ["Auth"]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: true,
        content: new OA\JsonContent(
            required: ["email", "code", "recaptchaToken", "recaptchaSiteKey"],
            properties: [
                new OA\Property(
                    property: "email",
                    type: "string",
                    format: "email",
                    description: "Email do usuário"
                ),
                new OA\Property(
                    property: "code",
                    type: "string",
                    description: "Código de redefinição de senha enviado no e-mail do usuário"
                ),
                new OA\Property(
                    property: "recaptchaToken",
                    type: "string",
                    description: "Token do Google reCAPTCHA"
                ),
                new OA\Property(
                    property: "recaptchaSiteKey",
                    type: "string",
                    description: "Site key do Google reCAPTCHA"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Código ativo",
        content: new OA\JsonContent(
            example: '{
                        "status": "success",
                        "message": "Código ativo."
                    }'
        )
    )]
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

    #[OA\Post(
        path: "/auth/confirm_email",
        summary: "Realiza a ativação de conta padrão (email e senha)",
        description: "Endpoint responsável por realizar a ativação de conta padrão através do código enviado por e-mail. Este endpoint conclui a ação iniciada por /auth/signup.",
        tags: ["Auth"]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: true,
        content: new OA\JsonContent(
            required: ["email", "code", "recaptchaToken", "recaptchaSiteKey"],
            properties: [
                new OA\Property(
                    property: "email",
                    type: "string",
                    format: "email",
                    description: "Email do usuário"
                ),
                new OA\Property(
                    property: "code",
                    type: "string",
                    description: "Código de ativação de conta enviado no e-mail do usuário"
                ),
                new OA\Property(
                    property: "recaptchaToken",
                    type: "string",
                    description: "Token do Google reCAPTCHA"
                ),
                new OA\Property(
                    property: "recaptchaSiteKey",
                    type: "string",
                    description: "Site key do Google reCAPTCHA"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 204,
        description: "Conta ativada com sucesso"
    )]
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

    #[OA\Post(
        path: "/auth/forgot_password",
        summary: "Realiza o envio do código de redefinição de senha de conta padrão (email e senha) por e-mail",
        description: "Endpoint responsável por realizar o envio código de redefinição de senha de conta padrão por e-mail",
        tags: ["Auth"]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: true,
        content: new OA\JsonContent(
            required: ["email", "recaptchaToken", "recaptchaSiteKey"],
            properties: [
                new OA\Property(
                    property: "email",
                    type: "string",
                    format: "email",
                    description: "Email do usuário"
                ),
                new OA\Property(
                    property: "recaptchaToken",
                    type: "string",
                    description: "Token do Google reCAPTCHA"
                ),
                new OA\Property(
                    property: "recaptchaSiteKey",
                    type: "string",
                    description: "Site key do Google reCAPTCHA"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Código de confirmação enviado com sucesso",
        content: new OA\JsonContent(
            example: '{
                        "status": "success",
                        "message": "Se o e-mail informado estiver correto, você receberá em breve as instruções para redefinir sua senha.",
                        "data": {
                            "expirationInHours": 2
                        }
                    }'
        )
    )]
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

    #[OA\Post(
        path: "/auth/reset_password",
        summary: "Realiza a alteração de senha de conta padrão (email e senha)",
        description: "Endpoint responsável por realizar a alteração de senha de conta padrão. Este endpoint conclui a ação iniciada por /auth/forgot_password.",
        tags: ["Auth"]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: true,
        content: new OA\JsonContent(
            required: ["email", "code", "password", "recaptchaToken", "recaptchaSiteKey"],
            properties: [
                new OA\Property(
                    property: "email",
                    type: "string",
                    format: "email",
                    description: "Email do usuário"
                ),
                new OA\Property(
                    property: "code",
                    type: "string",
                    description: "Código de redefinição de senha enviado no e-mail do usuário"
                ),
                new OA\Property(
                    property: "password",
                    type: "string",
                    description: "Nova senha do usuário"
                ),
                new OA\Property(
                    property: "recaptchaToken",
                    type: "string",
                    description: "Token do Google reCAPTCHA"
                ),
                new OA\Property(
                    property: "recaptchaSiteKey",
                    type: "string",
                    description: "Site key do Google reCAPTCHA"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 204,
        description: "Senha redefinida com sucesso"
    )]
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

    #[OA\Post(
        path: "/auth/login",
        summary: "Realiza o login do usuário com conta padrão (email e senha)",
        description: "Endpoint responsável por realizar o login e retornar um token de autenticação",
        tags: ["Auth"]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: true,
        content: new OA\JsonContent(
            required: ["email", "password", "recaptchaToken", "recaptchaSiteKey"],
            properties: [
                new OA\Property(
                    property: "email",
                    type: "string",
                    format: "email",
                    description: "Email do usuário"
                ),
                new OA\Property(
                    property: "password",
                    type: "string",
                    description: "Senha do usuário"
                ),
                new OA\Property(
                    property: "recaptchaToken",
                    type: "string",
                    description: "Token do Google reCAPTCHA"
                ),
                new OA\Property(
                    property: "recaptchaSiteKey",
                    type: "string",
                    description: "Site key do Google reCAPTCHA"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Login bem sucedido",
        content: new OA\JsonContent(
            example: '{
                        "status": "success",
                        "message": "Login realizado com sucesso.",
                        "data": {
                            "token": "string-com-token-de-autenticacao",
                            "refreshToken": "string-com-token-de-atualizacao"
                        }
                    }'
        )
    )]
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

    #[OA\Post(
        path: "/auth/refresh_token",
        summary: "Realiza a atualização do token do usuário utilizando o refreshToken",
        description: "Endpoint responsável por realizar a atualização do token do usuário e retornar um novo token de autenticação",
        tags: ["Auth"]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: true,
        content: new OA\JsonContent(
            required: ["refreshToken"],
            properties: [
                new OA\Property(
                    property: "refreshToken",
                    type: "string",
                    description: "RefreshToken do usuário"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Atualização do token bem sucedida",
        content: new OA\JsonContent(
            example: '{
                        "status": "success",
                        "message": "Token atualizado realizado com sucesso.",
                        "data": {
                            "token": "string-com-token-de-autenticacao",
                            "refreshToken": "string-com-token-de-atualizacao"
                        }
                    }'
        )
    )]
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

    #[OA\Get(
        path: "/auth/is_logged_in",
        summary: "Realiza a checagem se o usuário está autenticado e seu token é válido",
        description: "Realiza a checagem se o usuário está autenticado e seu token é válido, se tudo correr bem atualiza o seu último acesso.",
        tags: ["Auth"],
        security: [["BearerAuth" => []]]
    )]
    #[OA\Response(
        response: 204,
        description: "Conta autenticada e token ativo"
    )]
    public function isLoggedIn(Request $request, Response $response): Response
    {
        $this->authService->isLoggedIn(
            $request->getAttribute('user')?->sub?->id ?? null
        );
        return JsonResponse::successNoContent($response);
    }

    //google
    #[OA\Post(
        path: "/auth/google/signup",
        summary: "Realiza a criação de conta de usuário com conta Google/Firebase",
        description: "Endpoint responsável por realizar a criação de conta de usuário Google/Firebase já ativada e autenticada (token de login)",
        tags: ["Auth"]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: true,
        content: new OA\JsonContent(
            required: [
                "idTokenFirebase",
                "name",
                "lastname",
                "isTerms",
                "isPolicy",
                "recaptchaToken",
                "recaptchaSiteKey"
            ],
            properties: [
                new OA\Property(
                    property: "idTokenFirebase",
                    type: "string",
                    description: "Token Firebase do usuário"
                ),
                new OA\Property(
                    property: "name",
                    type: "string",
                    description: "Nome do usuário"
                ),
                new OA\Property(
                    property: "lastname",
                    type: "string",
                    description: "Sobrenome do usuário"
                ),
                new OA\Property(
                    property: "isTerms",
                    type: "boolean",
                    description: "Aceito os termos da aplicação"
                ),
                new OA\Property(
                    property: "isPolicy",
                    type: "boolean",
                    description: "Aceito a política de privacidade da aplicação"
                ),
                new OA\Property(
                    property: "recaptchaToken",
                    type: "string",
                    description: "Token do Google reCAPTCHA"
                ),
                new OA\Property(
                    property: "recaptchaSiteKey",
                    type: "string",
                    description: "Site key do Google reCAPTCHA"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "Criação bem sucedida",
        content: new OA\JsonContent(
            example: '{
                        "status": "success",
                        "message": "Conta criada e logada com sucesso.",
                        "data": {
                            "token": "string-com-token-de-autenticacao",
                            "refreshToken": "string-com-token-de-atualizacao"
                        }
                    }'
        )
    )]
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

    #[OA\Post(
        path: "/auth/google/login",
        summary: "Realiza o login do usuário com conta Google/Firebase",
        description: "Endpoint responsável por realizar o login e retornar um token de autenticação",
        tags: ["Auth"]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: true,
        content: new OA\JsonContent(
            required: ["idTokenFirebase", "recaptchaToken", "recaptchaSiteKey"],
            properties: [
                new OA\Property(
                    property: "idTokenFirebase",
                    type: "string",
                    description: "Token Firebase do usuário"
                ),
                new OA\Property(
                    property: "recaptchaToken",
                    type: "string",
                    description: "Token do Google reCAPTCHA"
                ),
                new OA\Property(
                    property: "recaptchaSiteKey",
                    type: "string",
                    description: "Site key do Google reCAPTCHA"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Login bem sucedido",
        content: new OA\JsonContent(
            example: '{
                        "status": "success",
                        "message": "Login realizado com sucesso.",
                        "data": {
                            "token": "string-com-token-de-autenticacao",
                            "refreshToken": "string-com-token-de-atualizacao"
                        }
                    }'
        )
    )]
    public function loginGoogle(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents(); // Obtém o conteúdo cru do corpo da requisição
        $data = json_decode($body, true); // Decodifica o JSON manualmente

        $token = $this->authService->loginGoogle(
            $data['idTokenFirebase'] ?? '',
            $data['recaptchaToken'] ?? '',
            $data['recaptchaSiteKey'] ?? ''
        );

        return JsonResponse::success(
            $response,
            "Login realizado com sucesso.",
            $token
        );
    }
}