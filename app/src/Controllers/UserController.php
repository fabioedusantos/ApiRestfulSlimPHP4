<?php

namespace App\Controllers;

use App\Helpers\JsonResponse;
use App\Services\UserService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use OpenApi\Attributes as OA;

class UserController
{
    public function __construct(private UserService $userService)
    {
    }

    #[OA\Get(
        path: "/users/me",
        summary: "Retorna os dados do usuário logado",
        description: "Retorna os dados do usuário logado",
        tags: ["Users"],
        security: [["BearerAuth" => []]]
    )]
    #[OA\Response(
        response: 200,
        description: "Dados do usuário",
        content: new OA\JsonContent(
            example: '{
                        "status": "success",
                        "message": "Sucesso.",
                        "data": {
                            "nome": "José",
                            "sobrenome": "Silva",
                            "email": "jSilva@gmail.com",
                            "photoBlob": null,
                            "ultimoAcesso": "2025-10-09 18:03:23",
                            "criadoEm": "2025-10-09 14:32:15",
                            "alteradoEm": "2025-10-09 14:32:15",
                            "isContaGoogle": false
                        }
                    }'
        )
    )]
    public function get(Request $request, Response $response): Response
    {
        $user = $this->userService->get($request->getAttribute('user')?->sub?->id ?? null);
        return JsonResponse::success($response, "Sucesso.", $user);
    }

    #[OA\Patch(
        path: "/users/me",
        summary: "Atualiza os dados do usuário logado",
        description: "Atualiza as informações do usuário autenticado. Nenhum campo do corpo da requisição é obrigatório; é possível atualizar apenas alguns dados, como o nome, sobrenome ou senha, conforme necessário. Você pode alterar um ou mais campos sem a necessidade de atualizar todos.",
        tags: ["Users"],
        security: [["BearerAuth" => []]]
    )]
    #[OA\RequestBody(
        description: "Corpo da requisição",
        required: false,
        content: new OA\JsonContent(
            required: ["name", "lastname", "password", "photoBlob", "isRemovePhoto"],
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
                    property: "password",
                    type: "string",
                    description: "Senha do usuário"
                ),
                new OA\Property(
                    property: "photoBlob",
                    type: "string",
                    description: "Foto do usuário blob em base64"
                ),
                new OA\Property(
                    property: "isRemovePhoto",
                    type: "boolean",
                    description: "Se true remove a foto do usuário"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 204,
        description: "Dados do usuário atualizados com sucesso"
    )]
    public function set(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        $this->userService->set(
            $request->getAttribute('user')?->sub?->id ?? null,
            $data['name'] ?? '',
            $data['lastname'] ?? '',
            $data['password'] ?? '',
            $data['photoBlob'] ?? '',
            $data['isRemovePhoto'] ?? false
        );

        return JsonResponse::successNoContent($response);
    }
}