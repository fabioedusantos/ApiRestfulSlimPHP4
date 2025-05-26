<?php

namespace App\Helpers;

/**
 * Helper para leitura e escrita de variáveis de ambiente em tempo de execução.
 *
 * Fornece acesso centralizado a \$_ENV para obter e definir valores.
 */
class EnvHelper
{
    /**
     * Obtém o valor de uma variável de ambiente.
     *
     * Este método retorna o valor de uma variável de ambiente configurada, ou `null` se a variável não estiver definida.
     *
     * @param string $alias O nome da variável de ambiente a ser recuperada.
     *
     * @return mixed O valor da variável de ambiente ou `null` se não estiver definida.
     */
    public static function getEnv(string $alias): mixed
    {
        return isset($_ENV[$alias]) ? $_ENV[$alias] : null;
    }

    /**
     * Define o valor de uma variável de ambiente.
     *
     * Este método permite configurar o valor de uma variável de ambiente.
     *
     * @param string $alias O nome da variável de ambiente a ser definida.
     * @param mixed $value O valor a ser atribuído à variável de ambiente.
     */
    public static function setEnv(
        string $alias,
        mixed $value
    ): void {
        $_ENV[$alias] = $value;
    }
}