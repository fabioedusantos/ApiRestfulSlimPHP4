<?php

namespace Tests\Services;

use App\Helpers\Valid;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Mockery;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\UserFixture;

#[RunTestsInSeparateProcesses] //aplicando para rodar cada teste em um processo separado, necessário para o Mockery overload funcionar corretamente
class UserServiceTest extends TestCase
{
    use UserFixture;

    private UserRepository $userRepository;
    private UserService $userService;
    private array $userData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userData = $this->getUserData();

        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userService = new UserService($this->userRepository);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->userData['is_active'] = 1;
        Mockery::close();
    }


    //get()
    public function testGetSucesso(): void
    {
        $this->userRepository->expects($this->once())
            ->method('getByUserId')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn($this->userData);

        $result = $this->userService->get(
            $this->userData['id']
        );

        // Assert básico: retornou array
        $this->assertIsArray($result);

        $this->assertArrayHasKey('nome', $result);
        $this->assertArrayHasKey('sobrenome', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('photoBlob', $result);
        $this->assertArrayHasKey('ultimoAcesso', $result);
        $this->assertArrayHasKey('criadoEm', $result);
        $this->assertArrayHasKey('alteradoEm', $result);
        $this->assertArrayHasKey('isContaGoogle', $result);

        $this->assertNotEmpty($result['nome']);
        $this->assertNotEmpty($result['sobrenome']);
        $this->assertNotEmpty($result['email']);
        $this->assertNotEmpty($result['criadoEm']);
        $this->assertNotEmpty($result['isContaGoogle']);

        $this->assertIsString($result['nome']);
        $this->assertIsString($result['sobrenome']);
        $this->assertIsString($result['email']);
        if (!empty($result['photoBlob'])) {
            $this->assertIsString($result['photoBlob']);
        }

        if (!empty($result['ultimoAcesso'])) {
            $this->assertIsString($result['ultimoAcesso']);
            $this->assertTrue(Valid::isValidDateTime($result['ultimoAcesso']));
            $this->assertInstanceOf(\DateTime::class, new \DateTime($result['ultimoAcesso']));
        } else {
            $this->assertNull($result['ultimoAcesso']);
        }

        if (!empty($result['criadoEm'])) {
            $this->assertIsString($result['criadoEm']);
            $this->assertTrue(Valid::isValidDateTime($result['criadoEm']));
            $this->assertInstanceOf(\DateTime::class, new \DateTime($result['criadoEm']));
        } else {
            $this->assertNull($result['criadoEm']);
        }

        if (!empty($result['alteradoEm'])) {
            $this->assertIsString($result['alteradoEm']);
            $this->assertTrue(Valid::isValidDateTime($result['alteradoEm']));
            $this->assertInstanceOf(\DateTime::class, new \DateTime($result['alteradoEm']));
        } else {
            $this->assertNull($result['alteradoEm']);
        }

        $this->assertIsBool($result['isContaGoogle']);
    }

    public function testGetFalhaUsuarioNaoEncontrado(): void
    {
        $this->userRepository
            ->method('getByUserId')
            ->willReturn(null);

        $this->expectExceptionMessage("Sem conteúdo.");

        $this->userService->get(
            $this->userData['id']
        );
    }


    //set()
    public function testSetSucessoFirebase(): void
    {
        $userId = $this->userData['id'];
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $senha = (string)null;
        $photoBase64 = (string)null;
        $isRemovePhoto = false;

        $this->userRepository->expects($this->once())
            ->method('getByUserId')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateProfile')
            ->with(
                $this->equalTo($userId),
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->equalTo(null),
                $this->equalTo(null),
                $this->equalTo($isRemovePhoto)
            )
            ->willReturn(true);

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetSucessoContaNormalComNomeSobrenome(): void
    {
        $userId = $this->userData['id'];
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $senha = (string)null;
        $photoBase64 = (string)null;
        $isRemovePhoto = false;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository->expects($this->once())
            ->method('getByUserId')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateProfile')
            ->with(
                $this->equalTo($userId),
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->equalTo(null),
                $this->equalTo(null),
                $this->equalTo($isRemovePhoto)
            )
            ->willReturn(true);

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetSucessoContaNormalComNomeSobrenomeSenha(): void
    {
        $userId = $this->userData['id'];
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $senha = "Senha@123!";
        $photoBase64 = (string)null;
        $isRemovePhoto = false;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository->expects($this->once())
            ->method('getByUserId')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateProfile')
            ->with(
                $this->equalTo($userId),
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->callback(function ($senhaHash) use ($senha) {
                    return password_verify($senha, $senhaHash);
                }),
                $this->equalTo(null),
                $this->equalTo($isRemovePhoto)
            )
            ->willReturn(true);

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetSucessoContaNormalComNomeSobrenomeFoto(): void
    {
        $userId = $this->userData['id'];
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $senha = (string)null;
        $photoBase64 = base64_encode($this->userData['photo_blob']);
        $isRemovePhoto = false;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository->expects($this->once())
            ->method('getByUserId')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateProfile')
            ->with(
                $this->equalTo($userId),
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->equalTo(null),
                $this->equalTo($this->userData['photo_blob']),
                $this->equalTo($isRemovePhoto)
            )
            ->willReturn(true);

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetSucessoContaNormalComNomeSobrenomeRemoverFoto(): void
    {
        $userId = $this->userData['id'];
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $senha = (string)null;
        $photoBase64 = (string)null;
        $isRemovePhoto = true;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository->expects($this->once())
            ->method('getByUserId')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateProfile')
            ->with(
                $this->equalTo($userId),
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->equalTo(null),
                $this->equalTo(null),
                $this->equalTo($isRemovePhoto)
            )
            ->willReturn(true);

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetSucessoContaNormalComSenha(): void
    {
        $userId = $this->userData['id'];
        $nome = (string)null;
        $sobrenome = (string)null;
        $senha = "Senha@123!";
        $photoBase64 = (string)null;
        $isRemovePhoto = false;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository->expects($this->once())
            ->method('getByUserId')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateProfile')
            ->with(
                $this->equalTo($userId),
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->callback(function ($senhaHash) use ($senha) {
                    return password_verify($senha, $senhaHash);
                }),
                $this->equalTo(null),
                $this->equalTo($isRemovePhoto)
            )
            ->willReturn(true);

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetSucessoContaNormalComSenhaFoto(): void
    {
        $userId = $this->userData['id'];
        $nome = (string)null;
        $sobrenome = (string)null;
        $senha = "Senha@123!";
        $photoBase64 = base64_encode($this->userData['photo_blob']);
        $isRemovePhoto = false;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository->expects($this->once())
            ->method('getByUserId')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateProfile')
            ->with(
                $this->equalTo($userId),
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->callback(function ($senhaHash) use ($senha) {
                    return password_verify($senha, $senhaHash);
                }),
                $this->equalTo($this->userData['photo_blob']),
                $this->equalTo($isRemovePhoto)
            )
            ->willReturn(true);

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetSucessoContaNormalComSenhaRemoverFoto(): void
    {
        $userId = $this->userData['id'];
        $nome = (string)null;
        $sobrenome = (string)null;
        $senha = "Senha@123!";
        $photoBase64 = (string)null;
        $isRemovePhoto = true;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository->expects($this->once())
            ->method('getByUserId')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateProfile')
            ->with(
                $this->equalTo($userId),
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->callback(function ($senhaHash) use ($senha) {
                    return password_verify($senha, $senhaHash);
                }),
                $this->equalTo(null),
                $this->equalTo($isRemovePhoto)
            )
            ->willReturn(true);

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetSucessoContaNormalSomenteFoto(): void
    {
        $userId = $this->userData['id'];
        $nome = (string)null;
        $sobrenome = (string)null;
        $senha = (string)null;
        $photoBase64 = base64_encode($this->userData['photo_blob']);
        $isRemovePhoto = false;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository->expects($this->once())
            ->method('getByUserId')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateProfile')
            ->with(
                $this->equalTo($userId),
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->equalTo($senha),
                $this->equalTo($this->userData['photo_blob']),
                $this->equalTo($isRemovePhoto)
            )
            ->willReturn(true);

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetSucessoContaNormalRemoverFoto(): void
    {
        $userId = $this->userData['id'];
        $nome = (string)null;
        $sobrenome = (string)null;
        $senha = (string)null;
        $photoBase64 = (string)null;
        $isRemovePhoto = true;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository->expects($this->once())
            ->method('getByUserId')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateProfile')
            ->with(
                $this->equalTo($userId),
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->equalTo($senha),
                $this->equalTo(null),
                $this->equalTo($isRemovePhoto)
            )
            ->willReturn(true);

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetSucessoContaNormalComNomeSobrenomeSenhaFoto(): void
    {
        $userId = $this->userData['id'];
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $senha = "Senha@123!";
        $photoBase64 = base64_encode($this->userData['photo_blob']);
        $isRemovePhoto = false;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository->expects($this->once())
            ->method('getByUserId')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateProfile')
            ->with(
                $this->equalTo($userId),
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->callback(function ($senhaHash) use ($senha) {
                    return password_verify($senha, $senhaHash);
                }),
                $this->equalTo($this->userData['photo_blob']),
                $this->equalTo($isRemovePhoto)
            )
            ->willReturn(true);

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetFalhaUsuarioNaoExiste(): void
    {
        $userId = $this->userData['id'];
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $senha = "Senha@123!";
        $photoBase64 = base64_encode($this->userData['photo_blob']);
        $isRemovePhoto = false;

        $this->userRepository
            ->method('getByUserId')
            ->willReturn(null);

        $this->userRepository
            ->method('updateProfile')
            ->willReturn(true);

        $this->expectExceptionMessage("Usuário não existe.");

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetFalhaNomeMuitoCurto(): void
    {
        $userId = $this->userData['id'];
        $nome = "F";
        $sobrenome = $this->userData['sobrenome'];
        $senha = "Senha@123!";
        $photoBase64 = base64_encode($this->userData['photo_blob']);
        $isRemovePhoto = false;

        $this->userRepository
            ->method('getByUserId')
            ->willReturn($this->userData);

        $this->userRepository
            ->method('updateProfile')
            ->willReturn(true);

        $this->expectExceptionMessage("Nome muito curto.");

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }
}