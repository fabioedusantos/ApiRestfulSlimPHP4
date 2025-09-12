<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerErrorException;
use App\Helpers\EnvHelper;
use App\Helpers\PhotoHelper;
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

    public function set(
        string $userId,
        string $nome,
        string $sobrenome,
        string $senha,
        string $photoBase64,
        bool $isRemovePhoto
    ): void {
        $user = $this->userRepository->getByUserId($userId);
        if (empty($user)) {
            throw new BadRequestException("Usuário não existe.");
        }

        $isGoogleAccount = !empty($user['firebase_uid']);

        if (!empty($nome)) {
            if (mb_strlen($nome) < 2) {
                throw new BadRequestException("Nome muito curto.");
            }

            if (mb_strlen($sobrenome) < 2) {
                throw new BadRequestException("Sobrenome muito curto.");
            }
        } else {
            $nome = $sobrenome = null;
        }

        if (!$isGoogleAccount && !empty($senha)) {
            if (strlen($senha) < 8 ||
                !preg_match('/[A-Z]/', $senha) ||       // Pelo menos uma letra maiúscula
                !preg_match('/[0-9]/', $senha) ||       // Pelo menos um número
                !preg_match('/[\W]/', $senha)           // Pelo menos um caractere especial
            ) {
                throw new BadRequestException(
                    "A senha deve ter no mínimo 8 caracteres, com pelo menos uma letra maiúscula, um número e um caractere especial."
                );
            }
            $senha = password_hash($senha, PASSWORD_BCRYPT);
        } else {
            $senha = null;
        }

        $photoBlob = null;
        if (!$isGoogleAccount && !$isRemovePhoto && !empty($photoBase64)) {
            try {
                $photoBlob = PhotoHelper::photoBase64ToBlob($photoBase64);
            } catch (\Exception $e) {
                throw new BadRequestException("Não foi possível processar a imagem: " . $e->getMessage(), 0, $e);
            }
        }

        if (!empty($nome) || !empty($sobrenome) || !empty($senha) || !empty($photoBase64) || $isRemovePhoto) {
            if (!$this->userRepository->updateProfile($userId, $nome, $sobrenome, $senha, $photoBlob, $isRemovePhoto)) {
                throw new InternalServerErrorException("Não foi possível atualizar perfil. Tente novamente.");
            }
        }
    }
}