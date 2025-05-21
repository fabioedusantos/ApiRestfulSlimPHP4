<?php

namespace Tests\Services;

use App\Helpers\GoogleRecaptchaHelper;
use App\Helpers\Valid;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use DateTime;
use Kreait\Firebase\Auth\UserRecord;
use Mockery;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Tests\Fixtures\MockClientRedis;
use Tests\Fixtures\UserFixture;

class AuthServiceTest extends TestCase
{

    use UserFixture;

    private UserRepository $userRepository;
    private Client $redisClient;
    private AuthService $authService;
    private array $userData;
    private UserRecord $firebaseUserData;
    private int $expirationInHours;

    protected function setUp(): void
    {
        $this->expirationInHours = 2;
        $this->userData = $this->getUserData();
        $this->firebaseUserData = $this->getFirebaseUserData();

        $this->userRepository = $this->createMock(UserRepository::class);
        $this->redisClient = $this->createMock(MockClientRedis::class);

        $this->authService = new AuthService($this->userRepository, $this->redisClient);
    }

    protected function tearDown(): void
    {
        $this->userData['is_active'] = 1;
        Mockery::close();
        parent::tearDown();
    }


    // signup()
    public function testSignupSucesso(): void
    {
        $nome = "Fábio";
        $sobrenome = "Santos";
        $email = "fabioedusantos@gmail.com";
        $senha = "Senha@123!";
        $isTerms = true;
        $isPolicy = true;
        $recaptchaToken = "fake-token";
        $recaptchaSiteKey = "fake-token";

        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->with(
                $this->equalTo($recaptchaToken),
                $this->equalTo($recaptchaSiteKey)
            )
            ->andReturn(true);

        $this->userRepository->expects($this->once())
            ->method('getByEmail')
            ->with($this->equalTo($email))
            ->willReturn(null);

        $this->userRepository->expects($this->once())
            ->method('create')
            ->with(
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->equalTo($email),
                $this->callback(function ($senhaHash) use ($senha) {
                    return password_verify($senha, $senhaHash);
                }),
                $this->callback(function ($codigoConfirmacaoHash) {
                    return Valid::isStringWithContent($codigoConfirmacaoHash);
                }),
                $this->callback(function ($expiry) {
                    return Valid::isStringDateTime($expiry);
                })
            );

        $this->redisClient->expects($this->once())
            ->method('rpush')
            ->with(
                $this->equalTo("email_queue"),
                $this->callback(function ($json) use ($nome, $email) {
                    $json = json_decode($json[0], true);
                    if (empty($json['type']) || $json['type'] != "accountConfirmation") {
                        return false;
                    }
                    if (empty($json['email']) || $json['email'] != $email) {
                        return false;
                    }
                    if (empty($json['nome']) || $json['nome'] != $nome) {
                        return false;
                    }
                    if (empty($json['codigo']) || !ctype_digit($json['codigo'])) {
                        return false;
                    }
                    if (empty($json['tempoDuracao']) || strlen($json['tempoDuracao']) <= 3) {
                        return false;
                    }
                    return true;
                })
            )
            ->willReturn(1);

        $info = $this->authService->signup(
            $nome,
            $sobrenome,
            $email,
            $senha,
            $isTerms,
            $isPolicy,
            $recaptchaToken,
            $recaptchaSiteKey
        );

        $this->assertIsArray($info);
        $this->assertArrayHasKey('expirationInHours', $info);
        $this->assertEquals($this->expirationInHours, $info['expirationInHours']);
    }

    public function testSignupFalhaRecaptcha(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(false);

        $this->expectExceptionMessage("Não foi possível validar sua ação. Tente novamente.");

        $this->authService->signup(
            "Fábio",
            "Santos",
            "fabioedusantos@gmail.com",
            "Senha@123!",
            true,
            true,
            "",
            ""
        );
    }

    public function testSignupFalhaNome(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Nome muito curto.");

        $this->authService->signup(
            "F",
            "Santos",
            "fabioedusantos@gmail.com",
            "Senha@123!",
            true,
            true,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupFalhaSobrenome(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Sobrenome muito curto.");

        $this->authService->signup(
            "Fábio",
            "S",
            "fabioedusantos@gmail.com",
            "Senha@123!",
            true,
            true,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupFalhaEmail(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Email deve ser válido.");

        $this->authService->signup(
            "Fábio",
            "Santos",
            "fabão@doemaião",
            "Senha@123!",
            true,
            true,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupFalhaSenha(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage(
            "A senha deve ter no mínimo 8 caracteres, com pelo menos uma letra maiúscula, um número e um caractere especial."
        );

        $this->authService->signup(
            "Fábio",
            "Santos",
            "fabioedusantos@gmail.com",
            "PipoquinhaAçucarada",
            true,
            true,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupEmailJaCadastrado(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmail')->willReturn($this->userData);

        $this->expectExceptionMessage("Email já cadastrado.");

        $this->authService->signup(
            "Fábio",
            "Santos",
            "fabioedusantos@gmail.com",
            "Senha@123!",
            true,
            true,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupFalhaTermos(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmail')->willReturn(null);

        $this->expectExceptionMessage("Aceite os termos e condições para se cadastrar.");

        $this->authService->signup(
            "Fábio",
            "Santos",
            "fabioedusantos@gmail.com",
            "Senha@123!",
            false,
            true,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupFalhaPolitica(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmail')->willReturn(null);

        $this->expectExceptionMessage("Aceite a política de privacidade para se cadastrar.");

        $this->authService->signup(
            "Fábio",
            "Santos",
            "fabioedusantos@gmail.com",
            "Senha@123!",
            true,
            false,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupFalhaCriarUsuarioException(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmail')->willReturn(null);
        $this->userRepository->method('create')
            ->willThrowException(new \PDOException("Erro no banco de dados XPTO"));

        $this->expectExceptionMessage("Erro ao criar usuário. Tente novamente.");

        $this->authService->signup(
            "Fábio",
            "Santos",
            "fabioedusantos@gmail.com",
            "Senha@123!",
            true,
            true,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupFalhaEnviarEmail(): void
    {
        $nome = "Fábio";
        $sobrenome = "Santos";
        $email = "fabioedusantos@gmail.com";
        $senha = "Senha@123!";
        $isTerms = true;
        $isPolicy = true;
        $recaptchaToken = "fake-token";
        $recaptchaSiteKey = "fake-token";

        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn(null);

        $this->userRepository->expects($this->once())
            ->method('create');

        $this->redisClient->expects($this->once())
            ->method('rpush')
            ->willThrowException(new \PDOException("Erro ao enviar email no Redis."));

        $this->expectExceptionMessage(
            "Não foi possível enviar o email com o código por uma falha interna. Tente novamente."
        );

        $info = $this->authService->signup(
            $nome,
            $sobrenome,
            $email,
            $senha,
            $isTerms,
            $isPolicy,
            $recaptchaToken,
            $recaptchaSiteKey
        );

        $this->assertIsArray($info);
        $this->assertArrayHasKey('expirationInHours', $info);
        $this->assertEquals($this->expirationInHours, $info['expirationInHours']);
    }


    // resendConfirmEmail()
    public function testResendConfirmEmailSucesso(): void
    {
        $email = "fabioedusantos@gmail.com";
        $recaptchaToken = "fake-token";
        $recaptchaSiteKey = "fake-token";

        $tempo = $this->expirationInHours * 60 * 60 - 1;

        $this->userData['firebase_uid'] = null;

        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->with(
                $this->equalTo($recaptchaToken),
                $this->equalTo($recaptchaSiteKey)
            )
            ->andReturn(true);

        $this->userRepository->expects($this->once())
            ->method('getByEmailWithPasswordReset')
            ->with($this->equalTo($email))
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->userRepository->expects($this->once())
            ->method('updateResetCode')
            ->with(
                $this->equalTo($this->userData['id']),
                $this->callback(function ($codigoConfirmacao) {
                    return Valid::isStringWithContent($codigoConfirmacao);
                }),
                $this->callback(function ($expiry) {
                    return Valid::isStringDateTime($expiry);
                })
            )
            ->willReturn(true);

        $this->redisClient->expects($this->once())
            ->method('rpush')
            ->with(
                $this->equalTo("email_queue"),
                $this->callback(function ($json) use ($email) {
                    $json = json_decode($json[0], true);
                    if (empty($json['type']) || $json['type'] != "passwordReset") {
                        return false;
                    }
                    if (empty($json['email']) || $json['email'] != $email) {
                        return false;
                    }
                    if (empty($json['nome']) || $json['nome'] != $this->userData['nome']) {
                        return false;
                    }
                    if (empty($json['codigo']) || !ctype_digit($json['codigo'])) {
                        return false;
                    }
                    if (empty($json['tempoDuracao']) || strlen($json['tempoDuracao']) <= 3) {
                        return false;
                    }
                    return true;
                })
            )
            ->willReturn(1);

        $info = $this->authService->resendConfirmEmail(
            $email,
            $recaptchaToken,
            $recaptchaSiteKey
        );

        $this->assertIsArray($info);

        $this->assertArrayHasKey('expirationInHours', $info);
        $this->assertEquals($this->expirationInHours, $info['expirationInHours']);
    }

    public function testResendConfirmEmailFalhaRecaptcha(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(false);

        $this->expectExceptionMessage("Não foi possível validar sua ação. Tente novamente.");

        $this->authService->resendConfirmEmail(
            "fabioedusantos@gmail.com",
            "",
            ""
        );
    }

    public function testResendConfirmEmailFalhaEmail(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Email deve ser válido.");

        $this->authService->resendConfirmEmail(
            "fabão@doemaião",
            "fake-token",
            "fake-token"
        );
    }

    public function testResendConfirmEmailFalhaUsuarioNaoEncontrado(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmailWithPasswordReset')->willReturn(null);

        $this->expectExceptionMessage(
            "Não foi possível gerar o código de confirmação. Usuário não encontrado ou não possui uma redefinição de senha ativa."
        );

        $this->authService->resendConfirmEmail(
            "fabioedusantos@gmail.com",
            "fake-token",
            "fake-token"
        );
    }

    public function testResendConfirmEmailFalhaContaFirebase(): void
    {
        $tempo = $this->expirationInHours * 60 * 60 - 1;

        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmailWithPasswordReset')->willReturn(
            $this->userData +
            [
                'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
            ]
        );

        $this->expectExceptionMessage("Não é possível redefinir senha de conta Firebase/Google.");

        $this->authService->resendConfirmEmail(
            "fabioedusantos@gmail.com",
            "fake-token",
            "fake-token"
        );
    }

    public function testResendConfirmEmailFalhaGerarCodigoConfirmacao(): void
    {
        $this->userData['firebase_uid'] = null;
        $tempo = $this->expirationInHours * 60 * 60 - 1;

        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmailWithPasswordReset')->willReturn(
            $this->userData +
            [
                'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
            ]
        );
        $this->userRepository->method('updateResetCode')->willReturn(false);

        $this->expectExceptionMessage("Não foi possível salvar o código de confirmação. Tente novamente.");

        $this->authService->resendConfirmEmail(
            "fabioedusantos@gmail.com",
            "fake-token",
            "fake-token"
        );
    }

    public function testResendConfirmEmailFalhaGerarCodigoConfirmacaoException(): void
    {
        $this->userData['firebase_uid'] = null;
        $tempo = $this->expirationInHours * 60 * 60 - 1;

        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmailWithPasswordReset')->willReturn(
            $this->userData +
            [
                'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
            ]
        );
        $this->userRepository->method('updateResetCode')->willThrowException(
            new \PDOException("Erro no banco de dados XPTO")
        );

        $this->expectExceptionMessage("Não foi possível salvar o código de confirmação. Tente novamente.");

        $this->authService->resendConfirmEmail(
            "fabioedusantos@gmail.com",
            "fake-token",
            "fake-token"
        );
    }

    public function testResendConfirmEmailFalhaEnviarEmail(): void
    {
        $email = "fabioedusantos@gmail.com";
        $recaptchaToken = "fake-token";
        $recaptchaSiteKey = "fake-token";

        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $tempo = $this->expirationInHours * 60 * 60 - 1;

        $this->userData['firebase_uid'] = null;

        $this->userRepository
            ->method('getByEmailWithPasswordReset')
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->userRepository
            ->method('updateResetCode')
            ->willReturn(true);

        $this->redisClient->expects($this->once())
            ->method('rpush')
            ->willThrowException(new \PDOException("Erro ao enviar email no Redis."));

        $this->expectExceptionMessage(
            "Não foi possível enviar o email com o código por uma falha interna. Tente novamente."
        );

        $info = $this->authService->resendConfirmEmail(
            $email,
            $recaptchaToken,
            $recaptchaSiteKey
        );

        $this->assertIsArray($info);

        $this->assertArrayHasKey('expirationInHours', $info);
        $this->assertEquals($this->expirationInHours, $info['expirationInHours']);
    }


    // forgotPassword()
    public function testForgotPasswordSucesso(): void
    {
        $email = "fabioedusantos@gmail.com";
        $recaptchaToken = "fake-token";
        $recaptchaSiteKey = "fake-token";

        $this->userData['firebase_uid'] = null;

        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->with(
                $this->equalTo($recaptchaToken),
                $this->equalTo($recaptchaSiteKey)
            )
            ->andReturn(true);

        $this->userRepository->expects($this->once())
            ->method('getByEmail')
            ->with($this->equalTo($email))
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateResetCode')
            ->with(
                $this->equalTo($this->userData['id']),
                $this->callback(function ($codigoConfirmacao) {
                    return Valid::isStringWithContent($codigoConfirmacao);
                }),
                $this->callback(function ($expiry) {
                    return Valid::isStringDateTime($expiry);
                })
            )
            ->willReturn(true);


        $this->redisClient->expects($this->once())
            ->method('rpush')
            ->with(
                $this->equalTo("email_queue"),
                $this->callback(function ($json) use ($email) {
                    $json = json_decode($json[0], true);
                    if (empty($json['type']) || $json['type'] != "passwordReset") {
                        return false;
                    }
                    if (empty($json['email']) || $json['email'] != $email) {
                        return false;
                    }
                    if (empty($json['nome']) || $json['nome'] != $this->userData['nome']) {
                        return false;
                    }
                    if (empty($json['codigo']) || !ctype_digit($json['codigo'])) {
                        return false;
                    }
                    if (empty($json['tempoDuracao']) || strlen($json['tempoDuracao']) <= 3) {
                        return false;
                    }
                    return true;
                })
            )
            ->willReturn(1);

        $info = $this->authService->forgotPassword(
            $email,
            $recaptchaToken,
            $recaptchaSiteKey
        );

        $this->assertIsArray($info);

        $this->assertArrayHasKey('expirationInHours', $info);
        $this->assertEquals($this->expirationInHours, $info['expirationInHours']);
    }

    public function testForgotPasswordFalhaRecaptcha(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(false);

        $this->expectExceptionMessage("Não foi possível validar sua ação. Tente novamente.");

        $this->authService->forgotPassword(
            "fabioedusantos@gmail.com",
            "",
            ""
        );
    }

    public function testForgotPasswordFalhaEmail(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Email deve ser válido.");

        $this->authService->forgotPassword(
            "fabão@doemaião",
            "fake-token",
            "fake-token"
        );
    }

    public function testForgotPasswordFalhaUsuarioNaoEncontrado(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmail')->willReturn(null);

        $this->expectExceptionMessage("Não foi possível gerar o código de confirmação. Usuário não encontrado.");

        $this->authService->forgotPassword(
            "fabioedusantos@gmail.com",
            "fake-token",
            "fake-token"
        );
    }

    public function testForgotPasswordFalhaContaFirebase(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmail')->willReturn($this->userData);

        $this->expectExceptionMessage("Não é possível redefinir senha de conta Firebase/Google.");

        $this->authService->forgotPassword(
            "fabioedusantos@gmail.com",
            "fake-token",
            "fake-token"
        );
    }

    public function testForgotPasswordFalhaGerarCodigoConfirmacao(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmail')->willReturn($this->userData);

        $this->expectExceptionMessage("Não é possível redefinir senha de conta Firebase/Google.");

        $this->authService->forgotPassword(
            "fabioedusantos@gmail.com",
            "fake-token",
            "fake-token"
        );
    }
}