# API RESTful Moderna com SlimPHP 4: Arquitetura e Escalabilidade

## Visão Geral do Projeto

Este projeto representa uma **solução de API RESTful completa e robusta**, desenvolvida em **PHP 8.4.14** com o **Slim Framework 4**. Seu objetivo é demonstrar a aplicação das melhores práticas de mercado em arquitetura de software, segurança e automação de ambiente.

Ele cobre o ciclo completo de uma aplicação moderna, desde a orquestração do ambiente de desenvolvimento até a implementação de recursos de segurança e escalabilidade.

**Destaques Arquitetônicos e Tecnológicos:**

* **Padrões de Projeto:** Implementação de camadas de **Repository** e **Service** para uma clara separação de responsabilidades, facilitando a manutenção e a testabilidade do código (Clean Architecture).
* **Segurança Robusta:** Autenticação e Autorização via **JWT (JSON Web Tokens)** e proteção contra bots em endpoints críticos utilizando **Google reCAPTCHA Enterprise**.
* **Escalabilidade Assíncrona:** Uso de **Redis** como *broker* de fila para processamento de tarefas assíncronas (ex: envio de e-mails), isolado em um *worker* dedicado.
* **Ambiente Dockerizado:** Ambiente de desenvolvimento isolado e replicável utilizando **Docker Compose** (PHP-Apache, MariaDB, Redis, Worker) com suporte nativo a **HTTPS**.
* **Documentação Profissional:** Geração e exibição interativa da especificação **OpenAPI 3.0** via **Swagger UI** (`https://localhost/docs`).
* **Qualidade de Código:** Cobertura de testes com **PHPUnit 12** (incluindo testes Unitários e E2E - End-to-End).


---
## Segurança em Desenvolvimento: Bypass do reCAPTCHA

Para facilitar o desenvolvimento, os testes automatizados e o uso da documentação interativa via Swagger UI, a verificação do **Google reCAPTCHA Enterprise** é desativada quando o projeto está em modo de desenvolvimento.

O sistema verifica a variável de ambiente `APP_ENV`.

### Condição de Bypass

A verificação do reCAPTCHA é automaticamente ignorada (*bypass*) se a variável no arquivo `store/.env` for definida como:

```dotenv
    APP_ENV="DEV"
```

---
## Estrutura esperada

```
    project/
    ├── app/
    │   └── bin/
    │       └── RunWorkerEmail.php               //Worker Redis de envio de emails
    │   └── public/
    │       └── docs/                            //Swagger-ui
    │       └── .htaccess
    │       └── index.php                        //SlimPHP
    │       └── openapi.yaml                     //openapi.yaml consumido por Swagger-ui da pasta /docs
    │   └── src/
    │       └── Config/
    │           └── dependencies.php
    │           └── ...
    │       └── Controllers/
    │       └── Emails/
    │       └── Exceptions/                      //Exceptions customizadas para controle da API
    │       └── Handlers/                        //Handlers SlimPHP customizados para tratamento de erros
    │       └── Helpers/
    │       └── Middlewares/
    │       └── Repositories/
    │       └── Routes/
    │       └── Services/
    │       └── Models/
    │       └── Workers/
    │           └── EmailWorker.php              //Consumido por app/bin/RunWorkerEmail.php
    │   └── tests/
    │       └── E2E/                             //Testes End-to-End
    │       └── Fixtures/                        //Dados de Suporte
    │       └── Repositories/                    //Testes Unitários
    │       └── Services/                        //Testes Unitários
    │       └── bootstrap.php                    //Configuração Inicial PHPUnit
    │   └── composer.json
    │   └── composer.lock
    │   └── phpunit.xml                          //Arquivo de configuração principal do PHPUnit
    ├── docker/
    │   └── dev
    │       └── apache
    │           └── 000-default.conf             //Roteamento Apache de Requisições (configurado 80 e 443)
    │           └── default-ssl.conf             //Configurações SSL do Apache
    │       └── db
    │           └── init.sql                     //Script SQL que é executado automaticamente quando o contêiner do banco de dados é iniciado
    │       └── docker-build.sh               
    │       └── docker-compose.yml               //Configurador Docker central de todos os serviços (e suas respectivas imagens)
    │       └── docker-restart.sh
    │       └── docker-start.sh
    │       └── docker-stop.sh
    │       └── Dockerfile                       //Configurações de criação da imagem docker PHP
    ├── store/                                   //Arquivos de configuração e credenciais sensíveis
    │   └── .env.example                         //Variáveis de ambiente, deverá virar .env
    │   └── firebase_key.example.json            //Chave de serviço JSON para o Google Firebase, deverá virar firebase_key.json
    │   └── recaptcha_google_key.example.json    //Chave de serviço JSON para o Google Recaptcha, deverá virar recaptcha_google_key.json
    ├── .gitignore
    └── README.md
```

---

## Docker
Este projeto utiliza o **Docker Compose** para orquestrar um ambiente de desenvolvimento local robusto, isolado e consistente. Todas as dependências (PHP, Apache, Banco de Dados, Cache e Workers) são gerenciadas através de contêineres.

## Serviços Docker Principais

O ambiente é composto por **quatro serviços principais** definidos no arquivo `docker-compose.yml`:

| Serviço (`container_name`) | Imagem/Base                                          | Propósito                                                                                                                                            | Dependências |
| :--- |:-----------------------------------------------------|:-----------------------------------------------------------------------------------------------------------------------------------------------------| :--- |
| **`app`** (`php-app`) | Construído a partir do `Dockerfile` (PHP 8.4.14-Apache) | Contêiner da aplicação principal. Executa o servidor **Apache** com **PHP 8.4.14** e expõe as portas HTTP e HTTPS. É onde o código da aplicação reside. | `db` |
| **`db`** (`mariadb`) | `mariadb:12.0.2`                                     | **Banco de Dados MariaDB** para persistência de dados. Inicializa automaticamente o esquema de tabelas e usuários.                                   | Nenhuma |
| **`redis`** (`redis`) | `redis:8.2.2-alpine`                                 | Servidor **Redis** usado como **fila de email**.                                                                                                     | Nenhuma |
| **`email-worker-redis`** (`email-worker-redis`) | Construído a partir do `Dockerfile` (PHP 8.4.14-Apache) | Contêiner dedicado que monitora o Redis e processa o envio de e-mails de forma **assíncrona**.                                                       | `redis` |

## Gerenciamento do Ambiente

Para facilitar o uso diário, utilize os *scripts* de *shell* fornecidos na pasta `docker/dev/`:

| Script | Comando Executado | Descrição |
| :--- | :--- | :--- |
| `./docker-start.sh` | `docker-compose up -d` | **Constrói as imagens (se necessário)** e inicia todos os contêineres em *background*. |
| `./docker-stop.sh` | `docker-compose stop` | **Para** todos os contêineres sem remover seus dados ou volumes. |
| `./docker-restart.sh` | **Para e Inicia** | Simplifica o ciclo de desenvolvimento chamando `./docker-stop.sh` e, em seguida, `./docker-start.sh`. |
| `./docker-build.sh` | `docker-compose build` | Força a **reconstrução** das imagens, útil após modificações no `Dockerfile`. |

---

## Gerenciamento de Dependências (Composer)

A gestão de dependências é baseada nos arquivos **`composer.json`** e **`composer.lock`** localizados em **`/app`**.

### Comandos de Atualização e Força

| Ação | Comando a ser rodado (no terminal do Host) |
| :--- | :--- |
| **Atualizar Dependências** | **`composer update`** |
| **Forçar Reinstalação** | `composer install --prefer-dist` |
| **Limpar Cache** | `composer clear-cache` |

---
## Redis para envio de e-mail
Necessário reiniciar o **email-worker-redis** quando se atualiza o código fonte pois como é um loop, o php não encerra atividade, mesmo que tenhamos a substituição do arquivo automaticamente.

### Escutar o worker de email em tempo real
```bash
  docker logs -f email-worker-redis  
```

---
## Swagger
Este projeto utiliza o **Swagger** para gerar e exibir a documentação interativa da API. A documentação está acessível através da URL principal **/openapi.yaml**. Exemplo: https://localhost/openapi.yaml.

### Acessando a Documentação
A documentação interativa da API está disponível em na url principal em **/docs**. Exemplo: https://localhost/docs.
Este link abrirá a interface do Swagger, onde você poderá explorar as rotas, parâmetros, respostas e outros detalhes da API de forma interativa.

### Gerando o arquivo `openapi.yaml`
Para que o Swagger tenha a documentação atualizada, é necessário gerar o arquivo `openapi.yaml`. Este arquivo contém as especificações da API em formato OpenAPI e é utilizado pelo Swagger para gerar a interface interativa.
```bash
   cd ./app/
   ./vendor/bin/openapi ./src/ -o ./public/openapi.yaml
```
### Estrutura do Projeto
- Pasta `public/docs/`: Contém a documentação do Swagger via Swagger-ui.
- Arquivo `public/openapi.yaml`: O arquivo gerado com as especificações OpenAPI que o Swagger usa para renderizar a documentação.

---
## Testes
Testes na pasta `app/tests` voltados para `PHPUnit 12`.
```
project/
├── app/
│   └── tests/
│       └── E2E/                //end-to-end
│       └── Fixtures/           //apoio
│       └── Repositories/       //unit
│       └── Services/           //unit
│       └── bootstrap.php       //config phpunit inicial
```

### Rodando todos os testes
```bash
   cd ./app/
   .\vendor\bin\phpunit
```

### Rodando testes específicos
```bash
   cd ./app/
   .\vendor\bin\phpunit --filter NomeDoMetodoDeTesteAquiOuClasse
```

### Resultado dos testes
```
PHPUnit 12.4.2 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.14
Configuration: D:\TRABALHO\Projetos\PHP\api_default\app\phpunit.xml

...............................................................  63 / 186 ( 33%)
............................................................... 126 / 186 ( 67%)
............................................................    186 / 186 (100%)

Time: 01:50.754, Memory: 28.00 MB

OK, but there were issues!
Tests: 186, Assertions: 2214, PHPUnit Deprecations: 1.
```

---

## Observações
- Todo o conteúdo da pasta `app/` do projeto local é mapeado para dentro do container em `/var/www/html`.
- Todo o conteúdo da pasta `store/` do projeto local é mapeado para dentro do container em `/var/www/store`.
- A pasta publica do apache (pasta de servidor) é alterada para `/var/www/html/public` para melhor segurança.
- O `.htaccess` é respeitado graças ao `AllowOverride All`
- Um certificado autoassinado é gerado automaticamente na imagem.

---
## Sobre o Autor
Este projeto foi desenvolvido por Fábio Eduardo Santos. Conecte-se:
* **Email:** fabioedusantos@gmail.com
* **Website:** [fbsantos.com.br](https://fbsantos.com.br)
* **LinkedIn:** [Fábio Eduardo Santos](https://www.linkedin.com/in/fabioedusantos/)
