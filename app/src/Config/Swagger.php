<?php

namespace App\Config;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'API Default',
    description: <<<'DESC'
Documentação da API Default.
<br><br>
**NOTA ESSENCIAL (Modo DEV):** A funcionalidade de **bypass** do **Google reCAPTCHA** presente neste projeto, só será 
ativada se a variável de ambiente no arquivo `store/.env` estiver configurada como `APP_ENV=\"DEV\"`. Se essa 
especificação não for atendida, todos os *endpoints* protegidos (que esperam recaptchaToken e recaptchaSiteKey no body) 
rejeitarão as requisições sem um **token** Google reCAPTCHA válido.
<br><br>
**REQUISITO TÉCNICO (Firebase):** As rotas de `/google` deste projeto exigem um **`idTokenFirebase`** (JWT) válido, que 
só pode ser obtido através da autenticação bem-sucedida do usuário no **front-end** via SDK do Google Firebase.
<br><br><br>
**Sobre o Desenvolvedor:**
<br>
* **Email:** fabioedusantos@gmail.com
<br>
* **Website:** [fbsantos.com.br](https://fbsantos.com.br)
<br>
* **LinkedIn:** [fabioedusantos](https://www.linkedin.com/in/fabioedusantos/)
DESC
)]
#[OA\Server(
    url: 'https://localhost',
    description: 'Servidor local'
)]
#[OA\SecurityScheme(
    securityScheme: "BearerAuth",  // Nome do esquema de segurança
    type: "http",                  // Tipo de segurança HTTP
    scheme: "bearer",              // Esquema Bearer
    bearerFormat: "JWT",           // Indica que o formato é JWT (opcional)
    description: "Bearer JWT Token para autenticação"
)]
#[OA\Tag(name: 'Status', description: 'Endpoints de status')]
#[OA\Tag(name: 'Auth', description: 'Endpoints de autenticação')]
#[OA\Tag(name: 'Users', description: 'Endpoints de usuário')]
#[OA\Tag(name: 'Notifications', description: 'Endpoints de notificações')]
abstract class Swagger
{}