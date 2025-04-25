<?php

namespace Tests\Services;

use App\Helpers\GoogleRecaptchaHelper;
use App\Helpers\Valid;
use App\Repositories\UserRepository;
use App\Services\AuthService;
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
}