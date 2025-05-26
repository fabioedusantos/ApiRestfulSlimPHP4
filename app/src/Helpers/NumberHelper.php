<?php

namespace App\Helpers;

use Random\RandomException;

/**
 * Helper para geração de números aleatórios formatados como string com comprimento fixo.
 *
 * Gera uma string contendo um número aleatório seguro (via `random_int`) preenchida com zeros à esquerda
 * para atingir o número de dígitos especificado.
 */
class NumberHelper
{
    /**
     * Gera uma string contendo um número aleatório com a quantidade de dígitos especificada.
     *
     * A função utiliza `random_int()` para gerar um número aleatório seguro e, em seguida,
     * preenche o resultado com zeros à esquerda (`0`) para atingir o comprimento total
     * definido por `$qtdDigitos`. O número máximo aleatório gerado internamente é 999999.
     *
     * @static
     * @param int $qtdDigitos O número desejado de dígitos para a string resultante.
     * @return string A string contendo o número aleatório preenchido com zeros.
     * @throws RandomException Se uma fonte de entropia adequada não puder ser encontrada (via random_int).
     */
    public static function generateRandomNumber(int $qtdDigitos): string
    {
        return str_pad(
            random_int(0, 999999),
            $qtdDigitos,
            '0',
            STR_PAD_LEFT
        );
    }
}