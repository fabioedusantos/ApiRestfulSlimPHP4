<?php

namespace App\Services;


use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerErrorException;
use App\Exceptions\UnauthorizedException;
use App\Helpers\GoogleRecaptchaHelper;
use App\Helpers\Util;
use App\Repositories\UserRepository;
use DateTime;

class AuthService
{
    private string $jwtSecret;
    private string $jwtRefreshSecret;
    private int $digitosConfirmacaoSenha = 6;
    private int $horasExpirarConfirmacaoSenha = 2;

    public function __construct(
        private UserRepository $userRepository
    ) {
        $this->jwtSecret = Util::getEnv('JWT_SECRET') ?? '';
        $this->jwtRefreshSecret = Util::getEnv('JWT_REFRESH_SECRET') ?? '';
    }

    public function signup(
        string $nome,
        string $sobrenome,
        string $email,
        string $senha,
        bool $isTerms,
        bool $isPolicy,
        string $recaptchaToken,
        string $recaptchaSiteKey
    ): array {
        if (!GoogleRecaptchaHelper::isValid($recaptchaToken, $recaptchaSiteKey)) {
            throw new UnauthorizedException("Não foi possível validar sua ação. Tente novamente.");
        }

        if (mb_strlen($nome) < 2) {
            throw new BadRequestException("Nome muito curto.");
        }

        if (mb_strlen($sobrenome) < 2) {
            throw new BadRequestException("Sobrenome muito curto.");
        }

        // Validação do email: Deve ser um email válido
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException("Email deve ser válido.");
        }

        if (strlen($senha) < 8 ||
            !preg_match('/[A-Z]/', $senha) ||       // Pelo menos uma letra maiúscula
            !preg_match('/[0-9]/', $senha) ||       // Pelo menos um número
            !preg_match('/[\W]/', $senha)           // Pelo menos um caractere especial
        ) {
            throw new BadRequestException(
                "A senha deve ter no mínimo 8 caracteres, com pelo menos uma letra maiúscula, um número e um caractere especial."
            );
        }

        // Verificar se o email já existe no banco de dados
        $user = $this->userRepository->getByEmail($email);
        if (!empty($user['id'])) {
            throw new BadRequestException("Email já cadastrado.");
        }

        if (!$isTerms) {
            throw new BadRequestException("Aceite os termos e condições para se cadastrar.");
        }

        if (!$isPolicy) {
            throw new BadRequestException("Aceite a política de privacidade para se cadastrar.");
        }

        $senha = password_hash($senha, PASSWORD_BCRYPT);
        $codigoConfirmacao = $this->generateRandomConfirmationCode();
        $validadeCodigoConfirmacao = $this->generateExpirationTime();
        $codigoConfirmacaoHash = password_hash($codigoConfirmacao, PASSWORD_BCRYPT);

        try {
            $this->userRepository->create($nome, $sobrenome, $email, $senha, $codigoConfirmacaoHash, $validadeCodigoConfirmacao);
        } catch (\Exception $e) {
            throw new InternalServerErrorException("Erro ao criar usuário. Tente novamente.", 0, $e);
        }

        try {
            $this->sendAccountConfirmationEmail(
                $email,
                $nome,
                $codigoConfirmacao,
                "{$this->horasExpirarConfirmacaoSenha} horas"
            );
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                "Não foi possível enviar o email com o código por uma falha interna. Tente novamente.", 0, $e
            );
        }

        return ['expirationInHours' => $this->horasExpirarConfirmacaoSenha];
    }

    private function generateRandomConfirmationCode(): string
    {
        try {
            return Util::generateRandomNumber($this->digitosConfirmacaoSenha);
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                "Erro ao gerar o código de confirmação. Tente novamente.",
                0,
                $e
            );
        }
    }

    private function generateExpirationTime(): string
    {
        $expiry = new DateTime("+{$this->horasExpirarConfirmacaoSenha} hour");
        return $expiry->format('Y-m-d H:i:s');
    }

    private function sendAccountConfirmationEmail(
        string $email,
        string $nome,
        string $codigo,
        string $tempoDuracao
    ): void {
        $this->sendEmail(
            "accountConfirmation",
            $email,
            $nome,
            $codigo,
            $tempoDuracao
        );
    }

    private function sendPasswordResetEmail(
        string $email,
        string $nome,
        string $codigo,
        string $tempoDuracao
    ): void {
        $this->sendEmail(
            "passwordReset",
            $email,
            $nome,
            $codigo,
            $tempoDuracao
        );
    }

    private function sendEmail(
        string $type,
        string $email,
        string $nome,
        string $codigo,
        string $tempoDuracao
    ): void {
        //todo implementar um redis aqui
    }
}