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
}