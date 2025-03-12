<?php

namespace App\Repositories;

use PDO;
use Ramsey\Uuid\Uuid;

class UserRepository
{
    public function __construct(private PDO $db)
    {}

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


    public function create(
        string $nome,
        string $sobrenome,
        string $email,
        string $hashSenha,
        string $hashResetCode,
        string $resetCodeExpiry
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
            $stmt->bindParam(':reset_code_expiry', $resetCodeExpiry);
            $stmt->execute();

            $this->db->commit();

            return $uuid;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}