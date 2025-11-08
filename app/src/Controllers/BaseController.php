<?php

namespace App\Controllers;

use App\Helpers\JsonResponse;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use OpenApi\Attributes as OA;

class BaseController
{
    #[OA\Get(
        path: "/",
        summary: "Verifica o status da API",
        description: "Endpoint usado para checar se a API está em funcionamento",
        tags: ["Status"]
    )]
    #[OA\Response(
        response: 200,
        description: "API operacional",
        content: new OA\JsonContent(
            example: '{"status":"success","message":"API on!"}'
        )
    )]
    public function checkApi(Request $request, Response $response): Response{
        return JsonResponse::success($response, "API on!");
    }
}