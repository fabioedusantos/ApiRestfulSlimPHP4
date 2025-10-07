<?php

namespace Tests\E2E;

use App\Handlers\HttpErrorHandler;
use App\Handlers\ShutdownHandler;
use App\Helpers\GoogleRecaptchaHelper;
use App\Helpers\JwtHelper;
use DI\ContainerBuilder;
use Kreait\Firebase\Auth\UserRecord;
use Mockery;
use PDO;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UriFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Tests\Fixtures\DbFixture;
use Tests\Fixtures\MockClientRedis;
use Tests\Fixtures\UserFixture;

abstract class BaseFlow extends TestCase
{
    use DbFixture;

    use UserFixture;

    protected App $app;
    protected PDO $db;
    protected Client $redisClient;
    protected string $resetCode;
    protected array $userData;
    protected UserRecord $firebaseUserData;
    protected string $useSenha = "Senha@123!";

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->getTestDatabase();
        $this->app = $this->getAppInstance();
        $this->userData = $this->getUserData();
        $this->firebaseUserData = $this->getFirebaseUserData();
        $this->checkApiIsOn();
    }

    protected function getAppInstance(): App
    {
        // Criação do container de dependências usando PHP-DI
        $containerBuilder = new ContainerBuilder();

        $dependencies = require __DIR__ . '/../../src/Config/dependencies.php';
        $this->redisClient = $this->createMock(MockClientRedis::class);
        $this->redisClient
            ->method('rpush')
            ->with(
                $this->equalTo("email_queue"),
                $this->callback(function ($values) {
                    $this->assertIsArray($values);
                    $this->assertCount(1, $values);

                    $jsonJob = $values[0];
                    $this->assertJson($jsonJob);

                    $jobData = json_decode($jsonJob, true);

                    $this->assertArrayHasKey('codigo', $jobData);
                    $this->assertNotEmpty($jobData['codigo']);

                    $this->resetCode = $jobData['codigo'];

                    return true;
                })
            )->willReturn(1);
        $dependencies[Client::class] = $this->redisClient;
        $dependencies[PDO::class] = $this->db;

        $containerBuilder->addDefinitions($dependencies);

        // Adicionando as definições de dependências ao container
        $container = $containerBuilder->build();
        AppFactory::setContainer($container);

        // Criação da aplicação
        $app = AppFactory::create();

        // Gerenciamento de erros da aplicação
        $displayErrorDetails = true;
        $callableResolver = $app->getCallableResolver();
        $responseFactory = $app->getResponseFactory();
        $serverRequestCreator = ServerRequestCreatorFactory::create();
        $request = $serverRequestCreator->createServerRequestFromGlobals();
        $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);
        $shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrorDetails);
        register_shutdown_function($shutdownHandler);
        $app->addRoutingMiddleware();
        $errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, false, false);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);

        // Carregar as configurações
        //$config = require __DIR__ . '/config/config.php';
        //$settings = require __DIR__ . '/config/settings.php';
        //$settings($app); // Passar o objeto $app para a função anônima

        //configuração de subpasta para que funcione no servidor
        //não esquecer de ajustar o .htaccess
        $app->setBasePath($_ENV['BASE_PATH']); //subpasta no servidor (vazio se for no root)

        //cors
        $app->add(function ($request, $handler) {
            $response = $handler->handle($request);
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader(
                    'Access-Control-Allow-Headers',
                    'X-Requested-With, Content-Type, Accept, Origin, Authorization'
                )
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Credentials', 'true');
        });

        // Middlewares para tratar requisições OPTIONS separadamente
        $app->options('/{routes:.+}', function ($request, $response, $args) {
            return $response;
        });

        // Registrar rotas
        (require __DIR__ . '/../../src/Routes/routes.php')($app);

        return $app;
    }

    protected function createRequest(
        string $method,
        string $path,
        array $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
        ?array $body = null,
        ?string $token = null
    ): Request {
        $uri = (new UriFactory())->createUri($path);
        $stream = (new StreamFactory())->createStream($body ? json_encode($body) : '');

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        $requestHeaders = new Headers($headers);

        return new Request($method, $uri, $requestHeaders, [], [], $stream);
    }

    protected function recaptchaFake(
        string $token,
        string $siteKey
    ) {
        $recaptchaHelper = Mockery::mock('overload:' . GoogleRecaptchaHelper::class);
        $recaptchaHelper->shouldReceive('isValid')
            ->once()
            ->with(
                $this->equalTo($token),
                $this->equalTo($siteKey)
            )
            ->andReturn(true);
    }


    protected function checkApiIsOn(): void
    {
        $request = $this->createRequest(
            'GET',
            '/'
        );
        $response = $this->app->handle($request);
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        $this->assertArrayHasKey('status', $responseBody);
        $this->assertEquals('success', $responseBody['status']);

        $this->assertArrayHasKey('message', $responseBody);
        $this->assertEquals(
            'API on!',
            $responseBody['message']
        );
    }

    protected function baseCreateAndLogin(): array
    {
        //signup
        $body = [
            'name' => $this->userData['nome'],
            'lastname' => $this->userData['sobrenome'],
            'email' => $this->userData['email'],
            'password' => $this->useSenha,
            'isTerms' => true,
            'isPolicy' => true,
            'recaptchaToken' => 'fake-token',
            'recaptchaSiteKey' => 'fake-site-key'
        ];

        $this->recaptchaFake($body['recaptchaToken'], $body['recaptchaSiteKey']);

        $request = $this->createRequest('POST', '/auth/signup', body: $body);
        $response = $this->app->handle($request);
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertEquals(201, $response->getStatusCode());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        $this->assertArrayHasKey('status', $responseBody);
        $this->assertEquals('success', $responseBody['status']);

        $this->assertArrayHasKey('message', $responseBody);
        $this->assertEquals(
            'Se o e-mail informado estiver correto, você receberá em breve as instruções para confirmar sua conta.',
            $responseBody['message']
        );

        $this->assertArrayHasKey('data', $responseBody);
        $this->assertIsArray($responseBody['data']);

        $this->assertArrayHasKey('expirationInHours', $responseBody['data']);
        $this->assertEquals(2, $responseBody['data']['expirationInHours']);
        $this->assertIsInt($responseBody['data']['expirationInHours']);
        //signup


        //confirmEmail
        $body = [
            'email' => $this->userData['email'],
            'code' => $this->resetCode,
            'recaptchaToken' => 'fake-token',
            'recaptchaSiteKey' => 'fake-site-key'
        ];

        $this->recaptchaFake($body['recaptchaToken'], $body['recaptchaSiteKey']);

        $request = $this->createRequest('POST', '/auth/confirm_email', body: $body);
        $response = $this->app->handle($request);

        $this->assertEquals(204, $response->getStatusCode());
        //confirmEmail

        //login
        return $this->baseLogin($this->useSenha);
        //login
    }

    protected function baseLogin(?string $senha = null)
    {
        if (empty($senha)) {
            $senha = $this->useSenha;
        }

        //login
        $body = [
            'email' => $this->userData['email'],
            'password' => $senha,
            'recaptchaToken' => 'fake-token',
            'recaptchaSiteKey' => 'fake-site-key'
        ];

        $this->recaptchaFake($body['recaptchaToken'], $body['recaptchaSiteKey']);

        $request = $this->createRequest('POST', '/auth/login', body: $body);
        $response = $this->app->handle($request);
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        $this->assertArrayHasKey('status', $responseBody);
        $this->assertEquals('success', $responseBody['status']);

        $this->assertArrayHasKey('message', $responseBody);
        $this->assertEquals(
            'Login realizado com sucesso.',
            $responseBody['message']
        );

        $this->assertArrayHasKey('data', $responseBody);
        $this->assertIsArray($responseBody['data']);

        $this->assertArrayHasKey('token', $responseBody['data']);
        $this->assertIsString($responseBody['data']['token']);
        $this->assertNotEmpty($responseBody['data']['token']);

        $this->assertArrayHasKey('refreshToken', $responseBody['data']);
        $this->assertIsString($responseBody['data']['refreshToken']);

        $this->assertNotEmpty($responseBody['data']['refreshToken']);

        return $responseBody['data'];
        //login
    }

    protected function generateInvalidToken(): string
    {
        return JwtHelper::generateToken("id-usuario-inexistente", $_ENV['JWT_SECRET'] ?? "");
    }

    protected function generateInvalidRefreshToken(): string
    {
        return JwtHelper::generateRefreshToken("id-usuario-inexistente", $_ENV['JWT_REFRESH_SECRET'] ?? "");
    }


    /** Valida o funcionamento da validação do Middleware JwtMiddleware
     * @param ResponseInterface $response
     * @return array
     */
    protected function assertJwtNaoAutorizado(ResponseInterface $response): array
    {
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertEquals(401, $response->getStatusCode());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        $this->assertArrayHasKey('status', $responseBody);
        $this->assertEquals('error', $responseBody['status']);

        $this->assertArrayHasKey('message', $responseBody);
        $this->assertEquals(
            'Jwt não autorizado.',
            $responseBody['message']
        );

        $this->assertArrayNotHasKey('data', $responseBody);

        return $responseBody;
    }

    /** Valida o funcionamento da validação do Middleware UserActiveMiddleware
     * @param ResponseInterface $response
     * @return array
     */
    protected function assertNaoAutorizadoUsuarioInativoOuInexistente(ResponseInterface $response): array
    {
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertEquals(401, $response->getStatusCode());

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        $this->assertArrayHasKey('status', $responseBody);
        $this->assertEquals('error', $responseBody['status']);

        $this->assertArrayHasKey('message', $responseBody);
        $this->assertEquals(
            'Usuário inexistente ou inativo.',
            $responseBody['message']
        );

        $this->assertArrayNotHasKey('data', $responseBody);

        return $responseBody;
    }

    protected function assertSuccess(ResponseInterface $response): array
    {
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody);
        $this->assertNotEmpty($responseBody);

        $this->assertArrayHasKey('status', $responseBody);
        $this->assertEquals('success', $responseBody['status']);

        $this->assertArrayHasKey('message', $responseBody);

        if (isset($responseBody['data'])) {
            $this->assertArrayHasKey('data', $responseBody);
            $this->assertIsArray($responseBody['data']);
        }

        return $responseBody;
    }
}