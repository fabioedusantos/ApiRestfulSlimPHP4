# API RESTful Moderna com SlimPHP 4: Arquitetura e Escalabilidade

## Visão Geral do Projeto

Este projeto representa uma **solução de API RESTful completa e robusta**, desenvolvida em **PHP 8.2** com o **Slim Framework 4**. Seu objetivo é demonstrar a aplicação das melhores práticas de mercado em arquitetura de software, segurança e automação de ambiente.

Ele cobre o ciclo completo de uma aplicação moderna, desde a orquestração do ambiente de desenvolvimento até a implementação de recursos de segurança e escalabilidade.

**Destaques Arquitetônicos e Tecnológicos:**

* **Padrões de Projeto:** Implementação de camadas de **Repository** e **Service** para uma clara separação de responsabilidades, facilitando a manutenção e a testabilidade do código (Clean Architecture).
* **Segurança Robusta:** Autenticação e Autorização via **JWT (JSON Web Tokens)** e proteção contra bots em endpoints críticos utilizando **Google reCAPTCHA Enterprise**.
* **Ambiente Dockerizado:** Ambiente de desenvolvimento isolado e replicável utilizando **Docker Compose** (PHP-Apache, MariaDB) com suporte nativo a **HTTPS**.

---
## Extensões PHP ativas
- `pdo`
- `pdo_mysql`
- `zip`
- `ssl`
- `rewrite`

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
