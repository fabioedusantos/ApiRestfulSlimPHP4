<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Helpers\Util;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class EmailService
{
    private const DIR_EMAIL_TEMPLATES = __DIR__ . '/../Emails';
    private const DIR_EMAIL_ANEXOS = self::DIR_EMAIL_TEMPLATES . '/Anexos';

    public function __construct(
        private PHPMailer $phpMailer,
        private Environment $twig = new Environment(new FilesystemLoader(self::DIR_EMAIL_TEMPLATES))
    ) {
        $this->phpMailer->isSMTP();
        $this->phpMailer->Host = Util::getEnv("SMTP_HOST");           // Servidor SMTP da Microsoft 365
        $this->phpMailer->SMTPAuth = true;                                  // Autenticação habilitada
        $this->phpMailer->Username = Util::getEnv("SMTP_USERNAME");   // Seu e-mail do Microsoft 365
        $this->phpMailer->Password = Util::getEnv("SMTP_PASSWORD");   // Sua senha de e-mail de app
        $this->phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;     // TLS para criptografia
        $this->phpMailer->Port = 587;                                      // Porta TCP para TLS
        //Enable SMTP debugging
        //SMTP::DEBUG_OFF = off (for production use)
        //SMTP::DEBUG_CLIENT = client messages
        //SMTP::DEBUG_SERVER = client and server messages
        $this->phpMailer->SMTPDebug = SMTP::DEBUG_OFF;

        $this->phpMailer->isHTML();
        $this->phpMailer->CharSet = 'UTF-8';

        $fromEmail = Util::getEnv("SMTP_FROM_EMAIL");
        $fromName = Util::getEnv("SMTP_FROM_NAME");
        if (empty($fromEmail)) {
            $fromEmail = Util::getEnv("SMTP_USERNAME");
        }
        if (empty($fromName)) {
            $fromName = "";
        }
        $this->phpMailer->setFrom(
            $fromEmail,
            $fromName
        );

        //email de resposta ao remetente
        $replyEmail = Util::getEnv("SMTP_REPLY_EMAIL");
        if (!empty($replyEmail)) {
            $replyName = Util::getEnv("SMTP_REPLY_NAME");
            if (empty($replyName)) {
                $replyName = "";
            }
            $this->phpMailer->addReplyTo(
                $replyEmail,
                $replyName
            );
        }
    }

    public function sendAccountConfirmation(
        string $email,
        string $nome,
        string $codigo,
        string $tempoDuracao
    ): void {
        try {
            $this->phpMailer->addAddress($email, $nome); //destinatário

            $titulo = "Código de Ativação para Sua Conta BUSINESS LOGO";
            $this->phpMailer->Subject = $titulo;

            //montamos o body com twig
            $data = [
                "titulo" => $titulo,
                "nome" => $nome,
                "codigo" => $codigo,
                "tempoDuracao" => $tempoDuracao
            ];
            $this->phpMailer->Body = $this->twig->render("AccountConfirmationEmail.twig", $data);
            $this->phpMailer->addEmbeddedImage(self::DIR_EMAIL_ANEXOS . "/logo-150px.png", "logo"); //imagem em anexo
            //montamos o body com twig

            $this->phpMailer->send();
        } catch (\Exception $e) {
            throw new BadRequestException("Erro ao enviar email.\n" . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    public function sendPasswordReset(
        string $email,
        string $nome,
        string $codigo,
        string $tempoDuracao
    ): void {
        try {
            $this->phpMailer->addAddress($email, $nome); //destinatário

            $titulo = "Código de Redefinição de Senha para Sua Conta BUSINESS LOGO";
            $this->phpMailer->Subject = $titulo;

            //montamos o body com twig
            $data = [
                "titulo" => $titulo,
                "nome" => $nome,
                "codigo" => $codigo,
                "tempoDuracao" => $tempoDuracao
            ];
            $this->phpMailer->Body = $this->twig->render("PasswordResetEmail.twig", $data);
            $this->phpMailer->addEmbeddedImage(self::DIR_EMAIL_ANEXOS . "/logo-150px.png", "logo"); //imagem em anexo
            //montamos o body com twig

            $this->phpMailer->send();
        } catch (\Exception $e) {
            throw new BadRequestException("Erro ao enviar email.\n" . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}