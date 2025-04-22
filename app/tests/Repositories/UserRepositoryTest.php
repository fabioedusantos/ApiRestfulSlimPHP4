<?php

namespace Tests\Repositories;

use App\Helpers\Util;
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

    public function testCreateGoogleSucesso(): string
    {
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $photoBlob = Util::urlFotoToBlob($this->firebaseUserData->photoUrl);
        $email = $this->userData['email'];
        $firebaseUid = $this->firebaseUserData->uid;

        $newUserId = $this->userRepository->createByGoogle(
            $nome,
            $sobrenome,
            $photoBlob,
            $email,
            $firebaseUid
        );

        $this->assertNotEmpty($newUserId);
        $this->assertIsString($newUserId);

        $userFromDb = $this->userRepository->getByUserId($newUserId);
        $this->assertEquals($newUserId, $userFromDb['id']);
        $this->assertEquals($nome, $userFromDb['nome']);
        $this->assertEquals($sobrenome, $userFromDb['sobrenome']);
        $this->assertEquals($photoBlob, $userFromDb['photo_blob']);
        $this->assertEquals($email, $userFromDb['email']);
        $this->assertEquals($firebaseUid, $userFromDb['firebase_uid']);

        return $newUserId;
    }

    public function testCreateGoogleFalhaEmailDuplicado(): void
    {
        $this->testCreateGoogleSucesso();

        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $photoBlob = Util::urlFotoToBlob($this->firebaseUserData->photoUrl);
        $email = $this->userData['email'];
        $firebaseUid = $this->firebaseUserData->uid;

        $this->expectExceptionMessage("Integrity constraint violation: 1062 Duplicate");

        $this->userRepository->createByGoogle(
            $nome,
            $sobrenome,
            $photoBlob,
            $email,
            $firebaseUid
        );
    }

    public function testCreateGoogleFirebaseUidDuplicado(): void
    {
        $this->testCreateGoogleSucesso();

        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $photoBlob = Util::urlFotoToBlob($this->firebaseUserData->photoUrl);
        $email = $this->userData['email'] . ".br";
        $firebaseUid = $this->firebaseUserData->uid;

        $this->expectExceptionMessage("Integrity constraint violation: 1062 Duplicate entry");

        $this->userRepository->createByGoogle(
            $nome,
            $sobrenome,
            $photoBlob,
            $email,
            $firebaseUid
        );
    }

    public function testGetByFirebaseUidSucesso(): void
    {
        $this->testCreateGoogleSucesso();

        $user = $this->userRepository->getByFirebaseUid(
            $this->userData['firebase_uid']
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
        $this->assertEmpty($user['senha']);

        $this->assertArrayHasKey('firebase_uid', $user);

        $this->assertArrayHasKey('termos_aceito_em', $user);
        $this->assertNotEmpty($user['termos_aceito_em']);
        $this->assertIsString($user['termos_aceito_em']);

        $this->assertArrayHasKey('politica_aceita_em', $user);
        $this->assertNotEmpty($user['politica_aceita_em']);
        $this->assertIsString($user['politica_aceita_em']);

        $this->assertArrayHasKey('is_active', $user);
        $this->assertEquals(1, $user['is_active']);
        $this->assertIsNumeric($user['is_active']);

        $this->assertArrayHasKey('penultimo_acesso', $user);

        $this->assertArrayHasKey('ultimo_acesso', $user);

        $this->assertArrayHasKey('criado_em', $user);
        $this->assertNotEmpty($user['criado_em']);
        $this->assertIsString($user['criado_em']);

        $this->assertArrayHasKey('alterado_em', $user);
    }

    public function testGetByUserIdSucesso(): void
    {
        $userId = $this->testCreateSucesso();

        $user = $this->userRepository->getByUserId(
            $userId
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

    public function testIsActiveSucesso(): void
    {
        $userId = $this->testCreateGoogleSucesso();

        $isSuccess = $this->userRepository->isActive(
            $userId
        );

        $this->assertNotEmpty($isSuccess);
        $this->assertIsBool($isSuccess);
        $this->assertTrue($isSuccess);
    }

    public function testIsActiveFalhaUsuarioInativo(): void
    {
        $userId = $this->testCreateSucesso();

        $isSuccess = $this->userRepository->isActive(
            $userId
        );

        $this->assertIsBool($isSuccess);
        $this->assertFalse($isSuccess);
    }

    public function testUpdateResetCodeSucesso(): void
    {
        $userId = $this->testCreateSucesso();
        $hashResetCode = password_hash("123456", PASSWORD_BCRYPT);
        $resetCodeExpiry = $this->generateExpirationTime();

        $isSuccess = $this->userRepository->updateResetCode(
            $userId,
            $hashResetCode,
            $resetCodeExpiry,
        );

        $this->assertIsBool($isSuccess);
        $this->assertTrue($isSuccess);

        $userFromDb = $this->userRepository->getByEmailWithPasswordReset($this->userData['email']);
        $this->assertEquals($hashResetCode, $userFromDb['reset_code']);
        $this->assertEquals($resetCodeExpiry, $userFromDb['reset_code_expiry']);
    }

    public function testActivateSucesso(): void
    {
        $userId = $this->testCreateSucesso();
        $userFromDb = $this->userRepository->getByUserId($userId);
        $this->assertEquals(0, $userFromDb['is_active']);

        $this->userRepository->activate(
            $userId
        );

        $userFromDb = $this->userRepository->getByUserId($userId);
        $this->assertEquals(1, $userFromDb['is_active']);
    }

    public function testUpdatePasswordSucesso(): void
    {
        $userId = $this->testCreateSucesso();
        $hashSenha = password_hash("JABULANI@123!@#", PASSWORD_BCRYPT);

        $this->userRepository->updatePassword(
            $userId,
            $hashSenha
        );

        $userFromDb = $this->userRepository->getByUserId($userId);
        $this->assertEquals($hashSenha, $userFromDb['senha']);
    }

    public function testUpdateUltimoAcessoSucesso(): void
    {
        $userId = $this->testCreateSucesso();

        $userFromDb = $this->userRepository->getByUserId($userId);
        $this->assertEmpty($userFromDb['ultimo_acesso']);

        $isSuccess = $this->userRepository->updateUltimoAcesso(
            $userId
        );

        $this->assertIsBool($isSuccess);
        $this->assertTrue($isSuccess);

        $userFromDb = $this->userRepository->getByUserId($userId);
        $this->assertNotEmpty($userFromDb['ultimo_acesso']);
    }

    public function testUpdatePhotoBlobSucesso(): string
    {
        $userId = $this->testCreateSucesso();
        $photoBlob = Util::urlFotoToBlob($this->firebaseUserData->photoUrl);

        $userFromDb = $this->userRepository->getByUserId($userId);
        $this->assertEmpty($userFromDb['photo_blob']);

        $isSuccess = $this->userRepository->updatePhotoBlob(
            $userId,
            $photoBlob
        );

        $this->assertIsBool($isSuccess);
        $this->assertTrue($isSuccess);

        $userFromDb = $this->userRepository->getByUserId($userId);
        $this->assertEquals($photoBlob, $userFromDb['photo_blob']);

        return $userId;
    }
}