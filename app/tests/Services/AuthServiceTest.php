<?php

namespace Tests\Services;

use App\Helpers\FirebaseAuthHelper;
use App\Helpers\GoogleRecaptchaHelper;
use App\Helpers\JwtHelper;
use App\Helpers\NumberHelper;
use App\Helpers\Valid;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use DateTime;
use Firebase\JWT\JWT;
use Kreait\Firebase\Auth\UserRecord;
use Mockery;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use stdClass;
use Tests\Fixtures\MockClientRedis;
use Tests\Fixtures\UserFixture;

#[RunTestsInSeparateProcesses] //aplicando para rodar cada teste em um processo separado, necessário para o Mockery overload funcionar corretamente
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
        parent::setUp();
        $this->expirationInHours = 2;
        $this->userData = $this->getUserData();
        $this->firebaseUserData = $this->getFirebaseUserData();

        $this->userRepository = $this->createMock(UserRepository::class);
        $this->redisClient = $this->createMock(MockClientRedis::class);

        $this->authService = new AuthService($this->userRepository, $this->redisClient);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->userData['is_active'] = 1;
        Mockery::close();
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

    public function testSignupFalhaGerarCodigoConfirmacao(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmail')->willReturn(null);

        $numberHelper = Mockery::mock('overload:' . NumberHelper::class);
        $numberHelper->shouldReceive('generateRandomNumber')
            ->andThrow(new \Exception("Erro ao gerar número aleatório."));

        $this->expectExceptionMessage("Erro ao gerar o código de confirmação. Tente novamente.");

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

        $numberHelper = Mockery::mock('overload:' . NumberHelper::class);
        $numberHelper->shouldReceive('generateRandomNumber')
            ->andThrow(new \Exception("Erro ao gerar número aleatório."));

        $this->expectExceptionMessage("Erro ao gerar o código de confirmação. Tente novamente.");

        $this->authService->resendConfirmEmail(
            "fabioedusantos@gmail.com",
            "fake-token",
            "fake-token"
        );
    }

    public function testResendConfirmEmailFalhaSalvarCodigoConfirmacao(): void
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

    public function testResendConfirmEmailFalhaSalvarCodigoConfirmacaoException(): void
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


    // checkResetPassword()
    public function testCheckResetPasswordSucesso(): void
    {
        $email = "fabioedusantos@gmail.com";
        $codigoConfirmacao = "123456";
        $recaptchaToken = "fake-token";
        $recaptchaSiteKey = "fake-token";

        $tempo = $this->expirationInHours * 60 * 60 - 1;

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
            ->with(
                $this->equalTo($email)
            )
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->authService->checkResetPassword(
            $email,
            $codigoConfirmacao,
            $recaptchaToken,
            $recaptchaSiteKey
        );
    }

    public function testCheckResetPasswordFalhaRecaptcha(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(false);

        $this->expectExceptionMessage("Não foi possível validar sua ação. Tente novamente.");

        $tempo = $this->expirationInHours * 60 * 60 - 1;
        $this->userRepository
            ->method('getByEmailWithPasswordReset')
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->authService->checkResetPassword(
            "fabioedusantos@gmail.com",
            "123456",
            "",
            ""
        );
    }

    public function testCheckResetPasswordFalhaUsuarioInexistente(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Código inválido ou expirado. Tente novamente ou recupere sua senha.");

        $this->userRepository
            ->method('getByEmailWithPasswordReset')
            ->willReturn(null);

        $this->authService->checkResetPassword(
            "fabioedusantos@gmail.com",
            "123456",
            "fake-token",
            "fake-token"
        );
    }

    public function testCheckResetPasswordFalhaCodigoIncorreto(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Código inválido ou expirado. Tente novamente ou recupere sua senha.");

        $tempo = $this->expirationInHours * 60 * 60 - 1;
        $this->userRepository
            ->method('getByEmailWithPasswordReset')
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->authService->checkResetPassword(
            "fabioedusantos@gmail.com",
            "654321",
            "fake-token",
            "fake-token"
        );
    }

    public function testCheckResetPasswordFalhaCodigoExpirado(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Código inválido ou expirado. Tente novamente ou recupere sua senha.");

        $this->userRepository
            ->method('getByEmailWithPasswordReset')
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("-1 second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->authService->checkResetPassword(
            "fabioedusantos@gmail.com",
            "123456",
            "fake-token",
            "fake-token"
        );
    }


    // confirmEmail()
    public function testConfirmEmailSucesso(): void
    {
        $email = "fabioedusantos@gmail.com";
        $codigoConfirmacao = "123456";
        $recaptchaToken = "fake-token";
        $recaptchaSiteKey = "fake-token";

        $tempo = $this->expirationInHours * 60 * 60 - 1;

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
            ->with(
                $this->equalTo($email)
            )
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->userRepository->expects($this->once())
            ->method('activate')
            ->with($this->equalTo($this->userData['id']));

        $this->authService->confirmEmail(
            $email,
            $codigoConfirmacao,
            $recaptchaToken,
            $recaptchaSiteKey
        );
    }

    public function testConfirmEmailFalhaRecaptcha(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(false);

        $this->expectExceptionMessage("Não foi possível validar sua ação. Tente novamente.");

        $this->authService->confirmEmail(
            "fabioedusantos@gmail.com",
            "123456",
            "",
            ""
        );
    }

    public function testConfirmEmailFalhaEmail(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Email deve ser válido.");

        $this->authService->confirmEmail(
            "fabão@doemaião",
            "123456",
            "fake-token",
            "fake-token"
        );
    }

    public function testConfirmEmailFalhaCodigoQtdDigitos(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Código inválido ou expirado. Tente novamente ou recupere sua senha.");

        $this->authService->confirmEmail(
            "fabioedusantos@gmail.com",
            "12345",
            "fake-token",
            "fake-token"
        );
    }

    public function testConfirmEmailFalhaCodigoDigitosNumericos(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Código inválido ou expirado. Tente novamente ou recupere sua senha.");

        $this->authService->confirmEmail(
            "fabioedusantos@gmail.com",
            "AbCdEf",
            "fake-token",
            "fake-token"
        );
    }

    public function testConfirmEmailFalhaUsuarioInexistente(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Código inválido ou expirado. Tente novamente ou recupere sua senha.");

        $this->userRepository->method('getByEmailWithPasswordReset')
            ->willReturn(null);

        $this->authService->confirmEmail(
            "fabioedusantos@gmail.com",
            "123456",
            "fake-token",
            "fake-token"
        );
    }

    public function testConfirmEmailFalhaCodigoIncorreto(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Código inválido ou expirado. Tente novamente ou recupere sua senha.");

        $tempo = $this->expirationInHours * 60 * 60 - 1;
        $this->userRepository->method('getByEmailWithPasswordReset')
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->userRepository->method('activate');

        $this->authService->confirmEmail(
            "fabioedusantos@gmail.com",
            "654321",
            "fake-token",
            "fake-token"
        );
    }

    public function testConfirmEmailFalhaCodigoExpirado(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Código inválido ou expirado. Tente novamente ou recupere sua senha.");

        $this->userRepository->method('getByEmailWithPasswordReset')
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("-1 second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->userRepository->method('activate');

        $this->authService->confirmEmail(
            "fabioedusantos@gmail.com",
            "123456",
            "fake-token",
            "fake-token"
        );
    }

    public function testConfirmEmailFalhaAtivar(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Não foi possível ativar o usuário. Tente novamente.");

        $tempo = $this->expirationInHours * 60 * 60 - 1;
        $this->userRepository->method('getByEmailWithPasswordReset')
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->userRepository->method('activate')
            ->willThrowException(new \PDOException("Erro no banco de dados XPTO"));

        $this->authService->confirmEmail(
            "fabioedusantos@gmail.com",
            "123456",
            "fake-token",
            "fake-token"
        );
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

        $this->userData['firebase_uid'] = null;
        $this->userRepository->method('getByEmail')->willReturn($this->userData);

        $numberHelper = Mockery::mock('overload:' . NumberHelper::class);
        $numberHelper->shouldReceive('generateRandomNumber')
            ->andThrow(new \Exception("Erro ao gerar número aleatório."));

        $this->expectExceptionMessage("Erro ao gerar o código de confirmação. Tente novamente.");

        $this->authService->forgotPassword(
            "fabioedusantos@gmail.com",
            "fake-token",
            "fake-token"
        );
    }

    public function testForgotPasswordFalhaSalvarCodigoConfirmacao(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userData['firebase_uid'] = null;
        $this->userRepository->method('getByEmail')->willReturn($this->userData);
        $this->userRepository->method('updateResetCode')->willReturn(false);

        $this->expectExceptionMessage("Não foi possível salvar o código de confirmação. Tente novamente.");

        $this->authService->forgotPassword(
            "fabioedusantos@gmail.com",
            "fake-token",
            "fake-token"
        );
    }

    public function testForgotPasswordFalhaSalvarCodigoConfirmacaoException(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userData['firebase_uid'] = null;
        $this->userRepository->method('getByEmail')->willReturn($this->userData);
        $this->userRepository->method('updateResetCode')->willThrowException(
            new \PDOException("Erro no banco de dados XPTO")
        );

        $this->expectExceptionMessage("Não foi possível salvar o código de confirmação. Tente novamente.");

        $this->authService->forgotPassword(
            "fabioedusantos@gmail.com",
            "fake-token",
            "fake-token"
        );
    }

    public function testForgotPasswordFalhaEnviarEmail(): void
    {
        $email = "fabioedusantos@gmail.com";
        $recaptchaToken = "fake-token";
        $recaptchaSiteKey = "fake-token";

        $this->userData['firebase_uid'] = null;

        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($this->userData);

        $this->userRepository
            ->method('updateResetCode')
            ->willReturn(true);

        $this->redisClient->expects($this->once())
            ->method('rpush')
            ->willThrowException(new \PDOException("Erro ao enviar email no Redis."));

        $this->expectExceptionMessage(
            "Não foi possível enviar o email com o código por uma falha interna. Tente novamente."
        );

        $info = $this->authService->forgotPassword(
            $email,
            $recaptchaToken,
            $recaptchaSiteKey
        );

        $this->assertIsArray($info);

        $this->assertArrayHasKey('expirationInHours', $info);
        $this->assertEquals($this->expirationInHours, $info['expirationInHours']);
    }


    // resetPassword()
    public function testResetPasswordSucesso(): void
    {
        $email = "fabioedusantos@gmail.com";
        $codigoConfirmacao = "123456";
        $senha = "Senha@123!";;
        $recaptchaToken = "fake-token";
        $recaptchaSiteKey = "fake-token";

        $tempo = $this->expirationInHours * 60 * 60 - 1;

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
            ->with(
                $this->equalTo($email)
            )
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->userRepository->expects($this->once())
            ->method('updatePassword')
            ->with(
                $this->equalTo($this->userData['id']),
                $this->callback(function ($hashedPassword) use ($senha) {
                    return password_verify($senha, $hashedPassword);
                })
            );

        $this->authService->resetPassword(
            $email,
            $codigoConfirmacao,
            $senha,
            $recaptchaToken,
            $recaptchaSiteKey
        );
    }

    public function testResetPasswordFalhaRecaptcha(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(false);

        $this->expectExceptionMessage("Não foi possível validar sua ação. Tente novamente.");

        $this->authService->resetPassword(
            "fabioedusantos@gmail.com",
            "123456",
            "Senha@123!",
            "",
            ""
        );
    }

    public function testResetPasswordFalhaEmail(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Email deve ser válido.");

        $this->authService->resetPassword(
            "fabão@doemaião",
            "123456",
            "Senha@123!",
            "fake-token",
            "fake-token"
        );
    }

    public function testResetPasswordFalhaSenha(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage(
            "A senha deve ter no mínimo 8 caracteres, com pelo menos uma letra maiúscula, um número e um caractere especial."
        );

        $this->authService->resetPassword(
            "fabioedusantos@gmail.com",
            "123456",
            "PipoquinhaAçucarada",
            "fake-token",
            "fake-token"
        );
    }

    public function testResetPasswordFalhaUsuarioInexistente(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Código inválido ou expirado. Tente novamente ou recupere sua senha.");

        $this->userRepository->method('getByEmailWithPasswordReset')
            ->willReturn(null);

        $this->authService->resetPassword(
            "fabioedusantos@gmail.com",
            "123456",
            "Senha@123!",
            "fake-token",
            "fake-token"
        );
    }

    public function testResetPasswordFalhaCodigoIncorreto(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Código inválido ou expirado. Tente novamente ou recupere sua senha.");

        $tempo = $this->expirationInHours * 60 * 60 - 1;
        $this->userRepository->method('getByEmailWithPasswordReset')
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->authService->resetPassword(
            "fabioedusantos@gmail.com",
            "654321",
            "Senha@123!",
            "fake-token",
            "fake-token"
        );
    }

    public function testResetPasswordFalhaCodigoExpirado(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Código inválido ou expirado. Tente novamente ou recupere sua senha.");

        $this->userRepository->method('getByEmailWithPasswordReset')
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("-1 second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->authService->resetPassword(
            "fabioedusantos@gmail.com",
            "123456",
            "Senha@123!",
            "fake-token",
            "fake-token"
        );
    }

    public function testResetPasswordFalhaSalvarException(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Não foi possível atualizar a senha. Tente novamente.");

        $tempo = $this->expirationInHours * 60 * 60 - 1;
        $this->userRepository->method('getByEmailWithPasswordReset')
            ->willReturn(
                $this->userData +
                [
                    'reset_code' => password_hash("123456", PASSWORD_BCRYPT),
                    'reset_code_expiry' => (new DateTime("+{$tempo} second"))->format('Y-m-d H:i:s')
                ]
            );

        $this->userRepository->method('updatePassword')->willThrowException(
            new \PDOException("Erro no banco de dados XPTO")
        );

        $this->authService->resetPassword(
            "fabioedusantos@gmail.com",
            "123456",
            "Senha@123!",
            "fake-token",
            "fake-token"
        );
    }


    //login()
    public function testLoginSucesso(): array
    {
        $email = "fabioedusantos@gmail.com";
        $senha = "Senha@123!";
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
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateUltimoAcesso')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn(true);

        $token = $this->authService->login(
            $email,
            $senha,
            $recaptchaToken,
            $recaptchaSiteKey
        );

        // Assert básico: retornou array
        $this->assertIsArray($token);

        $this->assertArrayHasKey('token', $token);
        $this->assertArrayHasKey('refreshToken', $token);

        $this->assertIsString($token['token']);
        $this->assertNotEmpty($token['token']);

        $this->assertIsString($token['refreshToken']);
        $this->assertNotEmpty($token['refreshToken']);

        return $token;
    }

    public function testLoginFalhaRecaptcha(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(false);

        $this->expectExceptionMessage('Não foi possível validar sua ação. Tente novamente.');

        // Simula que recaptcha falha
        $this->authService->login(
            "user@example.com",
            "123456",
            "",
            ""
        );
    }

    public function testLoginFalhaUsuarioNaoEncontrado(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage('Usuário ou senha inválido.');

        $this->authService->login(
            "naoexiste@example.com",
            "123456",
            "fake-token",
            "fake-token"
        );
    }

    public function testLoginFalhaUsuarioInativo(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userData['is_active'] = 0;
        $this->userRepository->method('getByEmail')->willReturn($this->userData);

        $this->expectExceptionMessage(
            'Necessário confirmar seu email. Use a opção de \"Esqueci a senha\" para recuperar a conta.'
        );

        $this->authService->login(
            "fabioedusantos@gmail.com",
            "Senha@123!",
            "fake-token",
            "fake-token",
        );
    }

    public function testLoginFalhaAtualizarUltimoAcesso(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmail')->willReturn($this->userData);

        $this->userRepository->method('updateUltimoAcesso')
            ->willReturn(false);

        $this->expectExceptionMessage("Erro ao atualizar último acesso. Tente novamente.");

        $this->authService->login(
            "fabioedusantos@gmail.com",
            "Senha@123!",
            "fake-token",
            "fake-token",
        );
    }

    public function testLoginFalhaAtualizarUltimoAcessoException(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->userRepository->method('getByEmail')->willReturn($this->userData);

        $this->userRepository->method('updateUltimoAcesso')
            ->willThrowException(new \PDOException("Erro no banco de dados XPTO"));

        $this->expectExceptionMessage("Erro ao atualizar último acesso. Tente novamente.");

        $this->authService->login(
            "fabioedusantos@gmail.com",
            "Senha@123!",
            "fake-token",
            "fake-token",
        );
    }

    public function testLoginFalhaGerarToken(): void
    {
        $email = "fabioedusantos@gmail.com";
        $senha = "Senha@123!";
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
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateUltimoAcesso')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn(true);

        $jwtHelper = Mockery::mock('overload:' . JwtHelper::class);
        $jwtHelper->shouldReceive('generateToken')
            ->andThrow(new \Exception("Erro ao gerar token."));

        $this->expectExceptionMessage("Erro ao gerar token. Tente novamente.");

        $this->authService->login(
            $email,
            $senha,
            $recaptchaToken,
            $recaptchaSiteKey
        );
    }

    public function testLoginFalhaGerarRefreshToken(): void
    {
        $email = "fabioedusantos@gmail.com";
        $senha = "Senha@123!";
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
            ->willReturn($this->userData);

        $this->userRepository->expects($this->once())
            ->method('updateUltimoAcesso')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn(true);

        $jwtHelper = Mockery::mock('overload:' . JwtHelper::class);
        $jwtHelper->shouldReceive('generateToken')
            ->andReturn("fake-jwt-token");
        $jwtHelper->shouldReceive('generateRefreshToken')
            ->andThrow(new \Exception("Erro ao gerar token."));

        $this->expectExceptionMessage("Erro ao gerar token. Tente novamente.");

        $this->authService->login(
            $email,
            $senha,
            $recaptchaToken,
            $recaptchaSiteKey
        );
    }


    // refreshToken()
    public function testRefreshTokenSucesso(): void
    {
        $this->userRepository->expects($this->once())
            ->method('isActive')
            ->with($this->equalTo($this->userData['id']))
            ->willReturn(true);

        $token = $this->testLoginSucesso();
        if (empty($token['refreshToken'])) {
            $this->fail("O refreshToken não foi gerado.");
        }

        $token = $this->authService->refreshToken(
            $token['refreshToken']
        );

        // Assert básico: retornou array
        $this->assertIsArray($token);

        $this->assertArrayHasKey('token', $token);
        $this->assertArrayHasKey('refreshToken', $token);

        $this->assertIsString($token['token']);
        $this->assertNotEmpty($token['token']);

        $this->assertIsString($token['refreshToken']);
        $this->assertNotEmpty($token['refreshToken']);
    }

    public function testRefreshTokenFalhaTokenNaoFornecido(): void
    {
        $this->expectExceptionMessage('Refresh token não fornecido.');

        $this->authService->refreshToken(
            ""
        );
    }

    public function testRefreshTokenFalhaUsuarioNaoAutorizado(): void
    {
        $this->userRepository->method('isActive')->willReturn(false);

        $token = $this->testLoginSucesso();
        if (empty($token['refreshToken'])) {
            $this->fail("O refreshToken não foi gerado.");
        }

        $this->expectExceptionMessage('Usuário não autorizado.');

        $this->authService->refreshToken(
            $token['refreshToken']
        );
    }

    public function testRefreshTokenFalhaGerarToken(): void
    {
        $this->userRepository
            ->method('isActive')
            ->willReturn(true);

        $jwt = Mockery::mock('overload:' . JWT::class);
        $decoded = new stdClass();
        $decoded->sub = new stdClass();
        $decoded->sub->id = $this->userData['id'];
        $jwt->shouldReceive('decode')
            ->andReturn($decoded);

        $jwtHelper = Mockery::mock('overload:' . JwtHelper::class);
        $jwtHelper->shouldReceive('generateToken')
            ->andThrow(new \Exception("Erro ao gerar token."));

        $this->expectExceptionMessage("Erro ao gerar token. Tente novamente.");

        $this->authService->refreshToken("fake-jwt-refresh-token");
    }

    public function testRefreshTokenFalhaTokenInvalido(): void
    {
        $this->userRepository
            ->method('isActive')
            ->willReturn(true);

        $this->expectExceptionMessage("Refresh token inválido ou expirado.");

        $this->authService->refreshToken("fake-jwt-refresh-token-invalido");
    }

    public function testRefreshTokenFalhaGerarRefreshToken(): void
    {
        $this->userRepository
            ->method('isActive')
            ->willReturn(true);

        $jwt = Mockery::mock('overload:' . JWT::class);
        $decoded = new stdClass();
        $decoded->sub = new stdClass();
        $decoded->sub->id = $this->userData['id'];
        $jwt->shouldReceive('decode')
            ->andReturn($decoded);

        $jwtHelper = Mockery::mock('overload:' . JwtHelper::class);
        $jwtHelper->shouldReceive('generateToken')
            ->andReturn("fake-jwt-token");
        $jwtHelper->shouldReceive('generateRefreshToken')
            ->andThrow(new \Exception("Erro ao gerar token."));

        $this->expectExceptionMessage("Erro ao gerar token. Tente novamente.");

        $this->authService->refreshToken("fake-jwt-refresh-token");
    }


    // isLoggedIn()
    public function testIsLoggedInSucesso(): void
    {
        $userId = $this->userData['id'];

        $this->userRepository->expects($this->once())
            ->method('updateUltimoAcesso')
            ->with(
                $this->equalTo($userId)
            )
            ->willReturn(true);

        $this->authService->isLoggedIn(
            $userId
        );
    }

    public function testIsLoggedInFalhaAlterarUltimoAcesso(): void
    {
        $userId = $this->userData['id'];

        $this->expectExceptionMessage("Erro ao atualizar último acesso. Tente novamente.");

        $this->userRepository->method('updateUltimoAcesso')->willReturn(false);

        $this->authService->isLoggedIn(
            $userId
        );
    }

    public function testIsLoggedInFalhaAlterarUltimoAcessoException(): void
    {
        $userId = $this->userData['id'];

        $this->expectExceptionMessage("Erro ao atualizar último acesso. Tente novamente.");

        $this->userRepository->method('updateUltimoAcesso')
            ->willThrowException(new \PDOException("Erro no banco de dados XPTO"));

        $this->authService->isLoggedIn(
            $userId
        );
    }


    // signupGoogle()
    public function testSignupGoogleSucesso(): void
    {
        $firebaseToken = "FaKeFirebaseTokenFaKeFirebas";
        $nome = "Fábio";
        $sobrenome = "Santos";
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

        $firebaseAuthHelper = Mockery::mock('overload:' . FirebaseAuthHelper::class);
        $firebaseAuthHelper->shouldReceive('verificarIdToken')
            ->once()
            ->andReturn($this->firebaseUserData);

        $this->userRepository->expects($this->once())
            ->method('getByEmail')
            ->with($this->equalTo($this->userData['email']))
            ->willReturn(null);

        $this->userRepository->expects($this->once())
            ->method('createByGoogle')
            ->with(
                $this->equalTo($nome),
                $this->equalTo($sobrenome),
                $this->callback(function ($photoBlob) {
                    return Valid::isNullOrString($photoBlob);
                }),
                $this->callback(function ($email) {
                    return Valid::isStringWithContent($email);
                }),
                $this->callback(function ($firebaseUid) {
                    return Valid::isStringWithContent($firebaseUid);
                })
            )
            ->willReturn($this->userData['id']);

        $token = $this->authService->signupGoogle(
            $firebaseToken,
            $nome,
            $sobrenome,
            $isTerms,
            $isPolicy,
            $recaptchaToken,
            $recaptchaSiteKey
        );

        $this->assertIsArray($token);

        $this->assertArrayHasKey('token', $token);
        $this->assertArrayHasKey('refreshToken', $token);

        $this->assertIsString($token['token']);
        $this->assertNotEmpty($token['token']);

        $this->assertIsString($token['refreshToken']);
        $this->assertNotEmpty($token['refreshToken']);
    }

    public function testSignupGoogleFalhaRecaptcha(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(false);

        $this->expectExceptionMessage("Não foi possível validar sua ação. Tente novamente.");

        $this->authService->signupGoogle(
            "FaKeFirebaseTokenFaKeFirebas",
            "Fábio",
            "Santos",
            true,
            true,
            "",
            ""
        );
    }

    public function testSignupGoogleFalhaTokenFirebase(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $this->expectExceptionMessage("Token Firebase não fornecido.");

        $this->authService->signupGoogle(
            "",
            "Fábio",
            "Santos",
            true,
            true,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupGoogleFalhaTokenFirebaseInvalido(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $firebaseAuthHelper = Mockery::mock('overload:' . FirebaseAuthHelper::class);
        $firebaseAuthHelper->shouldReceive('verificarIdToken')
            ->once()
            ->andReturn(null);

        $this->expectExceptionMessage("Token Firebase inválido ou expirado.");

        $this->authService->signupGoogle(
            "FaKeFirebaseTokenFaKeFirebas",
            "Fábio",
            "Santos",
            true,
            true,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupGoogleFalhaNome(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $firebaseAuthHelper = Mockery::mock('overload:' . FirebaseAuthHelper::class);
        $firebaseAuthHelper->shouldReceive('verificarIdToken')
            ->once()
            ->andReturn($this->firebaseUserData);

        $this->expectExceptionMessage("Nome muito curto.");

        $this->authService->signupGoogle(
            "FaKeFirebaseTokenFaKeFirebas",
            "F",
            "Santos",
            true,
            true,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupGoogleFalhaSobrenome(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $firebaseAuthHelper = Mockery::mock('overload:' . FirebaseAuthHelper::class);
        $firebaseAuthHelper->shouldReceive('verificarIdToken')
            ->once()
            ->andReturn($this->firebaseUserData);

        $this->expectExceptionMessage("Sobrenome muito curto.");

        $this->authService->signupGoogle(
            "FaKeFirebaseTokenFaKeFirebas",
            "Fábio",
            "S",
            true,
            true,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupGoogleFalhaEmail(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $firebaseAuthHelper = Mockery::mock('overload:' . FirebaseAuthHelper::class);
        $firebaseAuthHelper->shouldReceive('verificarIdToken')
            ->once()
            ->andReturn($this->firebaseUserData);

        $this->userRepository->method('getByEmail')->willReturn($this->userData);

        $this->expectExceptionMessage("Email já cadastrado.");

        $this->authService->signupGoogle(
            "FaKeFirebaseTokenFaKeFirebas",
            "Fábio",
            "Santos",
            true,
            true,
            "fake-token",
            "fake-token"
        );
    }

    public function testSignupGoogleFalhaTermos(): void
    {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->andReturn(true);

        $firebaseAuthHelper = Mockery::mock('overload:' . FirebaseAuthHelper::class);
        $firebaseAuthHelper->shouldReceive('verificarIdToken')
            ->once()
            ->andReturn($this->firebaseUserData);

        $this->userRepository->method('getByEmail')->willReturn(null);

        $this->expectExceptionMessage("Aceite os termos e condições para se cadastrar.");

        $this->authService->signupGoogle(
            "FaKeFirebaseTokenFaKeFirebas",
            "Fábio",
            "Santos",
            false,
            true,
            "fake-token",
            "fake-token"
        );
    }
}