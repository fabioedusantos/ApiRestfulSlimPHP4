<?php

namespace App\Repositories;

use PDO;
use Ramsey\Uuid\Uuid;

class UserRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function getByEmail(string $email): ?array
    {
        $sql = "SELECT 
                    id,
                    nome,
                    sobrenome,
                    photo_blob,
                    email,
                    senha,
                    firebase_uid,
                    termos_aceito_em,
                    politica_aceita_em,
                    is_active,
                    penultimo_acesso,
                    ultimo_acesso,
                    criado_em,
                    alterado_em
                FROM users WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    public function getByEmailWithPasswordReset(string $email): ?array
    {
        $sql = "SELECT 
                    u.id,
                    u.nome,
                    u.sobrenome,
                    u.photo_blob,
                    u.email,
                    u.senha,
                    u.firebase_uid,
                    u.termos_aceito_em,
                    u.politica_aceita_em,
                    u.is_active,
                    u.penultimo_acesso,
                    u.ultimo_acesso,
                    u.criado_em,
                    u.alterado_em,
                    p.reset_code, 
                    p.reset_code_expiry
                FROM 
                    users AS u
                INNER JOIN user_password_resets as p ON p.user_id = u.id
                WHERE 
                    u.email = :email 
                    AND p.reset_code IS NOT NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $user = $stmt->fetch();
        return !empty($user['id']) ? $user : null;
    }

    public function getByFirebaseUid(string $firebaseUid): ?array
    {
        $sql = "SELECT 
                    id,
                    nome,
                    sobrenome,
                    photo_blob,
                    email,
                    senha,
                    firebase_uid,
                    termos_aceito_em,
                    politica_aceita_em,
                    is_active,
                    penultimo_acesso,
                    ultimo_acesso,
                    criado_em,
                    alterado_em
                FROM users WHERE firebase_uid = :firebase_uid";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':firebase_uid', $firebaseUid);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    public function isActive(string $userId): bool
    {
        $sql = "SELECT 
                    id
                FROM users WHERE id = :id AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        $user = $stmt->fetch();
        return !empty($user['id']);
    }

    public function create(
        string $nome,
        string $sobrenome,
        string $email,
        string $hashSenha,
        string $hashResetCode,
        string $validadeResetCode
    ): string {
        try {
            $uuid = Uuid::uuid4()->toString();

            $this->db->beginTransaction();

            $sql = "INSERT INTO users (id, nome, sobrenome, email, senha, termos_aceito_em, politica_aceita_em)
                VALUES (:id, :nome, :sobrenome, :email, :senha, NOW(), NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $uuid);
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':sobrenome', $sobrenome);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':senha', $hashSenha);
            $stmt->execute();

            $sql = "INSERT INTO user_password_resets (user_id, reset_code, reset_code_expiry) 
                VALUES (:id, :reset_code, :reset_code_expiry)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $uuid);
            $stmt->bindParam(':reset_code', $hashResetCode);
            $stmt->bindParam(':reset_code_expiry', $validadeResetCode);
            $stmt->execute();

            $this->db->commit();

            return $uuid;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    function updateResetCode(string $userId, string $hashResetCode, string $validadeResetCode): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE user_password_resets SET reset_code = :code, reset_code_expiry = :expiry WHERE user_id = :id"
        );
        return $stmt->execute([
            'code' => $hashResetCode,
            'expiry' => $validadeResetCode,
            'id' => $userId
        ]);
    }

    public function activate(string $userId): void
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "
                UPDATE users 
                SET is_active = 1 
                WHERE id = :id
            "
            );
            $stmt->bindParam(':id', $userId);
            $stmt->execute();

            $stmt = $this->db->prepare(
                "
                UPDATE user_password_resets 
                SET reset_code = NULL, reset_code_expiry = NULL
                WHERE user_id = :id
            "
            );
            $stmt->bindParam(':id', $userId);
            $stmt->execute();

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updatePassword(string $userId, string $hashSenha): void
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "
            UPDATE users 
            SET senha = :senha, is_active = 1, alterado_em = NOW()
            WHERE id = :id
        "
            );
            $stmt->bindParam(':senha', $hashSenha);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();

            $stmt = $this->db->prepare(
                "
            UPDATE user_password_resets 
            SET reset_code = NULL, reset_code_expiry = NULL
            WHERE user_id = :user_id
        "
            );
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateUltimoAcesso(string $userId): bool
    {
        $stmt = $this->db->prepare(
            "
                UPDATE users 
                SET
                    penultimo_acesso = ultimo_acesso,
                    ultimo_acesso = NOW()
                WHERE id = :id
            "
        );
        $stmt->bindParam(':id', $userId);
        return $stmt->execute();
    }

    public function createByGoogle(
        string $nome,
        string $sobrenome,
        ?string $photoBlob,
        string $email,
        string $firebaseUid
    ): ?string {
        $uuid = Uuid::uuid4()->toString();

        $sql = "INSERT INTO users (id, nome, sobrenome, photo_blob, email, firebase_uid, termos_aceito_em, 
                    politica_aceita_em, is_active)
                VALUES (:id, :nome, :sobrenome, :photo_blob, :email, :firebase_uid, NOW(), NOW(), 1)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $uuid);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':sobrenome', $sobrenome);
        $stmt->bindParam(':photo_blob', $photoBlob, PDO::PARAM_LOB);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':firebase_uid', $firebaseUid);

        return $stmt->execute() ? $uuid : null;
    }

    public function updatePhotoBlob(
        string $userId,
        string $photoBlob
    ): bool {
        $stmt = $this->db->prepare(
            "
                UPDATE users 
                SET photo_blob = :photo_blob
                WHERE id = :id
            "
        );
        $stmt->bindParam(':photo_blob', $photoBlob, PDO::PARAM_LOB);
        $stmt->bindParam(':id', $userId);
        return $stmt->execute();
    }
}