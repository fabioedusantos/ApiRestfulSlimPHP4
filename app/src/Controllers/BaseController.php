<?php

namespace App\Controllers;

use App\Helpers\JsonResponse;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class BaseController
{
    public function checkApi(Request $request, Response $response): Response{
        return JsonResponse::success($response, "API on!");
    }
}