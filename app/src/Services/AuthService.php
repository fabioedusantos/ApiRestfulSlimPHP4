<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerErrorException;
use App\Exceptions\UnauthorizedException;
use App\Helpers\FirebaseAuthHelper;
use App\Helpers\GoogleRecaptchaHelper;
use App\Helpers\JwtHelper;
use App\Helpers\NumberHelper;
use App\Helpers\PhotoHelper;
use App\Helpers\EnvHelper;
use App\Repositories\UserRepository;
use DateTime;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Predis\Client;

class AuthService
{
    private string $jwtSecret;
    private string $jwtRefreshSecret;
    private int $digitosConfirmacaoSenha = 6;
    private int $horasExpirarConfirmacaoSenha = 2;

    public function __construct(
        private UserRepository $userRepository,
        private Client $redisClient
    ) {
        $this->jwtSecret = EnvHelper::getEnv('JWT_SECRET') ?? '';
        $this->jwtRefreshSecret = EnvHelper::getEnv('JWT_REFRESH_SECRET') ?? '';
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
            $this->userRepository->create(
                $nome,
                $sobrenome,
                $email,
                $senha,
                $codigoConfirmacaoHash,
                $validadeCodigoConfirmacao
            );
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

    public function resendConfirmEmail(
        string $email,
        string $recaptchaToken,
        string $recaptchaSiteKey
    ): array {
        if (!GoogleRecaptchaHelper::isValid($recaptchaToken, $recaptchaSiteKey)) {
            throw new UnauthorizedException("Não foi possível validar sua ação. Tente novamente.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException("Email deve ser válido.");
        }

        $this->savePasswordResetCode($email);

        return ['expirationInHours' => $this->horasExpirarConfirmacaoSenha];
    }


    private function savePasswordResetCode(
        string $email,
        bool $isForceRegenerateCode = false
    ): void {
        if ($isForceRegenerateCode) {
            $user = $this->userRepository->getByEmail($email);
            if (empty($user)) {
                throw new BadRequestException(
                    "Não foi possível gerar o código de confirmação. Usuário não encontrado."
                );
            }
        } else {
            $user = $this->userRepository->getByEmailWithPasswordReset($email);
            if (empty($user)) {
                throw new BadRequestException(
                    "Não foi possível gerar o código de confirmação. Usuário não encontrado ou não possui uma redefinição de senha ativa."
                );
            }
        }

        if (!empty($user['firebase_uid'])) {
            throw new BadRequestException("Não é possível redefinir senha de conta Firebase/Google.");
        }

        $codigoConfirmacao = $this->generateRandomConfirmationCode();
        $validadeCodigoConfirmacao = $this->generateExpirationTime();
        $codigoConfirmacaoHash = password_hash($codigoConfirmacao, PASSWORD_BCRYPT);

        try {
            if (!$this->userRepository->updateResetCode(
                $user['id'],
                $codigoConfirmacaoHash,
                $validadeCodigoConfirmacao
            )) {
                throw new \Exception();
            }
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                "Não foi possível salvar o código de confirmação. Tente novamente.",
                0,
                $e
            );
        }

        try {
            $this->sendPasswordResetEmail(
                $user['email'],
                $user['nome'],
                $codigoConfirmacao,
                "{$this->horasExpirarConfirmacaoSenha} horas"
            );
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                "Não foi possível enviar o email com o código por uma falha interna. Tente novamente.", 0, $e
            );
        }
    }

    public function checkResetPassword(
        string $email,
        string $codigoConfirmacao,
        string $recaptchaToken,
        string $recaptchaSiteKey
    ): void {
        if (!GoogleRecaptchaHelper::isValid($recaptchaToken, $recaptchaSiteKey)) {
            throw new UnauthorizedException("Não foi possível validar sua ação. Tente novamente.");
        }

        $user = $this->userRepository->getByEmailWithPasswordReset($email);
        if (
            empty($user)
            || !password_verify($codigoConfirmacao, $user['reset_code'])
            || new DateTime() > new DateTime($user['reset_code_expiry'])
        ) {
            throw new UnauthorizedException("Código inválido ou expirado. Tente novamente ou recupere sua senha.");
        }
    }

    public function confirmEmail(
        string $email,
        string $codigoConfirmacao,
        string $recaptchaToken,
        string $recaptchaSiteKey
    ): void {
        if (!GoogleRecaptchaHelper::isValid($recaptchaToken, $recaptchaSiteKey)) {
            throw new UnauthorizedException("Não foi possível validar sua ação. Tente novamente.");
        }

        // Validação do email: Deve ser um email válido
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException("Email deve ser válido.");
        }

        if (!strlen($codigoConfirmacao) == $this->digitosConfirmacaoSenha || !ctype_digit($codigoConfirmacao)) {
            throw new UnauthorizedException("Código inválido ou expirado. Tente novamente ou recupere sua senha.");
        }

        $user = $this->userRepository->getByEmailWithPasswordReset($email);
        if (
            empty($user)
            || !password_verify($codigoConfirmacao, $user['reset_code'])
            || new DateTime() > new DateTime($user['reset_code_expiry'])
        ) {
            throw new BadRequestException("Código inválido ou expirado. Tente novamente ou recupere sua senha.");
        }

        try {
            $this->userRepository->activate($user['id']);
        } catch (\Exception $e) {
            throw new InternalServerErrorException("Não foi possível ativar o usuário. Tente novamente.", 0, $e);
        }
    }

    public function forgotPassword(
        string $email,
        string $recaptchaToken,
        string $recaptchaSiteKey
    ): array {
        if (!GoogleRecaptchaHelper::isValid($recaptchaToken, $recaptchaSiteKey)) {
            throw new UnauthorizedException("Não foi possível validar sua ação. Tente novamente.");
        }

        // Validação do email: Deve ser um email válido
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException("Email deve ser válido.");
        }

        $this->savePasswordResetCode($email, true);

        return ['expirationInHours' => $this->horasExpirarConfirmacaoSenha];
    }

    public function resetPassword(
        string $email,
        string $codigoConfirmacao,
        string $senha,
        string $recaptchaToken,
        string $recaptchaSiteKey
    ): void {
        if (!GoogleRecaptchaHelper::isValid($recaptchaToken, $recaptchaSiteKey)) {
            throw new UnauthorizedException("Não foi possível validar sua ação. Tente novamente.");
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

        $user = $this->userRepository->getByEmailWithPasswordReset($email);
        if (
            empty($user)
            || !password_verify($codigoConfirmacao, $user['reset_code'])
            || new DateTime() > new DateTime($user['reset_code_expiry'])
        ) {
            throw new BadRequestException("Código inválido ou expirado. Tente novamente ou recupere sua senha.");
        }

        $senha = password_hash($senha, PASSWORD_BCRYPT);
        try {
            $this->userRepository->updatePassword($user['id'], $senha);
        } catch (\Exception $e) {
            throw new InternalServerErrorException("Não foi possível atualizar a senha. Tente novamente.", 0, $e);
        }
    }

    public function login(
        string $email,
        string $senha,
        string $recaptchaToken,
        string $recaptchaSiteKey
    ): array {
        if (!GoogleRecaptchaHelper::isValid($recaptchaToken, $recaptchaSiteKey)) {
            throw new UnauthorizedException("Não foi possível validar sua ação. Tente novamente.");
        }

        $user = $this->userRepository->getByEmail($email);
        if (empty($user) || !password_verify($senha, $user['senha'])) {
            throw new UnauthorizedException('Usuário ou senha inválido.');
        }

        if (!$user['is_active']) {
            throw new UnauthorizedException(
                'Necessário confirmar seu email. Use a opção de \"Esqueci a senha\" para recuperar a conta.'
            );
        }

        try {
            if (!$this->userRepository->updateUltimoAcesso($user['id'])) {
                throw new \Exception();
            }
        } catch (\Exception $e) {
            throw new InternalServerErrorException("Erro ao atualizar último acesso. Tente novamente.", 0, $e);
        }

        return $this->generateToken($user['id']);
    }

    public function refreshToken(string $refreshToken): array
    {
        if (empty($refreshToken)) {
            throw new UnauthorizedException("Refresh token não fornecido.");
        }

        try{
            $decoded = JWT::decode($refreshToken, new Key($this->jwtRefreshSecret, 'HS256'));
            $userId = $decoded?->sub?->id ?? null;
        } catch (\Exception $e) {
            throw new UnauthorizedException("Refresh token inválido ou expirado.", 0, $e);
        }

        //verificar se usuário está ativo e funcional
        if (empty($userId) || !$this->userRepository->isActive($userId)) {
            throw new UnauthorizedException("Usuário não autorizado.");
        }

        return $this->generateToken($userId);
    }

    public function isLoggedIn(string $userId): void
    {
        try {
            if (!$this->userRepository->updateUltimoAcesso($userId)) {
                throw new \Exception();
            }
        } catch (\Exception $e) {
            throw new InternalServerErrorException("Erro ao atualizar último acesso. Tente novamente.", 0, $e);
        }
    }

    public function signupGoogle(
        string $firebaseToken,
        string $nome,
        string $sobrenome,
        bool $isTerms,
        bool $isPolicy,
        string $recaptchaToken,
        string $recaptchaSiteKey
    ): array {
        if (!GoogleRecaptchaHelper::isValid($recaptchaToken, $recaptchaSiteKey)) {
            throw new UnauthorizedException("Não foi possível validar sua ação. Tente novamente.");
        }

        if (!$firebaseToken) {
            throw new UnauthorizedException("Token Firebase não fornecido.");
        }

        try {
            $userFirebase = FirebaseAuthHelper::verificarIdToken($firebaseToken);
            if (empty($userFirebase)) {
                throw new \Exception();
            }
        } catch (\Exception $e) {
            throw new UnauthorizedException(
                "Token Firebase inválido ou expirado.",
                0,
                $e
            );
        }

        if (mb_strlen($nome) < 2) {
            throw new BadRequestException("Nome muito curto.");
        }

        if (mb_strlen($sobrenome) < 2) {
            throw new BadRequestException("Sobrenome muito curto.");
        }

        // Verificar se o email já existe no banco de dados
        $user = $this->userRepository->getByEmail($userFirebase->email);
        if (!empty($user['id'])) {
            throw new BadRequestException("Email já cadastrado.");
        }

        if (!$isTerms) {
            throw new BadRequestException("Aceite os termos e condições para se cadastrar.");
        }

        if (!$isPolicy) {
            throw new BadRequestException("Aceite a política de privacidade para se cadastrar.");
        }

        $userId = null;
        try {
            $photoBlob = null;
            if (!empty($userFirebase->photoUrl)) {
                $photoBlob = PhotoHelper::urlFotoToBlob($userFirebase->photoUrl);
            }

            $userId = $this->userRepository->createByGoogle(
                $nome,
                $sobrenome,
                $photoBlob,
                $userFirebase->email,
                $userFirebase->uid
            );

            if (empty($userId)) {
                throw new \Exception();
            }
        } catch (\Exception $e) {
            throw new InternalServerErrorException("Erro ao criar usuário. Tente novamente.", 0, $e);
        }

        return $this->generateToken($userId);
    }

    public function loginGoogle(
        string $firebaseToken,
        string $recaptchaToken,
        string $recaptchaSiteKey
    ): array {
        if (!GoogleRecaptchaHelper::isValid($recaptchaToken, $recaptchaSiteKey)) {
            throw new UnauthorizedException("Não foi possível validar sua ação. Tente novamente.");
        }

        if (empty($firebaseToken)) {
            throw new UnauthorizedException("Token Firebase não fornecido.");
        }

        $userFirebase = FirebaseAuthHelper::verificarIdToken($firebaseToken);
        if (empty($userFirebase)) {
            throw new UnauthorizedException("Token Firebase inválido ou expirado.");
        }

        $user = $this->userRepository->getByFirebaseUid($userFirebase->uid);
        if (empty($user['id'])) {
            throw new UnauthorizedException("Conta inexistente. Favor criar a conta primeiro.");
        }

        if (!$user['is_active']) {
            throw new UnauthorizedException(
                'Necessário confirmar seu email. Use a opção de \"Esqueci a senha\" para recuperar a conta.'
            );
        }

        if (!empty($userFirebase->photoUrl)) {
            try {
                $photoBlob = PhotoHelper::urlFotoToBlob($userFirebase->photoUrl);
                if (empty($photoBlob) || !$this->userRepository->updatePhotoBlob($user['id'], $photoBlob)) {
                    throw new \Exception();
                }
            } catch (\Exception $e) {
                throw new InternalServerErrorException("Erro ao atualizar foto de perfil. Tente novamente.", 0, $e);
            }
        }

        try {
            if (!$this->userRepository->updateUltimoAcesso($user['id'])) {
                throw new \Exception();
            }
        } catch (\Exception $e) {
            throw new InternalServerErrorException("Erro ao atualizar último acesso. Tente novamente.", 0, $e);
        }

        return $this->generateToken($user['id']);
    }

    private function generateToken(string $userId): array
    {
        try {
            $token = JwtHelper::generateToken($userId, $this->jwtSecret);
            $refreshToken = JwtHelper::generateRefreshToken($userId, $this->jwtRefreshSecret);
            return [
                'token' => $token,
                'refreshToken' => $refreshToken
            ];
        } catch (\Exception $e) {
            throw new InternalServerErrorException("Erro ao gerar token. Tente novamente.", 0, $e);
        }
    }

    private function generateRandomConfirmationCode(): string
    {
        try {
            return NumberHelper::generateRandomNumber($this->digitosConfirmacaoSenha);
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
        $job = [
            "type" => $type,
            "email" => $email,
            "nome" => $nome,
            "codigo" => $codigo,
            "tempoDuracao" => $tempoDuracao
        ];

        $this->redisClient->rpush('email_queue', [json_encode($job)]);
    }
}