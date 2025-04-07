<?php

namespace Tests\Repositories;

use App\Repositories\UserRepository;
use DateTime;
use Kreait\Firebase\Auth\UserRecord;
use Mockery;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\DbFixture;
use Tests\Fixtures\UserFixture;

class UserRepositoryTest extends TestCase
{
    use UserFixture;
    use DbFixture;

    private PDO $pdo;
    private UserRepository $userRepository;
    private array $userData;
    private UserRecord $firebaseUserData;
    private int $horasExpirarConfirmacaoSenha = 2;

    protected function setUp(): void
    {
        $this->userData = $this->getUserData();
        $this->firebaseUserData = $this->getFirebaseUserData();

        try {
            $this->pdo = $this->getTestDatabase();
        } catch (PDOException $e) {
            $this->fail("Não foi possível conectar ao banco de dados de teste: " . $e->getMessage());
        }

        $this->userRepository = new UserRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function generateExpirationTime(): string
    {
        $expiry = new DateTime("+{$this->horasExpirarConfirmacaoSenha} hour");
        return $expiry->format('Y-m-d H:i:s');
    }

    public function testCreateSucesso(): string
    {
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $email = $this->userData['email'];
        $hashSenha = $this->userData['senha'];
        $hashResetCode = password_hash("123456", PASSWORD_BCRYPT);
        $resetCodeExpiry = $this->generateExpirationTime();

        $newUserId = $this->userRepository->create(
            $nome,
            $sobrenome,
            $email,
            $hashSenha,
            $hashResetCode,
            $resetCodeExpiry,
        );

        $this->assertNotEmpty($newUserId);
        $this->assertIsString($newUserId);

        $userFromDb = $this->userRepository->getByEmailWithPasswordReset($email);
        $this->assertEquals($newUserId, $userFromDb['id']);
        $this->assertEquals($nome, $userFromDb['nome']);
        $this->assertEquals($sobrenome, $userFromDb['sobrenome']);
        $this->assertEquals($email, $userFromDb['email']);
        $this->assertEquals($hashSenha, $userFromDb['senha']);
        $this->assertEquals($hashResetCode, $userFromDb['reset_code']);
        $this->assertEquals($resetCodeExpiry, $userFromDb['reset_code_expiry']);

        return $newUserId;
    }

    public function testCreateFalhaEmailDuplicado(): void
    {
        $this->testCreateSucesso();

        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $email = $this->userData['email'];
        $hashSenha = $this->userData['senha'];
        $hashResetCode = password_hash("123456", PASSWORD_BCRYPT);
        $resetCodeExpiry = $this->generateExpirationTime();

        $this->expectExceptionMessage("Integrity constraint violation: 1062 Duplicate");

        $this->userRepository->create(
            $nome,
            $sobrenome,
            $email,
            $hashSenha,
            $hashResetCode,
            $resetCodeExpiry,
        );
    }

    public function testGetByEmailSucesso(): void
    {
        $this->testCreateSucesso();

        $user = $this->userRepository->getByEmail(
            $this->userData['email']
        );

        $this->assertNotEmpty($user);
        $this->assertIsArray($user);

        $this->assertArrayHasKey('id', $user);
        $this->assertNotEmpty($user['id']);
        $this->assertIsString($user['id']);

        $this->assertArrayHasKey('nome', $user);
        $this->assertNotEmpty($user['nome']);
        $this->assertIsString($user['nome']);

        $this->assertArrayHasKey('sobrenome', $user);
        $this->assertNotEmpty($user['sobrenome']);
        $this->assertIsString($user['sobrenome']);

        $this->assertArrayHasKey('photo_blob', $user);

        $this->assertArrayHasKey('email', $user);
        $this->assertNotEmpty($user['email']);
        $this->assertIsString($user['email']);

        $this->assertArrayHasKey('senha', $user);
        $this->assertNotEmpty($user['senha']);
        $this->assertIsString($user['senha']);

        $this->assertArrayHasKey('firebase_uid', $user);

        $this->assertArrayHasKey('termos_aceito_em', $user);
        $this->assertNotEmpty($user['termos_aceito_em']);
        $this->assertIsString($user['termos_aceito_em']);

        $this->assertArrayHasKey('politica_aceita_em', $user);
        $this->assertNotEmpty($user['politica_aceita_em']);
        $this->assertIsString($user['politica_aceita_em']);

        $this->assertArrayHasKey('is_active', $user);
        $this->assertEquals(0, $user['is_active']);
        $this->assertIsNumeric($user['is_active']);

        $this->assertArrayHasKey('penultimo_acesso', $user);

        $this->assertArrayHasKey('ultimo_acesso', $user);

        $this->assertArrayHasKey('criado_em', $user);
        $this->assertNotEmpty($user['criado_em']);
        $this->assertIsString($user['criado_em']);

        $this->assertArrayHasKey('alterado_em', $user);
    }

    public function testGetByEmailWithPasswordResetSucesso(): void
    {
        $this->testCreateSucesso();

        $user = $this->userRepository->getByEmailWithPasswordReset(
            $this->userData['email']
        );

        $this->assertNotEmpty($user);
        $this->assertIsArray($user);

        $this->assertArrayHasKey('id', $user);
        $this->assertNotEmpty($user['id']);
        $this->assertIsString($user['id']);

        $this->assertArrayHasKey('nome', $user);
        $this->assertNotEmpty($user['nome']);
        $this->assertIsString($user['nome']);

        $this->assertArrayHasKey('sobrenome', $user);
        $this->assertNotEmpty($user['sobrenome']);
        $this->assertIsString($user['sobrenome']);

        $this->assertArrayHasKey('photo_blob', $user);

        $this->assertArrayHasKey('email', $user);
        $this->assertNotEmpty($user['email']);
        $this->assertIsString($user['email']);

        $this->assertArrayHasKey('senha', $user);
        $this->assertNotEmpty($user['senha']);
        $this->assertIsString($user['senha']);

        $this->assertArrayHasKey('firebase_uid', $user);

        $this->assertArrayHasKey('termos_aceito_em', $user);
        $this->assertNotEmpty($user['termos_aceito_em']);
        $this->assertIsString($user['termos_aceito_em']);

        $this->assertArrayHasKey('politica_aceita_em', $user);
        $this->assertNotEmpty($user['politica_aceita_em']);
        $this->assertIsString($user['politica_aceita_em']);

        $this->assertArrayHasKey('is_active', $user);
        $this->assertEquals(0, $user['is_active']);
        $this->assertIsNumeric($user['is_active']);

        $this->assertArrayHasKey('penultimo_acesso', $user);

        $this->assertArrayHasKey('ultimo_acesso', $user);

        $this->assertArrayHasKey('criado_em', $user);
        $this->assertNotEmpty($user['criado_em']);
        $this->assertIsString($user['criado_em']);

        $this->assertArrayHasKey('alterado_em', $user);

        $this->assertArrayHasKey('reset_code', $user);
        $this->assertNotEmpty($user['reset_code']);
        $this->assertIsString($user['reset_code']);

        $this->assertArrayHasKey('reset_code', $user);
        $this->assertNotEmpty($user['reset_code']);
        $this->assertIsString($user['reset_code_expiry']);
    }
}