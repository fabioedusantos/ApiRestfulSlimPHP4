<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerErrorException;
use App\Helpers\Util;
use App\Repositories\UserRepository;

class UserService
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function get(string $userId): array
    {
        $user = $this->userRepository->getByUserId($userId);
        if (empty($user)) {
            throw new BadRequestException("Sem conteúdo.");
        }

        if (!empty($user['photo_blob'])) {
            $user['photo_blob'] = base64_encode($user['photo_blob']);
        }
        return [
            'nome' => $user['nome'],
            'sobrenome' => $user['sobrenome'],
            'email' => $user['email'],
            'photoBlob' => $user['photo_blob'],
            'ultimoAcesso' => $user['penultimo_acesso'],
            'criadoEm' => $user['criado_em'],
            'alteradoEm' => $user['alterado_em'],
            'isContaGoogle' => !empty($user['firebase_uid']),
        ];
    }
}