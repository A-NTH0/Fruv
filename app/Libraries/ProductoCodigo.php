<?php

namespace App\Libraries;

use InvalidArgumentException;

class ProductoCodigo
{
    public static function generarCodigo(string $nombre, int $idProducto, array $basesOcupadas = []): string
    {
        if ($idProducto < 1) {
            throw new InvalidArgumentException('El número de registro debe ser mayor que cero.');
        }

        $numero = str_pad((string) $idProducto, 3, '0', STR_PAD_LEFT);
        $base   = self::generarBase($nombre, $basesOcupadas);
        $base   = substr($base, 0, 50 - strlen($numero) - 1);

        return $base . '-' . $numero;
    }

    public static function generarBase(string $nombre, array $basesOcupadas = []): string
    {
        $nombreNormalizado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        if ($nombreNormalizado === false) {
            throw new InvalidArgumentException('No se pudo normalizar el nombre del producto.');
        }

        $nombreNormalizado = strtoupper((string) preg_replace('/[^A-Z]/i', '', $nombreNormalizado));
        if ($nombreNormalizado === '') {
            throw new InvalidArgumentException('El nombre del producto debe contener letras.');
        }

        $consonantes = (string) preg_replace('/[AEIOU]/', '', $nombreNormalizado);
        $candidatos  = [];

        if (strlen($consonantes) >= 3) {
            for ($longitud = 3; $longitud <= strlen($consonantes); $longitud++) {
                $candidatos[] = substr($consonantes, 0, $longitud);
            }
        } else {
            $longitudInicial = min(3, strlen($nombreNormalizado));
            $candidatos[]    = substr($nombreNormalizado, 0, $longitudInicial);

            for ($longitud = $longitudInicial + 1; $longitud <= strlen($nombreNormalizado); $longitud++) {
                $candidatos[] = substr($nombreNormalizado, 0, $longitud);
            }
        }

        if (strlen($consonantes) >= 3) {
            for ($longitud = 4; $longitud <= strlen($nombreNormalizado); $longitud++) {
                $candidatos[] = substr($nombreNormalizado, 0, $longitud);
            }
        }

        $basesOcupadas = array_map('strtoupper', $basesOcupadas);
        foreach (array_unique($candidatos) as $candidato) {
            if (! in_array($candidato, $basesOcupadas, true)) {
                return $candidato;
            }
        }

        return end($candidatos);
    }
}
