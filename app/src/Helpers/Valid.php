<?php

namespace App\Helpers;

use DateTime;

/**
 * Classe de validações auxiliares.
 *
 * A classe `Valid` fornece métodos para validar diversos tipos de dados, como verificar
 * se uma string tem conteúdo, se uma string é uma data válida e se um valor é nulo ou uma string.
 */
class Valid
{
    /**
     * Verifica se um valor é uma string não vazia.
     *
     * Este método verifica se o valor fornecido é uma string e se ela contém algum conteúdo.
     *
     * @param mixed $value O valor a ser verificado.
     *
     * @return bool `true` se o valor for uma string com conteúdo, `false` caso contrário.
     */
    public static function isStringWithContent(mixed $value): bool
    {
        return (is_string($value) && strlen($value) > 0);
    }

    /**
     * Verifica se o valor é uma string com formato de data e hora válidos.
     *
     * Este método valida se o valor é uma string que representa uma data e hora válidas no formato 'Y-m-d H:i:s'.
     *
     * @param mixed $value O valor a ser verificado.
     *
     * @return bool `true` se o valor for uma string de data e hora válida, `false` caso contrário.
     */
    public static function isStringDateTime(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return $date && $date->format('Y-m-d H:i:s') === $value;
    }

    /**
     * Verifica se o valor é nulo ou uma string.
     *
     * Este método valida se o valor fornecido é nulo ou uma string.
     *
     * @param mixed $value O valor a ser verificado.
     *
     * @return bool `true` se o valor for nulo ou uma string, `false` caso contrário.
     */
    public static function isNullOrString(mixed $value): bool
    {
        return is_null($value) || is_string($value);
    }

    /**
     * Verifica se uma string tem um formato válido de data e hora.
     *
     * Este método valida se o valor fornecido é uma data e hora no formato 'Y-m-d H:i:s'.
     *
     * @param string $date O valor a ser verificado.
     *
     * @return bool `true` se o valor for uma data e hora válidas, `false` caso contrário.
     */
    public static function isValidDateTime(string $date): bool
    {
        return $date !== null && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date);
    }
}