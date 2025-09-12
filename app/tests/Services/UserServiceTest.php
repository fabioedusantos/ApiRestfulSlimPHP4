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

    public function testSetFalhaSobrenomeMuitoCurto(): void
    {
        $userId = $this->userData['id'];
        $nome = $this->userData['nome'];
        $sobrenome = "S";
        $senha = "Senha@123!";
        $photoBase64 = base64_encode($this->userData['photo_blob']);
        $isRemovePhoto = false;

        $this->userRepository
            ->method('getByUserId')
            ->willReturn($this->userData);

        $this->userRepository
            ->method('updateProfile')
            ->willReturn(true);

        $this->expectExceptionMessage("Sobrenome muito curto.");

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetFalhaValidacaoSenha(): void
    {
        $userId = $this->userData['id'];
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $senha = "Senha123";
        $photoBase64 = base64_encode($this->userData['photo_blob']);
        $isRemovePhoto = false;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository
            ->method('getByUserId')
            ->willReturn($this->userData);

        $this->userRepository
            ->method('updateProfile')
            ->willReturn(true);

        $this->expectExceptionMessage(
            "A senha deve ter no mínimo 8 caracteres, com pelo menos uma letra maiúscula, um número e um caractere especial."
        );

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetFalhaImagemInvalida(): void
    {
        $userId = $this->userData['id'];
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $senha = "Senha@123!";
        $photoBase64 = "base64-cagado";
        $isRemovePhoto = false;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository
            ->method('getByUserId')
            ->willReturn($this->userData);

        $this->userRepository
            ->method('updateProfile')
            ->willReturn(true);

        $this->expectExceptionMessage("Não foi possível processar a imagem: O arquivo de imagem não é válido ou está corrompido.");

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetFalhaImagemTipoInvalido(): void
    {
        $userId = $this->userData['id'];
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $senha = "Senha@123!";
        //formato .bpm inválido
        $photoBase64 = "Qk02kAAAAAAAADYAAAAoAAAAYAAAAIAAAAABABgAAAAAAACQAADDDgAAww4AAAAAAAAAAAAANUFNNkFPND9NNUBOMjtJMj1LND1KND1KND1LNkJON0BONkFPND9NNkFPN0RSNj9NMz9LNT5LMj5KNUFNNEJON0NPN0ZPNkJONUJQOEFON0FLN0FLOEJMMzxJMTtFMTpENT5LMzxJMzxJMDlDMDlCMDlCLjU+LzQ9Lzc+LzY/LzU8Lzc+LjY9LjQ7LzU8LTM6KjA1LTI7LTM+LTQ9LTQ9LDM8LDM8KzI7LDM8LjU+KzQ9LDc/LzhBLTY/LDc/KzQ9KjE6LDM8LzQ9JSsyNT1KPExdSVxvUGF0UmN2UmN2UWV3U2R3UmN2UWJ1UGF0UWJ1T2N1TmJ0UWJ1Tl9yTl9ySltuUGJzU2R3UGF0T2BzUWJ1UGR2T2N1UWN0UGF0T2BzPkdVPEVTOUJQNUBONj9NMjtIMT1JND1KMz9LMjtINj9MN0JQMz1OMTtMMzxJMz9LMT1JLztHLjpEMUBJND5IMzxJN0FLOUVRNkRQOURSNUBON0FMNT9JND5INT5LN0FLNT9JND5IMzxJND1KMzxGNT5HNz5HNj9JMjlCLjY9NDpBLzc+Lzc+LjY9LDU4LTM4KjA1KTE4KS80KC84LDI9LTM+LDM8LDM8LTM+LjdBLjdALTdBLzlDLDZALTdBLTdBKjM9LDI9LDI9LjU+MDU+JSkxLDdGQlFkTF1wUmN2UmN2UmN2UmN2UGF0UGF0UWJ1T2N1UGBzTGByTF1wRFVoVWd4VGZ3UmN2Tl9yUmN2TmJzUWN0UmR1UWJ1TF1wTl9yQU1ZPkpWPEdVPEdVOURSN0JQNUBOMz5MNEBMND9NMDtJMz9LNEBMND1LMj5KNEBMNUBONj9NNT5LMT1JMDxIMz9LNUFNN0NPN0NPNEBMMj5KMT1JND1LND1KN0FLNkBKNkBKNj9MNT1KOkROPEZQOkRONT9JNT5HMTpEMDlCMTdCMDdAMDdAKjI5Lzc+LTM6LDI3KzE2LDI3KjA1KzE4KS82LDQ7LTZALzhCLzlDLTdBLzhBLzhCLTdBLDZAKzQ+LDM8KTI7LTQ9LjU+LjM8LTM+LTM6IigtKzVBQVBjSVhrT2BzUGF0UGF0UWJ1UGF0UGF0Tl9yTF1wRVRnUmZ4U2V2UWJ1Tl9yUmN2UmN2UmR1UmR1T2BzTl9yT2BzT2BzRlJeQU1ZPUlVPklXPUlVPEdVPEdVN0JQNUBONj9NMj1LMz9LMz9LND1KMz9LND1LNT5MNT5LNUFNN0NPOERQNEBMNUFNMz9LMj5KMzxJMj5KMDxGNUFNMz9LMj5KMzxJN0BNND1KOkROOEROO0VPO0dRNkBKNkBKMzxJMDlDMjtFLjdBMjhDLjQ/LjY9KzI7LDM8LjQ7KzE2KTI2LTM6KzE4KjA3LC83KzI7LjhCLjdBLjdBLjhCLjhCLDM8KjI5LDQ7LDE6LjM8LDQ7LjM8LTI7LDI5LDI5LTM6IigvJi88QE9iS1tsT2BzTWFzT2BzTl9yS1ptTF1wVGZ3UmN2UGF0Tl9yUmN2UmN2VGZ3UmR1TV5xTl9yT2BzUWN0UWJ1TlpmTlpmSVVhRlJePUhWP0pYPUtXO0dTO0dTOURSOURSNkJONUBOND9NMz5MNT5MMj5KMz9LNEBMN0RSN0RSOUVRN0NNNUFNMjtIMTpHLztHLztHMj5KMj5KMT1JNEBMND1KPEZQOkZSOkZQPEZQN0FLNT9JND5INT9JND1HND1KMz1HNT9JMzxGMjtFLzhCLDY9LzQ9KjE6KzE4LTA4Ky42Ky42LC83LC83Ky42Jy43LjdALjdAKzQ9KzM6KjE6LDM8LDQ7KzI7KjI5LzQ9LTI7KzA5Ki84LDI5KzA5KjI5JCgtICgzP05hSVptTF1wRVZpUmN2U2V2UWJ1UGF0T2BzUWN0UWN0U2R3Tl9yTl9yUWJ1UGF0UGF0UmR1T2BzTVtnUFxoTlxoTlpmUFxoTVllRlJeQk1bPEhUPkdVPUhWQlBcPUtXPUtXMz5MNUBONUFNNkFPNUFNNj9MMj5KMDxINkJOOEJMOUNNN0BNMjtJMDpLMTxKMDxILztHMjtIMz9LNkJOOERQO0dTN0BNOUNNMz1HNj9MNT5LN0BNND1KNT9JNT1KN0FLMz1HNT5IMz1HMDlDLDM8LTU8LDI9LDM8KzM6LTM6LTM4LTM4KjA3KC41Ki84Jy43KDA3KzM6KzI7KzI7LDM8KzI7LjM8KzA5Ki84Ki84KS43KzE4KjA3KzE4LTM6JSkuGSQvOUhbTl9yVGR1UmF0T2BzUGF0UWJ1UmR1U2V2UGF0UGF0UGF0UGF0U2R3UmN2Tl1wS1ptTlxoTlxoTFpmTFpmTlxoT11pT11pUF1rTlxoRlJePkpVQE1bQUxaPEhUOklXPEdVNkJON0NPOENRNEBMNUBONkFPNj9MNEBMNUBOND5OMj5KNj9MNT5LMTpHLjlHND1KN0NPNkJONkJONUFNNEBMND5INT5LNj9NNj5LNT5LNT5IND5IOkNQPkdUO0VPOUJPNT5IND1HMDlCMDlDLzhCLzY/LjdAMDdALzY/LDM8LjQ7LTM4KjA1KC41Ky03KjA3Jy43KjI4LDQ7KDE6KzA5KS43KC84KzA5Ki84Ki84KzE4KjA3LDI5KjA3KjI5JCkwHCUtP1BkRlhrUGF0UGF2VGZ3UmN2T2BzU2R3UGF0UWJ1U2R3UWJ1R1doTV5xR1hrT11pT11pT11pTVtnUFxoUF5qUmBsUl9tUWFuT19rS1hmSVVhRFBcPkdUO0dTP0pYP0laQEpbOURSNkJOND9NMz5MNEBMN0BNNkJOMz9LMj5KNUFNNT5LMDlGLzhFMj5KNUFNNEBMMz9LMjtINT5LMj5KMj5IOUJPNj9MN0BNNT9JMzxJMjxGO0RRPEVSND1HND1HND1HND5IMjxGMDlDMjpHMTpEMTdCMDdAMDdALzY/KzM6LjQ7Ki84KS82KC02KzE4LDI5Jy43KS82KS04KzI7KTE4KzA5KzA5KzA5KzE4LDI5LTM6LTM6LDI5LDI5KjE6JSsxHCUtQ1JiTV5zT2BzUGF0UWJ1UmN2UWJ1U2R3S11uTFtuS11uR1hrS1xvSVVhS1djTVtnT1tnUV1pT11pUF5qUV9rT11pUl9tUF5qUV9rT11pSlhkSFZiP0tXPUhWPEdVO0dTOUdTOUJPOUJQOkVTO0RSOEFPMjtINEBMNkJONEBMMTxKMDxIMz9JNT5LND1KN0BNMz9JMjtILjdELTZDMjpHMTpHMzxJNT5LMzxJN0BNNT5LNT9JNT1KNT9JNT9JNkBKND1KND1HMztIMjtFMjtFMDlCLjdALjU+LDM8LDM8KTE4KTA5Ki84LTI7LTI7KzA5LDI5KTA5KDA3KDA3Ji02KTA5KDA3KzA5LDI5LjQ7LzQ9LTI7LDI5KjA3KzE4KjA3KCszHSUrRlVpUmN2UWJ1UWJ1UGF0RVdoS1xvSFlsSFlsTV5xS1xvR1FiR1FiRFFfRVVhTFhkSVdjTFhkTlpmTlxoT11pTFpmTVpoU19rTFpmSVhhSFZiSlhkR1NfQk5aPkpWPEZXO0ZUOUVQOUJQOENROENRN0JQNEBMNj9MNkJONkJONEBMNT5LND1KND1KMT1HMzxJMTpHMjtILzhGKzNALzdEMjtINT1KNj9MN0BNNUBKNT9JNkBKNj5LND5INT9JNkBKMjtIND5IMDlDMjhDMTpEMDlDLjQ/LjQ/LDM8KzI7LTI7LTQ9LDM8LDM8KzM6LDI5KzA3Jy82KC84Jy82KS43LDE6LTI7LjM8LTI7LTM6KzA5KzE4KzE4LDI5LDE5KjA3U2V2UmN2UWJ1TV9wT19wSVptR1hrTl9yTV5xSVptR1lqR1VnRlRmRFJkRVJiRlNjRVNfR1ZfSFVjSldlUV5sT1xqTlxoSlhkTlpmSFZiTFpmTFpmTFpmSlhkTVxlUF9oSFZiQUxaO0ZUNkFPOURSOURSOkVTNUFNND9NNEBMMz9LNEBMMj1LND1LMjtJMDxIMzxKMTxKMj1LMT1JKzRBLjVELTZDLzlDNDxJNT9JN0BKNDxJMzxGOUJMOUJLOkNMMzxGND1HMj1FMzxGND1HMTpEMDtDLzhCLjU+LTU8LTQ9LjY9LjY9LDQ7LDI5LDI5KzE4KS82KC41Jy00JiwzKC02KC41KzA5Ky42LDE6KzI7LTQ9LTI7LTA4JTA7TmJ0U2R3UGF0RVZpTF1wR1hrTF1wTl9yS1xvSFprR1hrR1hrTlpsS1lrSlhqR1VnR1ZmSFRmQlFhQ1BgS1hmSFRgRlFfSFRgSlVjSVdjTFlnSVdjSVdjTFpmS1llTVllTVllT1tnTVljSFRgRVFdRlJcP0tXO0dTOERQPEhUPUlVOkNQOEFOOEFONj9NNj9NN0BOMj5KMDtJMDtJMTpIMDlGLTZELDVDLTZELTZDLTdBMjtFMTpEMDhFNj9JOkNNNT5IMzxFND1GND1GMzxGNj9JMz1HND1HMjlCMjlCMThBLzY/LTU8LjY9LTU8KzM6LDI5KTA5KzE4Jy00KS82Jy00JSsyJSsyJiwzJy00KTA5LDM8KC84LDI5UmJzU2R3UmN2S11uTF1wSFlsSFlsTl9yTF1wSVptSFlsSVptRldqR1hrTFhqUV1vT1ttTVttTlxvTFhqSFRmSFRmSFRmRVJiQk9fRVJgRFFfRFBcRVNfRlFfRlRgSFZiR1VhTVpoS1llSVdjSlZiSlZiRlRgRlRgS1pjR1NfTFlnPUpYOURSOENRN0NPOkNQN0BNNj9NNj9NNj9MMj5KMj5KMDxIMT1JMjtIMDxILzpIMjtJLDhEKzNAKzQ+MTdELzhCLjdBMDlDNT5IMzxFND1GNTtGNDpFNj9JNUBIND1HMTpELzhBLjdALzc+Lzc+LjY9KjI5KjE6KC84KjA3KS43KS82Ki84KDA3JSozJyw1JSsyJiwxJCovHygyUWJ3UmN2UWN0SFhpSVptSFlsTF1wT2BzSFlsR1hrSVptSVptR1ZpSVlqSFdqUFxuTVlrUV1vUFxuUVttTVlrSFVlSVVnSFRmR1NlSVVnRFJkRlJkQk9fP0xcPklXQEtZRE5cQ1BeRFFfSFVjSVZkSlZjSVVhSFRgSFRgSlhkS1llT1xqSVVhQ09bQk5aQU1ZPEhUO0dTOERQOEFONj9MNUFNMz9LN0NPNUFNNj9NN0BNND1KNUFNNEBMMDxILTZDMDlGLjdFLDNCLjZDMzxGMj5END1HMTpHLzlDNT5INT9JNj9JND5IMDlDLjdBLjU+MDdAMTlALTY/MzpDMzpDMDdAKjA3KjI5LDI5KjI5KTE4KC84Ji02JCoxVGR0VGV4UWJ1SFlsSVptSFlsTF1wTF5vSltuR1hrR1hrSVptS1ptR1hrRldqRVZrR1lqTFtrSFdnTltrTFpsT1ttTFZnS1hoSVZmSFdnRlVlRlRmR1VnSVVnSFRmSVVnRVFjQlJfP09cPUhWPktZQE1bQU9bRFBcQk5aQk5aQlBcQ1FdQ09bSVVhRlRgR1NfRFBcRVFdRlFfSlhkQ1FdP0tXPEhUOEFPNEBMOkZSN0VROkNROkRVN0FSOUVROEVONkJON0BNMDlGLjdFLztHLTtHLTlFMDpELzhCMDpEMTpHMzxGNT1KNz9MND5INT9JMTpHN0BKMThBLzY/Lzc+MjpBMjpBLTU8LDM8KjI5LDE6KjE6KjA1Ki45Iy06U2V2VGV4U2J1R1hrSltuSltuTmBxSltuR1hrRldqR1hrR1hrSFlsRldqRlVoSFlsQlJjSllsRFBcQEtZP0xaRlNjTFlpSldnSVhoSVhoSllpSVhoSVhoRlVlR1RkRFJkSFVlSFVlR1RkQlFhPktZPEdVO0ZUPUhWQUxaPkhZP0pYRlJeR1NdRVNfR1JgR1JgTFhkSFRgTFpmSVZkR1VhRFJeRVNfSFZiR1NcQ05cQUxaOkdVO0hYO0VWPUdZO0VWOENROUJPO0dTOkNQNT5LLzhFMDlGLzhFMDlHKzNALDRBLTZAMztIMjxGND1KOEFONkBKOEFOOUJPMTpELjdBLTY/KzM6LTU8LTQ8KzE8LDM8LjU+LjdALDg+VmN2VWZ5UmZ4SltuTF1wSltuTF1wTF5vSVptRldqR1hrR1hrR1hrRldqRVZpR1hrQ1RnSFlsR1lqRldqSFVlQU1aQExYP0hWQk1bQ05cR1RiTFtrSllpSVhoSlhqSVhoSVhoR1ZmRVRkR1NlRlNjRlNjQU5eQUtcPUhWPUpYO0hWPEdVPEdVOkVTN0JQP0tXQ05cS1djT1xqT1xqSVdjRFBcQ09bRlVeSFZiSlZiTFhkTVtnTlxoS1djQE9fQlFhPkpcO0dZOEVVOUVROkZSOERQN0JQNkJONT5LNj9MMDtJLDVCKzRBMDlGKTE+LTVCMjtFOEJMOUNNN0FLMz9JNT9JMzxGMjtFMzlEMTpELDM8KzI7LDI9KTE6KTE5UmN2Vmd6U2R3S19xTF1wTl9yTl9yTGBySltuRldqSVptRldqSVhrR1hrR1hrRlVoPk1dN0VSS1tsRFRlQ1JlXGp9V2V4S1dpQ1BgQkxdQEtZPUpYQlBcRE9dR1JjSVdpSVhoSVhoSVVnSVZkRlRmR1NlRlJkQ1BgQU5eQEpbQEpbQEpbOkdVPUhWPUlVOENRN0NPO0ZUOkVTQEpXRk9cRlJeRVFdQ09bQk5aQ09bS1djTVtnUV1pTV1pT11pTFpmSVZkRFNjQU9hPkxcOUVROkNQOUJQNkJON0BNN0BNN0BNMz5IMzxJN0FLMDlGMTpHKzdDKzJCLjZDMTtFNkBKNT5INT5IND5IMz5GND1HMjtEMTpDMDlCLDU/VmR1V2d4Vmd6TF1wUGF0SV1vS1xvUGF0TF1wRVlrR1hrSFlsRldqRlhpRldqSFlsQVJlS1xvSlprR1doQVBgKjtIP05gVGByVGByTFhqTFhqVGByTVpqQ09dQk1bQE1bQEtZPktZRFFfRFFfRFFhRlNjSFRmR1NlR1RkRFFhSFdnSVhoRlRmQk5gPUpaPUdYP0pYPkpWOkZSOkRVOURSOURSOEFPS1ZkSFVjR1RiRE9dP0tXP0tXSVVhTFxoUmBsUmBsWWdzUmBsUl9tTVtnRFRhQk5aP0tXOUVPP0tXP09bQU1ZO0RROUJQN0FLOEJMN0BNN0BNN0NPMDlGMDlGMDlGMDlGMDpENj9JN0BNND5IMz1HND5IMT5GLThGVGV4VGV4U2R3TV1uVGV4TF1wS19xTl9ySltuSFlsSFlsRVZpRldqRVZpR1hrQVJlTV5xS1xvR1ZpRVRnQVBjPk5fQVBjN0VXWml8Wml8VmR3UV9xSVVnT1ttTlpsTlpsSFRmRU9gSFVlRlBhQEpbQU5eRU9gQ1BgRVJiRFBiRVJiSldlTFtrTl1tS1pqRVRkRlRmQ1JiQU5ePklXQUxaP0pYPEhUOkVTOURSPEdVRVJiSVhoSVZmRlNjQUxaQk9fT1xqU2BuWmd1VmRwUl9tTVtnS1llTVllSlZiRlJcPkpWPkhSPUZTQElWPUlTPUlVP0tVOkZSN0JQOUVPOkNQNUFNLzhFLTZDKzRBMDlHMzxJMz1HMjpHLzlDU2J0U2R3UmN2TF1wWGl8Vmh5T19wN0dYSltuSFlsSltuSFlsRVZpRVZpR1hrRldqTF1wSltuR1doRFRlP09gPExdPU1eOkpbOUlaN0dYWWd6UV9yWGZ5U2J1U2F0UmBzUF5wTlxuUV9xUV9yVGFxV2Z2UF1tTltpRlRgQk1bPktZQk9fQ1BeSlpnS1hoTFlpSlpnSllpQ1BgRVFjQ1FjQU9hQlFhQk9fPUpaOkVTOkVTOkZSO0dTOkhUQk9dSFVlQ1BePktbOUZWPElZQU5eRVJgTFpmS1llS1llSlZiS1djT1xqR1ZfRlJcRlJeRVFdPEhUQU1XRFBaRFBaQk5YPEhSOUJPOUJPNkJONkJOMz1HMDlGLjdELzlDKzZEUWR2T2N1Tl9yWGl8V2h7V2h7VGh6Vmp8V2h7UmN2Q1FjOEhZSFlsRVZpSFlsS1xvTF1wSVhrRFNmPExdOUlaPExdNERVOEZYNkZXNkZXNkRWWml8XGd7VWN2WGZ5U2F0U2FzWWZ8W2d/VmN5UV9yU19xW2p6Xmt5WGV1UF1tSFVlRlNjRVBeQUxaQU1ZQk9dQk9dRVJgRFFhQ1BeR1FiR1FiRFFhRVFjQ1FjQE5gP0xaPkxYPUpYRFRhPktZOURSOkZSPklXP0pYPUlVOERQNkJONEBMPUhWP0tXQE1bSVZkS1hmUV5sTl5qT19rT11pSVZkR1RiQU5cPUhWQktYQk5aQ09bRlJcQ09bN0NPND1KMz9LMzxJMDZDUWFzUmN2UWJ1TF1wWmt+WGl8V2h9V2h7V2h7Vmd8V2h7U2R3VWZ5U2R3T19wQlRlTV5xSVhrP09gN0dYN0VXN0VXNUNVNkRWNUVWN0VXN0VXMUBQM0FTXWuBW2p9Wml8W2p9WWd6VWN1VWN1VmR3VmR3VGJ1U2FzU2FzU2FzU19xVGBySlZoT1xqUF1rT1xsRVJgQk1bPklXPUhWPklXPktbQUtcQU5eRlNjRlNjRlJkQU1fQE1dQ01eQk1bQU5cRlNhOkRVOkRVOERRPEhUPUlVP0hWPUlVP0tXOkZSOkVTOUVRPEhUQU1ZS1llSlpnS1hmSVZkSFdnTl5vUF5wTVttQ1BeQU1ZQEtZP0tXRE5YQU1XP0tXN0NNN0BOVGV4UGR2T2BzWGl+WWp9Vmp8V2h7V2h7WGl8U2d8VGV6VGV6VGV4U2R3S1xvTl9yQVFiNUNVM0FTNEJUNUNVN0VXNkRWNkRWNUNVN0ZWMD9PMUBQMz9RMz9RWGZ8Xm2AZXaLZXaLYG2DXGt+WWh7V2V4V2V3VmJ0VGJ0VGJ0U2FzT1ttTVttTVttS1lrTVlrSldnRVJgQUxaP0laQ05cQk9dQU5cOkdVO0ZUPktZPUpaRlNjRlNjRlBhRVJiQk9fQU5eQUxaQ0xaQkxdQ05cPUdYN0NPPkdUPkpWQU9bQ1FdQlBcP01ZQU1ZOkZSQElWPUhWQU1ZRVJgRlNjT15uU2N0UmJyU2NzUmJyQ1NgQ0xZQk5aQExWQU1ZUmJyVmd8W2x/UGF0XW6BWGl8V2t9WGl8V2h7Vmd8VGh6VGV4VGV4U2R3TF1wTV5xRldqRFVoOEdXPEtbPEtbOklZOUdZNUNVNURUNkNTLzxMLTxMMD1NMDxOMj9PMT5OSFdqW2mAX3CFX22DYXSJZnmOYXWHYHGGXWuBWmh+WWd6WGZ4VWN1UV9xUV9xUV9xU19xVGByTlxuTlxuTVlrSldnQk9dUF1tXGh6RlNjPUpYOkdVPklXPEdVPUpYQU5eSlZoSlhqR1VnRVJiP0xcP0xcSFZoTFhqR1VnR1RkP0pYOERQPUhWQUxaQ1BeP0pYQ09bQ09bRFBcPEhSPEhUOURSO0lVRFFhRlVlSlhqTFxpQU1ZSFJcSFJcQlBdVGV4XW6BVWZ5W2x/WW1/WGx+Wm6AV2t9WWp9Vmp8VWZ7VGV4UmN2UGF0S1xvSltuQlFkQlZoQFNoOUdZPEtbOEZYNkVVM0JTMkFRLjtLMD1NLzxMLjtLMD1NMT5OLzxMMD1NQk5gSlhrXG2CZHiKZHeMZXiNZ3yRXnWLXm+GYXCDYW+CXmx/X2yCYm+FYW+CWWd6V2d4UF5wVWN1VGJ0UF5xSVhoRVJgQ09bQ09bQk5aQktZPEdVO0RSOURSN0RSOkVTPUhWQk9fQU5eRVJiRlNjSVZmR1VnU2F0X26BbH6PZ3mKWWl6TVlrT15uTFxpT1xsSFdnSVlmTVpoQ1BePkxYP0xaPUpYPktZPktZQE5aS1dhS1djPEZTV2Z2XHCCXW+AWWp9XW6BW2+BW2+BW2+BWW1/V2p/Vmp8VGh6VWZ7UmN2T15xT15xSFdqPUxfO0lcQVVnQVRpOkhbN0RUNEFRMD9PLjhJLTpKLTpKLTpKLjtLLzxMLTpKLTpKLjtLLjtLYG6AX21/X26BWml8WWd9YW+FYnCGZXSHZ3aJaHaJYHCBXG5/W2p9WWh7Wmh7WGZ5V2V4WWZ8V2R6V2Z5V2Z5U2FzTltrTltrSVdpR1VnP0xaQ09bQE1dQU1fQE1bPklXPUlVO0dTPEdVO0ZUQkxdRVJiSlhqUF5wW2t8XGt+Z3iLY3SHWWh7VGJ0UWBwUF9vTltpTVxsW2p6XG5/Wmp7WGd3Q1BeQExYRFBcRlJeTVllTFpmWGt9YHGEVGh6XHCCW2+BXW6BXXGDWGx+W2+BWW1/Vmp8Vmd6VWZ5S1xvTl9yS1ptRFNmPUteOUdZOUVXQVRpQVRpOkhaLz5OLDlJLTpKLTdILTdIKzhIMTpLLDdFLjlHLjlHKzhIKzhILTpKiJmiUF1tT1ttWGZ4YnCDZHKFYnGEYHCHYHKJYnCGY3KFY3KFYXCDY3KFWGR2WGR2VGByVWFzTVttTVttU15yW2l7VmR3T11vTlxuTFpsSFZoR1ZmRlVlRFJlP0xaPEdVPklXPElXPEhUPEhUQ09bQU5cPUpYRVJiRlVlUWBwUWFyVWV2UmBySllpSldlRlNhRFFfWGd3Xm5/YnKCbHuLbXyMa3qKZnWFYXCAWGZzVWV3Wm6AX3CDXm+CXXGDXHCCW2+BXHCCW2+BWW1/W2+BVmd6WGl8TV5xTl9yTVxvSFdqQE5hOEZZNkRWNkJUN0FSQFNoQFNoOkhaMUBQKzVGKjRFKzZEKzZEKzZELDdFLDdFLDdFKDNBLDdFMD1LLjlHbX6Lbn6Obn6ObX2NaXmJVGNzU2NzXm5/Y3GDYnCDXWt+XGt+ZnaHcICRfIyceYmaXmx+UF5wSlZoTFhqTlxvR1RkQ09hRVJiRVFjS1dpTFpsTVlrS1dpRlVlSVhoSFhlRFFfP0pYPUhWOUVRO0dTO0hWPEdVPEdVO0ZUPEdVQEtZRVJiTl1tUF9vTFtrTVpqQk9dQUxaPUpaZnaGbHyMbXyMbHuLaHeHZnSGWGl+YXKFUWV3X3OFXnKEXXGDXXGDXHCCXHCCWGx+WGx+WWp9UmN2UGF0TVxvSFhpQ1FjOUdaN0NVMT1PLjtLLTdIKTVHPlFmQFNoO0lbO0lbKzRCKzRCKzRCKzRCKzZEKzRCJzA9KzRBHCUyKTdJNENTKjVDiJilh5amhZSkhZSkgI+fcYGRYXGBanqLZnaGZnaGUmByYnKDd4aWgZCghJShgpGhfYyce4ubeIiYanmJW2yBWWp/TVxvTV1uR1RkQk9dQ1BeQ1BeRlNhRVJiRlNjRVJiSFVlR1RkQU5cQUxaO0ZUQEtZP0tXO0VWOkZSO0RROkZSOUZUPklXO0ZUP0pYQkxdQU5cPEdVP0xcP0tdP0xcQ1BgTVpnTV1wW26DXXCFXnKEXXGDXXGDXXGDXnKEXXGDWm6AXm+CV2p/WWp9U2R3TV5xSllsQ1JlOUdZOUVXMDxOKzdJJzREJjNDISw5Hio4PVBlPk9kO0lbOUlaKTJAKTJAKjNBKjNBKTJAKDE/Hyg1GiYyIi07KzdJM0JSKjRFkp6qkJ6qjpyoi5mlhpOhhJShhZWigJCdfIubc4KSbXyMbHuLdIOTcoGRZnWFZ3aGdYWVanuOaXqNaHmMXmx/WGZ5WGl+VWZ7V2mAVmZ6T11xSVhqSlhqQk9fQU5cQk9dQUxaQUxaP0xaQEtZQUxaPUhWPkpWPEdVOENRPEdVOURSOUJPOUJPO0RRPklXPEdVPEVTO0RSO0ZUOEZSOkRVPUhZWGx+YnaIVGd8YHSGYHOIX3OFXXGDXXGDXHCCWm6AWGx+Wmt+T2BzT2BzTl1wRlVoPEpcOUVXMj5QLDhKJjNDJDFBHSo4Gyg2GSczIjBAOEtgN0pfO0lbO0lbIyw5ICo0Iyw6Ji88Iis4GSUxISo3JS47KjRGKTdJNURULDlJa3yPeYmagpKfhZWiipikiZejiJikiJikh5ejhJOjgJCdhJSgfo6bgJCdfY2ae4qafo6bcYGOZXGDTl1tVmR3YW+BYG6AWmh7VWN2V2V4WWp/Vml+XW+GXm6FWWp/T15xS1tsQlFhP0xbPEdVO0ZUPEhUPEhUOkZSPEhUOkVTO0ZUOENROUVRPUZUOkVTOkVTPUhWPEdVPEdVOkdVT15vY3eJXHCCY3eJYHOIYHOIYXWHXXGDXG+EWm2CXHCCWWp/UWJ1UWJ1S1xvRlVoPkxfOEZYNEBSLDhKJzREJTJCHCk3HCc1GiYyGiUzGSUxNkZXNEVaNUZbPExdMEBRIy89GCQwHCYwGiMwHCUyIis4Ji89JzJAKDRGKzdJNURUKTZGb4CTbH2SaniOWWp/XGt+aXmKfo2dg5Ogh5ejiZmmjZunj52pkqCsjJypi5uoiJilhpaijJqmh5ejh5ekg5Oga3qKYXCATFhqQkxeTFdpS1lsWGZ8YHCHYXKHYXaLYneNX3KHWmyBUmN4TVtuTlxuQ1BfQk5aQUxaPEdVOkVTOURSO0hWRFFfOURSOERQPEdVOENRN0JQNUJQX3OEY3eJW2+BYnaIYnaIZnqMYXWHXnGGXXCFWm2CWGuAUGR2U2R3Tl9ySFdqQE9iOUdZNEJULjpMKTZGJDFBHis5HCc1GyczGSQyGyQxFiMxOElbNkleP1JnQVRpPk5fOkpbO0tcPUtdLjxOKTREHyo4Iy48KTRCKTNEKzdJKzdJNkNTKTZGcoaYdoeccX+VaXqPanuOcYWXcISWbX2UZHKIX26AYnGBd4eXf4+ciJaih5ekiZmmjJqmiJilhZWihJShg5Kih5ejiJmjiJWjg5CefoubcH+PYHB9Ul1vRVNlU2Z5XHGGYXaLYneNY3uPYXmNXXCFUGN4VGV6Tl5xUmF0T11wQ1BgP0pYQ05cRFJeQUxaPkdUPEdVTVxrZXqPX3OFaX2PY3iNaHyOZHiKY3eJYXWHYXWHXXGDWGuAVWZ7T2BzSllsQVBjPUtdN0NVMDxOKTVHJDFBHiw4Hik3GyczGiYyGSUxFyQxN0deOUtiQlRrQVRpQFNoQVRpPk5fPU1ePExdPEpcOUlZOUlaOUdZLTtNKTZGJjNDKTVHKDZINUJSLTpKQ01fQU1fRE5gTFhqU2F3XWuBbnyPcoCWdoWYb36ReYibfIuedoWYY3GHUFxwU19xbHuLgpGgh5ekiJiliJiliJikhZWhh5ejh5ekhJShg5OggpKfhJShg5Oge4uYbXyMVmNxRFJkSFdtWm6EWW6EW3CGWG2DUGN4UGN4UGN5U2R5SllsS1lwS1ptO0paOkVUaHyOaXmPYnWKaHyObYGTZXuNZXmLZ3uNZHiKYHSGX3OFVGh6VGV4S1xxRFVoQE5hOEZYMj5QLDhKJjNDJjNDIDA9Gig0GiYyHCUyGiY1N0hdPU9mQ1ZrQFNoQFNoP1JnQFNoQlRrPk5fO0tcO0tcO0tcPExdOkpbOEhZOkpbOkhaN0VXLz1PJzZGNURUMD1NSFZpSFNnSVRoRVJlQk5gQk5gP0tdQU9hU2F3Y3GHY3GHYG6BZHGHaXeNY3GHXGqAT11wR1VoPkpcSVVnVmN1b36Ofo6bg5OgiJilhZWihpajhpajhpajgpKfipiki5mliZmmg5OgeYmWb3+MVGFxSFVlOklbQlFmOkhaPUlbQEpbQk5gR1RkP0xaSl1uan2SYnWKboGWaX+Ra4GTaX6TZnyOY3mLZXmLY3eJU2Z7U2Z7S1xvTFtuQVBjO0lbNUFTLjpMKTVHJjRGITBAIC8/Hi09Hyw5IzJENkhfQFJpQVNqQFVrQ1VsQlVqQFNoP1JnQFNoQlVqP09gO0tcPExdPExdO0lbOkpbOUlaOkpbOkhaOUdZOEZYN0ZWNURUNEJUSlhtRVNlTVhsQ09hQUxgR1NlSlZoQ09hQ09hSFVnQ1FkQExeSFRmUV9yYXCDV2V4TFptTFptTFtuUmB2TFptR1VoQk5gO0dZQ1FjVGJ0cYCQfIyZhJShhpaih5ejh5ejhpaiiJilh5ekhJShhJGfgJCdeoyXcoKPaXiIR1RkN0FSMTxKMDtJcYWXa4CVbYGTbICSaXyRbYOVaH6QaH2SZXqPZHqMWW1/Vml+TmF2SVptQVBjPUteNUNVLTlLJzNFKDZIIzFDITBAIDA9Hyw6MD9SNkpgQVNqQVZrQ1huRFZtQFVrQlRrQVNqQlRrQFNoQVRpQ1ZrPk5fPU1ePk5fPExdOkpbO0lbO0tcOkpbOkhaOUdZOUdZN0ZWNkVVN0VXSlZoR1JmSVRoTFpsS1dpTFhqRlRmQEtfRFBiRVBkRlRnTVpvSlluSVdpSVhrSFZpQ1FkQ05iRE5gP0tdQlBjRlRnRVNmR1VrQU9iP01gQlBiP01fO0VXO0dZTlxubXuNeomZiZmmhJShh5ejhZWhg5Ogf5Cdf4+cg5OghZWhfYqaaHeKbYCVYHaIc4eZcoaYcYWXcISWb4OVbICSa3+RYXWHWm2CU2Z7S15zRlRqQE9iN0VXMj5QLDhKKzlLIzFDIzJCITBAHi09OUpdOkxjRFZtRFZtRVduQ1VsQlRrQVNqQVZrQlVqQVRpPlNpQVNqQFJpQlRrPUxfPExdO0tcPExdPk5fPExdOkpbOUlaOUlaOEhZN0VXN0VXNkRWOEZYorbBo7fCpLfDe4yaRVNmRFJkRlFlSlZqU2F0YHKJYnaPWmyDUF92TlxvRlVmS1lsTFpwYHWQan6XZnqTWmuCTVtyPEhaPkhaOkZYOkZYOEJUPUlbQUxgO0lcPEhaO0dZOUNVOEJUNUFTN0NVRVFjX21/eouZh5ejhJKegJKfc4iacoiac4eZdIiacoaYcISWcISWboKUbYGTa36TW26DV2p/UGN4SVptRFNmOklcMkBSLztNLTxPJTVGITNCIzFDIDBAPE1lPE5lRFluRFlvRlhvRFZtQ1VsQ1VsQVZsQldtQlVqQVRpQFJpPlNpP1FoQFJpQFVrPEtePExdO0tcO0tcPExdO0tcPExdO0lbOUlaOUdZOEZYOEZYN0ZWNUNVqb3Ip7vGprrFo7bDnq+8qb7GpLjDqLrFprnGlae0TVxuRVNlRVNmRFJlSFlsT11zUmB2T11zT2B1U2Z7Vmh/VGl/UGN4SFZpPEhaPEhaPEhaP0tdQExeQExeO0dZO0dZPEpcOkZYOERWO0dZPUlbND5QOUVXOERWRFNmdo6iaoCSdIqcdIiadoqccYeZcYeZbIKUbYGTXnOIXHGGVGd8TF90RldsPk1gNkRXLz1QLz5RJjVIJjRHJDJEHi9APk9mQVZsTmN5R1xyRVpvQ1huRFZtRFlvQldtRFlvQVZsQ1VsQ1VsQVNqQVNqPVJnQVNqQVNqQFVrPUxfO0tcO0tcOUlaO0tcOkpbO0tcOkhaO0lbOkpbOkhaN0VXN0ZWNkRWr8HMr8LKrsDLobfDrsDLssTPqr7JprrFqb3IqLzHo7bDlqm2q73IqLrFprnGoLPCWWl5QVFiRVNmR1VoSFZpQlNkPkxfPkpcPUlbPUlbPUlbO0dZOUVXPUlbP0tdQU1fQ1FkRFJlQ1FkP0tdOERWN0NVQEpedo2feI6geY6jdYuddIqcdIqccYeZcYeZcIWaY3iNYXaLWW6DUGN4RldsQU9lOktgN0VYOEZZMD9SLDpNKThLJTRKPU9mR1xySV50R1xyTmN5RltxQ1htQldtRVpwRVpwRFlvQ1huRFlvQVZsPlNoP1RqQFJpP1RqP1RqPlNpP1RpO0xfPExdO0tcPk5fPU1ePExdPExdPU1eO0lbOUdZOUdZOUdZN0ZWN0VXrsHJrsDLqr7Jkqi0t8bPtcfOssXNsMPLscPOpLbBjZ6rrsHJqrzHrb/Kqr3Fp7vGp7vGobTBo7bDn7O+mK66j6SzZ3WIO0haOUVXN0NVNEBSNURUOUVXOERWOERWNUFTNkJUN0NVOkZYQlBjTFptUWN4do+hboSWeI6gd42fdYuddIqcc4mbcIWaa4GTZ3uQQFVqMEJZPlBnRVduS110TF90VWZ7T15xS1ptQVBjPUthPFBpTWJ4TWJ4S2B2SV50RltxR1xyR1xyRltwR1xyRFlvR1xyRFlvRFlvQldtQVZsQFVqP1RqQVNqP1RqPVJoQFNqQVRpPE1gOkpbOkpbO0tcO0tcO0tcOkpbPExdOkpbO0lbO0lbOEZYNkVVOEZYvc3Uu8rTu8rTtsnRuMnSt8nQtsfQtcbPtcbPssXNtMTQr8LKs8TNsMPLrr/IssPMrcDIo7jGqbvGorbBoLPAjqOyf5OkbYKXaX6TX3eLXHGGY3eMYXOINkVYM0BSN0FTNkJUOUNVNkJUOUZWepCid4+hd4+heY+heI6gdoyedIqcdIqccoeca4KYaYCWN0lgYnmPXnWLYHWKXnOIYXaLX3SJYXWHXnKERVpvQVVuTWR6TmV7TWJ4Sl91S2B2Sl91R1xySF1zRltxRltxSF1zRVpwRltxRltxRVpwQldtQVZsP1RqQFVrPlNpPVJnP1RqPFNpPVJoO0pdOkpbOUlaOkpbOUlaO0tcOkpbN0dYOUdZOkhaOkhaOEZYN0dYNkRWw9PawtLZtsfQv8/WwdHYvs7Vu83UuszTu83UorS/t8nQt8nQt8jRtMfPtMbRs8TNp7vGq73IrsHJqrzHrsDLVGh6YXSFP1BlPE1iO01kOUpkSl91YnmPa4CVd42ffJKkgZiohZysiJushJure5GjfZOlf5ame5KieY+hdoyedIqcbISYa4OXaYCWaoKWTGF3NEZdYnmPZ3+TZHyQYnmPYHiMPlRtR1t0UWZ8T2R6TWJ4TWJ4T2R6Sl91TWJ4TGF3R1xySV50R1xyR1xySF1zRltxRFlvRFlvQldtQVZsQFVrQFVrQldtP1RqP1RpP1RpQFJpPlBnPEteOUlaOkpbOkpbOUlaOkpbOkhaO0lbOUdZOUhYOUdZOEZYOEZYNkRWyNbcyNbcqbnFxtTaxdPZxdPZwNDWwNDWvM7VwtLYwNDXwdHXuszTuMrRuMrRo7XAs8TNrsDLscTMtMXOobO+XW+AXnCBTl1wTmBxQ1RpOEhfW3CFd4udhZysWm+EgZepiaCwjaS0h56uhZysh56ugpmpgZiofpWleZGjZn6QbYWZb4ebb4ebbIWZZ4CUXnWLMUNaWnKIW3OLWnKIQFZvTmR9UWh+T2R6T2R6T2R6TGN5S2B2TGF3Sl91S2B2SV50R1xyR1xyR1xySV50R1xyRFlvQ1huRVpwRFlvRVpwQVZsQVZsQFVrPlNpQFVrP1RqPlNpPlBnPE1gOUlaPExdOklcOkpbOUlaOUlaOUdZOUdZN0dXOUdZOUdZN0VXNkRWy9rdytjeztvhydjbydfdyNbcxNTaxtTaucvSxtTaxNLYwNDWwNDWwtLYprrCt8nQtsjPtsfQtsfQs8TNrb/KW26AfJCgTF1wT15xTl90bICSRFdsV2+DXHGGiaCwk6q5kqm4iJ+viaCwi6KyepGhcomZfpSmd42fepKmd4+jdY2fdIyecYmdb4acZH2RYHSNNERbX3aMRFpzVGqDUmiBUml/UWh+TWR6TWR6TGN5S2J4SmF3TmN5SF1zSl91Sl91SF1zQ1huSF1zRltxRltxSF1zRltxQ1huQldtQldtQ1huP1RqP1RqP1RqPVJoP1RqQVZsPFFmOktgOUlaO0tcOUlaOkpbOkpbOUlaOUdZOUdZN0dYOUdZOUdZN0dXN0VX09/hwc7W1d7hz9vfztreytncydfdvczVy9fby9fcytbcyNbcxtTauMnRwNDWvs7VvMzTvMzTu8rQq77Js8TNWmx9aHyOWGuASltuTmN4YnSFbX+OnKixW26Dlaq5i6Kykqm4jKOziqGxhZ6ugpqsfZWnf5epfZWne5Old4+jdY2fdIygaoKWaYGVaYGTSmF7SGB4XHKKV22GVmyFU2mCUGd9Uml/TmV7T2Z8TGN5TGN5SWB2SmF3S2B2SF91SV50SF1zQ1huSV50SF91SF91RltxRFlvRVpwRltxQVZsQVZsP1RqP1RqQVZsPlNpPVJoQVZsPVJoPE1iOkpbOUlaOUlaOUlaO0tcOkpbO0tcOkhaOEhZN0VXOUdZOEhZNkRW0NzguszU0t7g0t7g0t7g0d3h0d7gzt3g0NzgzNjcxNDUzNjcydfdw9PZwNPYvtDXvM7VuMnSrr7KucvSssTPZXuNc4udY3aLSFlsSFluZHaHpLfEnrPCnbO/lq28lKu6k6q5j6a2hp+vhJ2th56uhJurgJmpfpSmeZGjc4udaoKWZ3+TaH+VYXiOTWR+XHSMXHSMWnGHVm2DVWyCUml/UWh+UWh+UWh+TmV7TGN5S2J4TGN5TGN5TGF3R150SV50SF1zQ1huSV50RltxR1xyR1xyRVpwRFlvRltxQVZsPlNpP1RqPlNpPlNpP1RqPVJoQFVrPFFnPE1iOUlaO0tcOkpbOEhZOkpbOkpbOUlaOkhaNkZXOEZYNkRWN0dYN0VXS2B2coeama29r7/KusnTwdLYrr3Fydbcztrgzdndz9vfzNrgytjeytncxdbZxNTawtLYs8TNws/XwNDXr8HMfpWlfJKkWm2CU2Z7TV5zdoqbpLfEma+7nrPCl669kai4jaS0h56uiaCwh56ugpiqgZepfJKkdYqfZ3+TZ3+TYnqOY3qQY3qQZHuRXnaOXXWNW3KIWnGHVm2DUml/VGqDT2Z8Uml/TmV7TmV7TGN5S2J4SmF3S2B2TGF3SF1zSF1zRltxPlNpRl1zRFtxRVpwRVpwRVpwQVZsQVZsQVZsP1RqP1RqPlNpP1RqQFVrPVJoQVZsPVJoO05jO0tcO0tcO0tcOUlaOUlaOkhaO0lbOkhaN0dYOEZYN0VXOEZYN0VXbISaa4ObZn6WYXmPXHaLVWuETmV8SmB5QlpzRFtyaoGVhp6vobbErL/JtcfPuMrTrsDLwc/VwtLZvc3UtcfSdoqbZnyOWm2CUGN4S15zr8HMp73InrTAk6i3iJ+vf5epe5Olf5WnepCieI6gdYqfboOYX3WLW3OHWXCGWG+FW3CGXHGGXG+EV26CX3ePXXOMWHCIWHCIWG+FVm2DVGqDT2Z8UWh+UGd9TGN5TGN5TGN5SWB2Sl91S2B2S2B2R1xyR1xyP1RqSF1zRVxyRltxRltxRFlvQldtQldtQldtQVZsQFVrP1RqP1RqP1RqPVJoP1RqPlNpPE9kO0tcOkpbOUlaO0lbO0lbOUlaOElaOEZYOEhZOEhZOEhZOEZYOUdZfJWpfZaqfJWpfZaqe5Opd4+lcIugcIigb4edaoScaICYYnqSXnaOWHCIUWeATGB5Rl52P1VuQFduYHaIucnRd4udaX2PWGuAT2J3ma2+nrbGTmeBbIGWXXWJTmV7SFpxP1FoNkpjM0ReNEZdOkxjP1RqQFVrRVduR1htSVpvSltwSltuTF1yVmp/XnaMXXWNW3OJWnGHVmyFUmqCU2mCT2V+UWeATmV7TWR6TGJ7SmF3SF91SWB2SF91R150SF1zSF1zQVZsSF1zRV1zRltxRFlvQllvQFdtP1RqP1RqPlNpP1RqPVJoPVJoPVJoPVJoPVJoPlNpPU9mPExdOkpbOkpbOUlaOEhZN0dYOEhZOEZYNkZXOUdZOEhZOEZYN0VXgJiqgJiqf5epfpmtfZerfZWre5aqe5Soe5Ope5Sod4+lcYujcIqicIigboigZ4GZZ4GZY32VZX2VXniQdIiaY3iNZHyQS15zTmJ0QFVwm7TGVG2HRVduR1htPE1hOEZZNEJVLz1TKzlPKjhOKDZMJzVLIzNJLj1QLj5RLj1TLD1SNkleS15zWW6DXHSKXHSMWnKKWHCIU2mCUmqCUmiBUGZ/UmiBUWh+TmV7TGN5S2J4SWB2R150R112RVt0R112R150PFFnRFpzRVpwQ1huQldtQVZsQ1huQ1huQFVrQVZsPVRqQVZsQVZsPlNpPFFnPFFnPVJoOk9kPExdOUlaOkpbOUlaOEhZOkpbOEhZOUdZN0VXOEZYN0dYN0dYOEZY3efqtsLKi6CwhpyshJ2ugpuvgpytf5isepWqeJOoeZGndpGmdI+kc46jco2ico2icYyhb4mhboiga4acZ3mKVml+U2Z7Umd8YHaMWG2IkKm7VW+HQVVuPE5lNkhfM0NaMD9SLDtOKTdKJDJEIS0/IS4+His5Hyw8Lj5VLj5SMkJZPU9mTV92ZHmOXHSKWnKKWHCIVW2FVGyEVW2FVWuEUWeAUml/TWN8TWN8S2J4SWB2SWB2SmF3SF53R150Rl1zSF91PVJoQ1lyRVpwQ1huQldtQ1huQ1huQldtQVZsQFVrPlVrQVZsQFVrPlNpPVBlO0xfLTxPNURXN0ZZOEdaNkVYOEhZOEhZOEhZOEhZOEhZOEZYN0VXN0dYNUVWOEZYfJSqcYicYXeJgZOhs8LM0t/h2ePm4Obm4ufm3+fo3ebp1uTnr77HfZamd5CkdY6ldI+kcouicoqicIqfiJqmW2x/SltwUGF2Y3+XXXePdo6kUmuFSl53Q1dwO01kN0deM0RZMT9VLDtOKTdKJjRGJTFDJDFBIy4/MkJZKjpONUdeRFZtVWd+XnaKW3OLWHCIV2+HWXGJU2uDUmqCUmiBUWh+Uml/UGZ/TWR6S2F6SV94SV94SmF3Rlx1R112RFpzRl1zPVJoRVlyQllvRFlvQ1huQVZsQVZsQFdtQVZsQFVrQFNoNURaM0FUOklcOEdaOklcOEdaOUhbOEdaN0ZZNURXNURXNENWNENWMD9SLj5PLj5PLj5PNkRWNkZXNUVWpbXAjaW1iKK0hqG1i6W2iKO0hp+whZ6ygpuvgpqufpareJOpb4mhZ36SbYCXmau5ydbe1+Di2OHj2OLhe42epbC5V2l9cYOXaoCUX3eQWXCIUWmCRlt3RFlvPU9mN0deNEVaMkFUMD5UKjlMKDZMJzVIJTNFJTFDNEVaHiw9NEVfO09oTV92X3aMWnKKWXGJWHCIVm6GVGyEUmqCUmiBT2V+UGiATWN8TGN5TGJ7S2F6SWB2R150Rlx1Rlx1RVxyRl1zPlJrRVxyRFlvQFZvP1ZsQlZrQE9lMUBTOklcO0pdOkpbO0pdOUhbOEhbOUhbOEhZOEZYNUNVM0NUMD5QLjxOLDpMKjlJKjlJKzhIKzdJKThIKzhIKzhIKjdHKzhI1Njav8DHoKewfYaSd4OVeYSTdIKSn6myy9HT4ens4+nvz9jes8HLiZ+zh5+vgpuxg56ygJuvf5etepand4ugbIehZ3+XV2+FRFhxMEFXUWiDKz9XPlBlSlx0T2V7TWF6Rlx1Q1lyQ1lyQ1dwQFNsMkNZJzVIJDJFNUdeFCEwNkdeQFRtT2R6X3aMXnaOWHCIV2+HVm6GVGyEUmqCUGZ/TGJ7T2d/TGJ7S2J4SV94SV94SWB2SmF3SmB5R150RFtxRVxyO1BmSVxxMD9SOkhbP01fPExdPEpcPkxfPUteOUlaOEdaNkZXNkRWLj1NKzhIKTZGJzREKDVFKDVDKTRCKTNEJjNBJjNDKjRFKDVFKzVGJjE/Ii07Iy87Iy87JzJAqKq0pqiyoaSsoKOroqWto6au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R2F5R2F5SGJ6RF52Q111RV93R193RV11RV11QlpyQFhwP1dvP1ZsPFRqO1JoOVBmNU1hNEthNExgNUpfMkdcK0BVMEhcMEVaLkNYLkNYL0RZLD9ULUJXMEJVHC02FiQrERMTLz9RLUFTLEBSKz9RLEBSKz9RLUFTLT5RKzpN1N7l1N7l1N7l1N7l093k097i097i093k097i097i093k0tzj0tzj0Nzi0Nzi0Nzizdvhy9fhydXfxdTdvs7ar8LPSWB6PVNsMT1OLjxOJTA+OkpbLT9WMT5OLkJXKDVLHis9MkNaKjxTKTtSJjZNL0FYKTlNOk5nQVdwRFpzRl52Rl52RV54SGJ6SWN7S2R+SWN7S2R+SmN9SmN9SWJ8SWN7SGJ6R2F5RmB4RF52RFx0RmB4Rl52RF52RV11QlhxQ1tzQFhwQVdwPVVtO1JoOlFnNk1jNk1jN05kNEthNktgLkNYMkdcNEleMEVaL0RZL0dbLkJXLkNYLUNVLUJYLkNYK0BVL0NVK0BVLUNVLkRWLEJUMEFULUFTLUFTKjtO1N7l1N7l093k093k093k093k097i093k093k093k093k093k0tzj0d3j0d3j0Nziz9riy9jgydXfxdTdvc/asMPQR154O1FqPUpbMDxPKTZIOUpfLT9WLz5RKj5UKz1ULD5VLT9WLD5VKz1UKTlQMUJXLz5UOk5nQVdwRlx1Rl52RV11R2B6SGJ6SWN7SmN9SmR8R2B6S2R+SWN7RV93SGJ6R2F5R2F5RmB4RV93Q1tzR2F5SGB4RF52RV93Q1tzQ1tzQFhwQVdwPlZuPFRqPVVrOE9lOE9lOE9lNk1jNUxiMUZbNktgNEleMkdcMUZbMkdcLkNYL0RZL0RZL0RZLkNYL0RZL0NVL0RZLUJXLkRWLEJUMERWLUNVLUFTKzxP1N7l1N7l1N7l1N7l1N7l1N7l0t3h097i093k093k093k0tzj0d3j0d3j0d3jz9rizNnhy9jgydXfxNPcvM3asMbSRFx1O1BrPU5hLjtNKj5XKj9VK0BWK0BWK0BWLD5VLEFXLkBXLT9WK0BWLD1ULD1SMUFYPU5oQVdwRFpzRFx0Rl52Rl95SGJ6SmR8S2R+TGZ+RmB5SWJ8SWN7SWN7SGJ6RmB4SWF5RV93RmB4Qlx0R2F5R2F5RV93RF52RFx0RFx0QFhwQVlxP1dvPVVtPlRtOVBmOlFnO1JoOE9lN05kM0tfNk1jNUpfMUldM0hdM0hdMEVaMUZbMEVaL0RZMEVaL0RZMkVaLkNYLkNYLUJXLEJULUJXLEJULkRWLUFT1N7l1N7l1N/j1N7l093k097i097h093k093k093k093k0tzj0d3j0d3j0Nziz9rizNnhy9jgydXfw9Tdvs/crcPPQllzPFFsKz1ULUFaK0FaLUJYLkNZLkNZLUJYMEJZL0RaMUNaMUNaL0NZMEFYJzVLNUVcPk9pQFZvRFpzRFx0Rl52Rl95RmB4SGJ6SmN9TWd/SGF7SGF7SWN7SGJ6R2F5R2F5SWB6RmB4R193RV11R2F5SGJ6Rl52Q111Rl52RFx0QVlxQ1tzP1dvQFhwP1VuPFNpOlJoPFNpO1JoOlFnNU1hN05kNEthM0pgNElfNEleM0ZbM0hdMEVaMUZbMEVaMUZbNEdcL0RZL0RZMEVaL0RZL0VXLkNYL0RZLUFT1N7l1N7l1N7l1N7l1N7l1N/j1N/j097i097i093k093k0t7k0t7k0t7k0dzkztvjzdriy9fhxtXewtTfvc7brcPPPVRuO1BsLkNZMkRbMUVeMERdMkZfMUVeMEVbMUVeMkZfM0VcNEZdMkRbMUNaJTZLNUZgPFBpP1VuRFpzRV11Rl52RV93RmB4SGJ6R2B6TGZ+R2B6SWJ8SWN7SGJ6RmB4R2F5SGJ6RmB4Rl52Rl52SGJ6SGJ6RmB4RV93Rl52RV11Q1tzRFx0QlpyQlpyQFhwPlVrPlNpPVRqO1JoOVBmN09jN05kN05kNk5iM0pgNEthMkdcM0hdMkdcM0hdMkdcM0lbM0hdMUZbMEVaMUZbLkNYMEVaL0RZL0RZLkFW1N/j1eDk1eDk1N/j1N/j1N7l1N7l1N7l097i1N7l093k093k0t7k0t7kz93jztvjzNnhydjhxdbfwdPevc7bobjIPFVtNk5oMUVeNEhhNUliNEhhNEhhNUliNEhhNEhhNUliM0dgM0ReMkRbMEJZLT1UNkhfPFBpQFZvRVt0Rlx1Rl52RV54RV54R2F5R2B6SGN4R2F5R2B6R2F5SGJ6R2F5RmB4SGB4Rl95RVx2Q1tzRV93R2F5SmJ6RV93Rl52RFx0Q1tzRl1zQlpyQ1tzQFhwQFdtP1VuPVVrPFNpPFNpN09jOlFnOE9lNEthNk1jNUxiNEleNkthM0tfNEleM0hdM0hdN0tdNEdcMUZcMUZbMEVaMUZbMUZbMEVaL0JX1eDk1N/j1eDk1N/j1N/j1N7l097i1N/j1N/j093k093k0t7k0t7k0NvjztziztvjzdriydjhxdXhwdPevM/cmrPDO1RsNU1nM0liNkxlM0liNEpjNkpjNUliNkpjM0dgNElfM0heM0ReMkRbMUNaMEBXNkhfPFBpQFZvRFpzRVt0Rl52Rl95R154RmB4Rl95SWR5RmB4SWJ8R2F5SWN7R2F5RV93Rl52RV93R193QVlxRV93R2F5SGB4RF52Rl52Q1tzQlpyRVxyQVlxQ1tzQVlxQllvPlRtPlZsPVRqO1JoOlJmPFNpOlFnN05kNk1jN05kNUpfNk1jNk1jM0pgNktgM0hdN0xiM0hdMkdcMkdcMkdcMkdcMUZbMEVaMUZb1eDk1eDk1eDk09/j1N/j093k1N7l1N7l09/l09/l09/l09/l0t3lz9zkztvjzdrizNjix9bfw9XgwNLduc7dkKq6PVVtNkpjOE5nOE5nNkxlNUtkNkpjNUliNkpjNEhhNEhhNUZgMkRbMUNaMUJXL0BVN0lgOk9lQVVuQ1lyRFpzRV11Rl54R193RmB4R2B6R2B6Rl95Rl95Rl95R2B6R2B6RmB4RF52Rl52SGB4QlpyQ111SGJ6RV93Rl52Rl52QlpyQlpyQ1tzQ1tzRFx0QVlxQllvP1VuP1ZsPlRtPFNpO1JoPFNpOlFnOVBmN05kNk1jNkthOVBmN05kNUxiNUxiNUpfNUxiNUpgNElfNElfM0hdM0hdNEleMkdcM0Zb1N/j1ODm1N7l1ODk1N7l1ODk1N/j1N7l09/l09/l0t7k0N7k0N3lz9zkzNzjytrhydrjxtbiw9PfvNHeuc7dlrDCQFhxO1FqO1FqOU9oOExlOExlNkpjNEhhNUZgNUZgM0VcMkJZMkJZNkZdOUlgO0tiOUtiOk9lP1VuRFpzRVt0Rl52R193Q111RmB4R2B6SGJ6RmB4RV54R2B6R2B6R2B6RV93SGB4R193R2B6Q1tzRFx0SWN7RF52Q111R193QlpyQVlxQ1lyRFx0RFx0QlpyQFhuQFdtP1dvP1ZsPlRtO1NpPFNpOlFnOlJoOE9lN05kNkthOE9lOVBmNk1jNk1jM0tfN05kNktgNEthNEleNEleNUpgNUpfM0hdNUhd1ODk1d/m09/j09/j1d/m1ODk09/l09/l09/l0t7k0N7k0N7kz9zkzd3kzd3kytvkydnlxNfivtPhsszdmLXLUGiEQFhwO1FqPFJrOU1mOExlNkpjNUZgPE5lQlRrQ1VsRlhvRlhvRlhvRVduSVtyRlhvRFlvRFlvPVNsRFpzQ1lyRV11SGB4SGB4RV93Rl95R2B6SGF7Rl95R2F5RF52Rl95RV93Rl52RV11R2B6RFx0Rl52SGJ6Q111RF52Rl52Rl52Q1tzQlhxQ1tzRFx0QlpyQVlxQVhuP1dvP1ZsP1VuPFRoPlRtO1JoO1NpO1JoOE9lN0xiOVBmO1JoN05kN05kNU1hOVBmN0xhNUxiNkthNUpfNkthNUpfNEleN0pf09/l09/l09/l1ODk09/l09/l097m0t3l0d/l0d/l0N7mzd7iydrjwdbjqcHWeZCpb4ahcoikaoOeboaecoqgYHuQPVRqUmd9W3CGVmuBV2p/VWp/VGl+UWZ8Umd9Umd9UWZ8UWZ8UGV7S2B2TWJ4TWJ4S2B2S2B2Sl91R112RFpzRV11R193R193RF52R2B6Rl95SGF7Rl95RmB4Rl52R193Rl52Q1tzQ111R154RFx0RF52R2F5RV11RV11Rl52R193Q1tzQ1tzQlpyRFx0QlpyQVlvQFhwQVdwPlZuPVVtPFRqP1dvPFNpO1NpO1JoOVBmNEthO1JoO1JoOVBmOlFnNk1jOlFnNktgNk1jNk1jNk5iNUxiNkthNEleNkth09/l09/l09/l09/l1ODmzt7ky9vkxNfjq8HSepGpdIypdIyqdYumeZOqfJerd5WngJ2sfJerfZiseJSlc46idpChbYaaa4SYaYGVa4CVZ3yRY3iNYHWKXnOJWm+FWG2DWG2DVmuBVmuBVGuBVGuBVWqAVGuBT2Z8TGN5SV51SV12RV11Rl52R193RF52SWJ8Rl95SWJ8R2B6RV93Q1tzSGB4RV11Q1pyQ111Rl13Q1tzQlx0RmB4Rl52Rl52RV11RF52Q111QlpyQ1tzRV11Q1tzQlpwQFhwP1dvPlZuPVRqP1ZsPlVrPFRqO1NpPVRqOlFnNUxiPVRqO1JoPFNpO1JoN05kOlFnNktgN05kN05kNk1jNk1jOE9lNktgNkthy9zkxNflpr7QepGpdo6rd5Crdo+pfJSsgZywf5usg5+wgZ2ugJytgJytf5usgZ2ugZusfZeoeZKmeZWmeZOkeJKjdY+gcoqeboaabYWZa4OXY3qQY3qQYXmNXHSIX3SJXnOIXXKHWXGFVm2DVGuBVm2DVWyCUGd9UGd9T2Z8TmV7TGN5SmB5Rl52RV93R2B6Rl95SWJ8R2B6RV93RFx0RV93RF52QVlxRl52RV11RFx0RV11RV11RV11RV11RV11RV11RFx0QVlxQ1tzR193QVlxQlpyQFhwP1dvP1dvPlZuPlVrP1VuPFJrO1NpPFNpOlFnOE9lPVRqPFNpO1JoO1JoN05kO1JoNktgO1JoOVBmN05kN05kN05kNEthN0xid5CpfpivhZ6yf5ush6O0haKxhqOyhqCwh6GxiqS0iqS0h6GxgpythqCxhqCxgZusgJqrf5mqe5Wmd5GieZOkdpChdI2hc4ygcYmdb4ebboaaaYGVaICUZHyQYXiOW3KIW3KIW3KIXnWLXXSKV26EVGuBU2qAUWh+T2Z8TmR9TWR6TGJ7T2Z8S2F6RV93RV54Rl95R2B6SF95RV93RFx0R191RF52Q1tzRl52RFt1Q1tzRFx0Q1tzRV11RFx0RV11RFx0RFx0QVlxQ1tzRV11QVlxQ1tzQFhwP1dvPlZuP1VuP1dvP1VuPFNpPFNpPFNpPFNpOE9lPVRqPVRqPFNpO1JoN05kPFNpNUxiO1JoOE9lNk1jN05kN05kNEthOE1ijKa2iKKyiaOzh6GxiKKyhqCwhZ+vhqCwiKKyiKKyhqCwhJ6ugpytfpipf5mqf5mqfJanfZeoe5WmeZOkepSld5Gid5CkdY6ic4ufcYmdZ3+TaoKWaICUZ3+TZHuRYnmPX3aMXHOJW3KIV26EWG+FV26EVm2DVm2DVm2DUml/UWh+TmV7T2V+SV94SWB4TGJ7SGJ6RV93R193RV93RV11R191Qlx0QVlxQ111QVp0RFx0RV11Q1tzRFx0Q1tzQ1tzRV11QVlxQFhwRFx0RFx0QFhwRVt0QVdwPlZuQFhwP1dvPlZuP1VuPFRsPlVrO1JoPVRqOE9lPVRqPFNpPVRqPFNpOVBmPVRqNUxiO1JoOlFnN05kN05kOE9lNk1jOE1iiaOzh6Gyi6W1h6GxiKKzhqCwhqCxhJ6vhZ+wg52ug52ugJqrg52ugpytgJqrg52ugZurgZurfZeoeZOkeJGlbYaacoydcoufcoydc4ufbISYbISYboWbZn6SY3qQYHeNYHeNYHeNXXSKX3aMXHOJV2+HWG+FV26EVGuBTWR6UmiBUGh+UGiAUWmBTmZ+TmR9S2N7Rl13RV93RV93Q1tzR193Q111QVlxQlx0RV11Q1tzQ1tzRFx0RFx0Q1tzQlpyRV11QlpyP1VuRFx0Q1tzQVdwQVlxQVdwQFhwQFhwPFRsPlZuPVVtPFRsQFdtO1JoO1JoN05kPVRqPFNpPFNpPVRqOE9lPVRqNUxiPFNpO1JoN05kOE9lOE9lNk1jOE9li6W1iaOziKKyiKKyiKKzgpytgZusgZushJ6uhZ6uhp+vhJ2tiKGxhp6wgZmrf5epgJiqgJiqfpaoeZOkepSldY+gd5Gid5GicImdboaaaICUboaaa4OXaoGXZ3+TYnmPY3qQYnmPX3aMXnWLU2qAWnGHWXGHWnGHVGuBVWyCV26EU2uDU2mCU2mCT2V+TmZ+TGR8S2N7SmJ6Rl52RFx0R193RVx1QFVuRV11RFx0RFx0RFx0RFx0QlpyQlpyQ1tzQ1tzQ1tzPlRtQ1tzQlpyQlhxQFhwPlRtQVdwPlZuP1ZsP1VuPlZuPVVtPVVrO1JoO1JoOVBmPVRqPVRqPVRqPVRqOVBmPlVrN0xhPFRoO1JoOE9lOVBmOE9lNUxiOE9liKGxi6O1h6GxhqCwhqCxhJ6uiKCyhJyuhp6whp6wgJiqhJyuhJyuhp6wgpqsgJiqeZOkepSleJCid5GiepSlboiZdI6fc4ufc42edY2hboaaa4OXa4OXZn2TZn6SYnmPYHeNZXySXnWLXnWLXHOJWnKIWnKIWnGHVm6EV2+FUmqCVGyEVGqDT2d/UWmBTWV9TmZ+TWV9TGR8SmJ6QVlxR193RFx0P1VuRl52RFx0RFx0RV11QlpyRFx0QVlxQVlxRFx0QlpyPlRtQ1tzQVlxQVdwQFhwPlRtQFhwQVdwP1dvP1ZsPlZuO1NrPFNpOlFnPFNpOlFnPVRqPFNpPlVrPFNpO1JoP1ZsNktgPVRqOlFnOVBmOE9lOVBmNk1jOE9li6S0iaKyiaKyiKGxh5+xepSlg5utgJiqhJyuhZ2vhp6wgJiqgJiqfpaoe5OleZGjdY2fe5OldpCheJCiepKkdY+gcIqbcoufcYqecImdcIicZH2Rb4ebbISYZ4CUZn2TYnmPX3aMYXiOYHeNXXSKWXGHWHCGVm6GV2+HVm6GVGyEU2uDUGiAUWmBT2d/U2uDTWV9TGR8SmJ6SmJ6S2F6R193Q1tzP1dvRV11QlpyQVlxRV93RFx0Q1tzQlpyQVlxRV11Q1tzPVNsQlpyQVdwQVlxP1dvPlRtQVlxP1dvP1VuPlZsQFZvPFRsPFRqO1FqPFNpOlFnPVRqO1JoPlVrPVRqPFNpP1ZsNk1jPVRqOVBmOVBmOE9lOVBmNk5kOVBmgpqsiKCyhZ2vhp6wg5uthJyuhZ6ugZmrf5epfpaofpipe5OlgJiqf5epe5Olf5epfZWnfJSmepKkdY+gd5Gid4+ha4OVcYmddI2hb4icboebaoOXa4SYaoOXZX6SY3uRZn2TYXiOX3aMXnaMXXWNWnKIWXGHVm6GVGyEVGyEVW2FVGyEUGiAU2uDTmZ+UGiATmZ+UWmBTWV9SmJ6SmB5SF53RV11PVVtRFx0QVlxQVlxQ111Q1tzRFx0QlpyQ1tzRFx0Q1tzP1VuRFx0RFpzQlhxPlZuP1VuQlpyP1dvPlZuPlZsQVhuPlVrO1NpO1JoPVRqOlFnPFNpPFNpPFNpPlVrO1JoPVRqNk5iPlVrOVBmOVBmOE9lOVBmNk1jOVBmiKGxhZ2vhJyuiKCyg5utgZmrfZWnf5epgZmrgZmrgJiqf5mqf5epgJmpgJmpfJSmfZWndY2fb4eZd4+heJCicoydcIqbcoufb4icbYaabIWZa4SYaoOXZX6SaIGVYnqQZHySYnqQYHiOXHSKXHSKXHSKWHCGVW2FUGiAUmqCVW2FVGyEUGiAUmqCU2uDTmZ+TmZ+TmZ+UWeASV94SV94S2F6RmB4Rlx1RV11QlpyQlpyRV11RFx0RFx0QlpyRFx0Q1tzQ1tzP1VuQ1tzQlpyQlhxP1dvP1VuQ1lyQFZvP1VuPlZuQFZvPlZuO1JoPVNsPFNpPVRqPVRqO1JoPFNpP1ZsPFNpPVRqN05kPFNpOlFnOlFnOlFnOVBmN05kOE9lhJ2tgpqshZ2vhZ2vhZ2vhp+vhZ6uhJ2tgpurgJiqgJiqf5epf5epcIiafJSme5Ole5Ole5OleZGjfJSmd4+hdIyec42ec42ebYaaaoOXbIWZbIWZbIWZaoOXaoOXZX2TaIGVZHySYnqQX3eNVm6EWnKKWXGHV2+HV2+HVW2FVGyEUGiAUmqCU2uDUWmBUGiATWV9TGR8TWV9SGB4SWF5R193Rl52Rl52R112Q1lyQFhwQ1tzRFx0RFx0QlpyRFx0Q1tzRFx0PFJrQlpyQlhxQFhwQFhuQFZvQ1lyQVdwQFZvPlZsQVdwPlRtPFNpP1VuO1JoPFNpO1JoO1JoPVRqPlVrPFNpPFNpOE9lO1JoOVBmPFNpOlFnOVBmN05kN05kiKGxiKGxhp+vg5uthZ2vgpurgJmpgpqseJCifZWngJiqgZmrfpaofpaof5iof5iof5epeZGje5OlepKkdIyedo6gc42eboebcImdbYaabIWZaoOXaYKWaYKWaIGVZn6UYXmPZHySX3eNX3eNXHSKWnKIWHCGVW2DVGyEU2uDU2uDUGiASmJ6U2uDT2d/TmZ+TGR8S2N7SWF5RV11SGB4RV11RV11R112RFpzRlx1SF53QlpyQlpyQ1tzP1dvRFx0QVlxRV11PVNsP1dvQVdwQVlxQFhuP1VuQVdwQlhxPlRtPVVtQldtPlRtO1NrPFNpO1JoPVRqPVRqPVRqPFNpP1ZsPVRqPFNpN05kPFNpOlFnOlFnPFNpOE9lNk1jNk1jhZ6uhJ2tgpqsgJmpepOjgpqsg5ysfpaof5epf5epf5epgJiqgZmre5OlfZWne5OldY2feJCieJCidIyeeJCicoydb4mab4icboebbIWZaoOXaICWYXmPYn2SZoGWZX2TZ3+VZHySXXWLXnaMXXWNWXGHWnKIVW2FTGR8U2uDUmqCUWmBUmqCTWV9TGR8S2N7SmJ6S2N7S2N7R193Rl52SGB4Rl52Rl52Rl52R112Q1lyRFpzRFpzQ1lyQFhwRFx0QVlxQlpyOlBpP1dvQlhxQlhxQFhuP1VuQFZvQFZvP1VuPFRsP1VrPVRqPFNpPlRtO1JoPlVrPVRqPFNpPFNpPFNpPVRqO1JoNk1jO1JoO1JoOlFnO1JoOVBmOE9lOk9lhZysiJ6vgZmrgZqqgJiqgJiqf5iofpaofpaof5epe5OleZGjepKkepSlfJSme5Ole5WmdIygcoufbYiccoqecYmbcoydbIWZaoOXbIWZaoOXZ3+VaICWZX6SY36TY3uRYnqQXXiNXnaMXnaMU2uDWXGHWHCIVm6GVW2FU2uDU2uDUWmBTmZ+TWV9S2N7S2N7SmJ6SWF5SGB4S2F6SmJ6SGB4SGB4Rl52R193R112Rlx1RFpzRVlyO1JoQlpyQFhwQlpyQlpyOU9oP1VuQlpyQFZvQVdwP1RvP1dvP1VuPlRtPFRqPlVrPlVrPFNpPFNpO1JoPVNsPVRqPFNpPFNpPVRqO1JoO1JoOE9lOlFnPFNpOlFnPVRqOlFnOFBkOE9lgZqqg5utgJiqgJmpgZmrgJmpf5epdo6ggJiqe5OlfJSme5OleZGjdpCheJKjcoydd4+hdY6icIqbc4ygbYaab4icboebbIWZbIWZaoOXaYKWZ3+VZ3+VZX2TX3qPX3eNV2+FW3OJYHiOXnaMXHSMWXGHV2+HWXGJVW2FU2uDUWmBUWmBUGiAS2N7T2d/S2N7TWV9S2N7S2N7SmJ6SmB5SWF5Rl52RV11Rlx1R1t0PFBpRVt0QlhxRlx1QVdwQVdwRFx0RFx0OU9oPlRtQVdwP1VuQVdwQFVwQVdwP1VuQFZvPVVrPVRqP1VuO1NrPlRtO1JoPVNsPFNpPFNpOlFnO1JoO1JoOlFnOVBmOVBmO1JoOVBmPVRqOlFnOVFlOE9lgpurgpurhJurfZWngZmrfpaofpaofpaofJSmepKkdpChdpCheJKjeJCid4+hc4udcIiado6gcYqec42ecIqbdY+gcoufa4SYaoOXaYKWaICWZX2TXnaMXHSKZHySX3eNXHeMXXWLW3OJXHeMXHSKWHCIVW2DVW2DVm6EU2uDUmqCVGyEUGiAUGiAT2d/TmZ+S2N7TGR8SWF5SWF5R193R193QlhxQ1lyR112Rlx1R193R193Rlx1Q1lyQ1pwQ1lyQlhxQVdwN01mPlRtQlhxP1VuQFhuQVdwQFZvPlVrPVNsPVRqPVJoPVNsPVRqPVRqPFNpPVRqOlFnO1JoOlFnOlFnPFNpO1JoOE9lOVBmOVBmOlFnO1JoOlFnOE9lOVBmgJmpfpaogJiqf5epfZWnfZWne5OlepKkepSleJKjdY2fcoqccYiedpChdY2feJCicoydcoyddI6fcoufbYaabYWbb4macImdaYKWYHiOZ3+VZn+TZHySYHuQXnmOYXyRX3qPXnaMWnWKWXOLWHCIWnKIVGyEVm6GWHCGVW2FU2uDT2d/UmqCUGiATGR8TWV9TWV9S2F6S2N7Q1pwSWB2R112R193SGB4R193SWF5RVt0RVt0Rlx1Rl52RVxyQ1lyP1VuQlhxQ1dwP1VuQVdwP1VuPFRqP1VuP1dvPVVrPFNpPlVrPVJoPVRqPVRqPVRqO1JoP1ZsOlFnPFNpOlFnOE9lPFNpOVBmOVBmOlFnOlFnOlFnOE9lOVBmOU5jO1Blf5epgJiqgJiqgZmrfpaofJSme5Oldo6gdpChdY6ic42edI6fdo6gdI6fdo6gc4ufb4macYqecImdb4icbYaaYXqOa4WWaoOXaIGVaIGVYnqQYn2SYHuQXHeMXnmOXHeMXXiNW3aLVm6EWXGHW3OLW3OJVm6EVW2DVW2FVGyEUWmBUWmBUWmBUGiATmZ+TmR9RFtxTGJ7S2N7SmJ6SmJ6TGR8S2N7R193R112Rl52Rlx1Rl52RVt0QFZvQ1lyQ1lyQ1lyQVdwPlRtP1VuP1VuP1ZsPFNpQFZvPlRtPlVrPlRtPVRqOk5nP1VuPFNpPVRqO1JoPlVrO1JoO1JoOVBmOVBmO1JoOVBmOVBmPVJoOVBmOlFnO1BmOE9lOVFlOk9lf5epgJiqf5epd4+je5OneZGjepKkd5Gid4+hdY2hdY6icoufb4iccImdcImdcImdcoufZHySb4icbYaabYaaaYKWYnqQY3uRZX2TYnqQZX2TYHuQYXmPXnaMXHSKWXGHX3eNYnqQW3aLWnKKVXCFV3KHV2+HVW2FVGyEVW2FU2uBUWmBT2Z8R112TWV9T2d/TmZ+TWV9SmJ6SWF5SWF5SmJ6SmJ6SGB4Rl52Rlx1Q1dwRFpzRVt0Q1pwQVdwQVdwRFpzQ1lyQVdwP1VuQFZvP1ZsPVRqPlRtPlRtPVNsPVNsO1NpNk1jPFNpPFNpOlFnO1JoPlRtOlFnO1JoOlFnOVBmOlFnOVBmO1BmOVBmOVBmOE9lO1BmO1BmOFBkOU5ke5Olf5epfZWnfZWneJKjeJCidpChdI6fc42ec4ufcoufcYqeboebX3eNbYaab4iccImdbYaaaYKWaIGVZ4CUZ3+VYHuQZHySYXmPYHiOYXmPWnWKYXmPYXmPXnmOXHeMWnWKWHOIW3aLVnGGW3OLWnKIV2+FV2+FWHCITWV9UGZ/UGh+UGiATWV9TWR6TmV7TWR6TWR6S2F6SGB4SGB4R193Rl52QVdwR193Rlx1R112Q1lyRVt0QVdwQlhxQ1lyRFtxRl1zQllvQFZvQVVuO1FqPVRqPlVrPVRqPFNpPFNpO1NpNkthPVRqPFNpOlFnPFNpPFNpOlFnOlFnOlFnOlFnOlFnOlFnPFFmOlFnOVBmOE9lO1BmO1BmOE9lOE1jfZWne5OleJCidpChdpChdpChdY6idY6icYqeZX2TaYGXboebb4icb4icbYaaaICWZ3+VZ3+VZ3+VYn2SZH+UZX2TY3uRYnqQXXiNYnqQYHiOYnqQX3eNXHeMW3aLWnWKW3OJW3OJWnKIW3aLWHCGWHCGSWF3V26EVm6EVGyEUWmBUGiAUGiATWV9UGd9T2V+TmV7S2N7SmJ6SmB5RVt0SGB4R193R193RV11R112Q1lyRFpzRVt0RFpzQlhxRFpzRFtxQVhuPlRtQFdtQFZvQFZvPlVrPVRqPVRqPVRqO1JoPVRqNUpgPVRqO1JoOlFnO1JoPFNpOlFnOlFnO1JoOVBmOE9lOVBmPVJoOlFnO1BmOE9lO1BmOE9lOk9lOk9leJCieJCid5Gido6gdIygbISYaYKWcImdb4icb4maboebbYaabISaa4SYaYGXZn6UZoGWZH+UaYKWY3uRYnqQYXyRYXyRYHuQY3yQYnuPYnqQX3qPXnaMXnaMXHeMWnWKXnmOWnKIW3OJS2N5WXGHV3KHVG+EVGyCVXCFVWuEUmqAU2uBUGiAUWl/Uml+UGZ/T2V+Rlx1SmJ6SGB4SmJ6R112R112Rlx1RFpzRVt0Q1lyRFtxRlx1Q1lyRVxyQVhuQFdtRFtxQVhuRVxyQ1lyQFZvP1ZsPlVrPlVrPFNpOlFnO1JoNUpgPVRqO1JoOlFnO1JoOlFnOlFnO1JoOVBmOlFnOVBmOk9lOlJoOVBmOVBmOE9lO1BmO1BmOk9lOU5keZGjdIyeaIGVcoufcoufcImdcYqebYaaa4SYboeba4SYaIGVaoKYaICWZn+TZ3+VZHySYnqQZX2TYn2SZ3+VZn+TZHySZHySYnqQYHiOYXmPXXiNX3eNXnaMXXWLVm6EVm6EW3OJVXCFVXCFVG+EVXCFVG+EVGyCVW2DU2uDUmqAU2uBUGh+UGiASmB5TWR6TmZ+TGR8SmJ6SWF5SGB4R193Rlx1Rlx1RFpzQ1lyRFtxRVxyR112QllvQ1pwQ1pwRFtxRFtxRVt0QVdwQFdtQVhuQFdtQldtP1ZsPFNpPlNpO1BmM0pgPFNpO1JoOVBmOVBmO1JoOVBmPFNpOVBmOlFnOE9lO1BmPFRqOVBmOlFnOk9lO1BmOk9lPFFnOE1j";
        $isRemovePhoto = false;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository
            ->method('getByUserId')
            ->willReturn($this->userData);

        $this->userRepository
            ->method('updateProfile')
            ->willReturn(true);

        $this->expectExceptionMessage("Não foi possível processar a imagem: Conteúdo não é uma imagem válida (os tipos aceitos são: .jpeg, .png e ,.gif).");

        $this->userService->set(
            $userId,
            $nome,
            $sobrenome,
            $senha,
            $photoBase64,
            $isRemovePhoto
        );
    }

    public function testSetFalhaAtualizarPerfil(): void
    {
        $userId = $this->userData['id'];
        $nome = $this->userData['nome'];
        $sobrenome = $this->userData['sobrenome'];
        $senha = "Senha@123!";
        $photoBase64 = base64_encode($this->userData['photo_blob']);
        $isRemovePhoto = false;

        $this->userData['firebase_uid'] = null; //setamos para desativar o teste de conta firebase
        $this->userRepository
            ->method('getByUserId')
            ->willReturn($this->userData);

        $this->userRepository
            ->method('updateProfile')
            ->willReturn(false);

        $this->expectExceptionMessage("Não foi possível atualizar perfil. Tente novamente.");

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