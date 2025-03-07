<?php

namespace App\Controllers;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

class BaseController
{
    public function checkApi(Request $request, Response $response): Response{
        $payload = [
            'status' => 'success',
            'message' => "API on!"
        ];
        $response->getBody()->write(json_encode($payload));
        return $response
            ->withStatus(200)
            ->withHeader("Content-Type", "application/json");
    }
}