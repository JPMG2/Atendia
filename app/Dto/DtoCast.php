<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Casts compartidos por los DTO de los maestros.
 *
 * Cada DTO tenía su propia copia privada de estos dos métodos, con el mismo
 * comentario explicando la misma trampa. Acá viven una sola vez.
 */
final class DtoCast
{
    /**
     * Id de una FK que llega del front.
     *
     * El combobox manda el id como STRING ("3"), y los DTO corren con
     * `strict_types`: pasarlo tal cual a un parámetro `?int` es un TypeError que
     * mata el componente (419, editor en blanco). El "sin elegir" llega como '',
     * y eso es null, no 0 — un 0 pasaría por `exists` como id inexistente.
     */
    public static function toNullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Texto de una columna nullable.
     *
     * Un input vacío llega como '' y la columna admite null: se guarda null, no
     * una cadena vacía. Si no, media tabla queda con valores "presentes pero
     * vacíos" que `whereNull` no encuentra nunca.
     */
    public static function toNullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
