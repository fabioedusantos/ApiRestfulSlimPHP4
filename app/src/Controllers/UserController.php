<?php

namespace App\Controllers;

use App\Helpers\JsonResponse;
use App\Services\UserService;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class UserController
{
    public function __construct(private UserService $userService)
    {
    }

    public function get(Request $request, Response $response): Response
    {
        $user = $this->userService->get($request->getAttribute('user')?->sub?->id ?? null);
        return JsonResponse::success($response, "Sucesso.", $user);
    }

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