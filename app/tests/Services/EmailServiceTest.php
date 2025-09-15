<?php

namespace Tests\Services;

use App\Services\EmailService;
use Mockery;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\UserFixture;
use Twig\Environment;

#[RunTestsInSeparateProcesses] //aplicando para rodar cada teste em um processo separado, necessário para o Mockery overload funcionar corretamente
class EmailServiceTest extends TestCase
{
    use UserFixture;

    private PHPMailer $phpMailer;
    private EmailService $emailService;
    private Environment $twig;
    private array $userData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userData = $this->getUserData();

        $this->phpMailer = $this->createMock(PHPMailer::class);

        //regra do email FROM
        $fromEmail = $_ENV["SMTP_FROM_EMAIL"];
        $fromName = $_ENV["SMTP_FROM_NAME"];
        if (empty($fromEmail)) {
            $fromEmail = $_ENV["SMTP_USERNAME"];
        }
        if (empty($fromName)) {
            $fromName = "";
        }
        $this->phpMailer->expects($this->once())
            ->method('setFrom')
            ->with(
                $this->equalTo($fromEmail),
                $this->equalTo($fromName)
            );


        //regra do email REPLY
        $replyEmail = $_ENV["SMTP_REPLY_EMAIL"];
        if (!empty($replyEmail)) {
            $replyName = $_ENV["SMTP_REPLY_NAME"];
            if (empty($replyName)) {
                $replyName = "";
            }
            $this->phpMailer->expects($this->once())
                ->method('addReplyTo')
                ->with(
                    $this->equalTo($replyEmail),
                    $this->equalTo($replyName)
                );
        }

        $this->twig = $this->createMock(Environment::class);
        $this->emailService = new EmailService($this->phpMailer, $this->twig);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }


    //sendAccountConfirmationEmail()
    public function testEnviarConfirmacaoDeContaSucesso(): void
    {
        $email = $this->userData['email'];
        $nome = $this->userData['nome'];
        $codigo = "123456";
        $tempoDuracao = "2 horas";

        $this->phpMailer->expects($this->once())
            ->method('addAddress')
            ->with(
                $this->equalTo($email),
                $this->equalTo($nome)
            );

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                $this->equalTo("AccountConfirmationEmail.twig"),
                $this->equalTo([
                    "titulo" => "Código de Ativação para Sua Conta BUSINESS LOGO",
                    "nome" => $nome,
                    "codigo" => $codigo,
                    "tempoDuracao" => $tempoDuracao
                ])
            )
            ->willReturn("<Corpo HTML da Mensagem Supimpa>");

        $this->phpMailer->expects($this->once())
            ->method('addEmbeddedImage')
            ->with(
                $this->isString(),
                $this->equalTo("logo")
            );

        $this->phpMailer->expects($this->once())
            ->method('send');

        $this->emailService->sendAccountConfirmation(
            $email,
            $nome,
            $codigo,
            $tempoDuracao
        );
    }
}