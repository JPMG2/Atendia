<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TracksUserActions;
use Database\Factories\SocialNetworkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'url', 'icon', 'abbreviation', 'is_active'])]
class SocialNetwork extends Model
{
    /** @use HasFactory<SocialNetworkFactory> */
    use HasFactory;

    // Un maestro no se borra: lo que lo referencia quedaría colgando.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Los nombres de red son NOMBRES PROPIOS: "TikTok", "LinkedIn", "X (Twitter)".
     * Mismo criterio que Currency::normalizeName y Country::normalizeName: se
     * respeta lo que escribe el usuario, solo se limpian espacios.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * Una URL no lleva espacios: se quitan TODOS, no solo los de las puntas. Un
     * espacio pegado al copiar ("https://x.com/ ") pasaría el `url` de Laravel
     * como falso positivo o rebotaría sin que el usuario vea por qué.
     */
    public static function normalizeUrl(string $value): string
    {
        return (string) preg_replace('/\s+/u', '', $value);
    }

    /**
     * El ícono es la CLAVE de config/icons.php ("x-twitter"), y la columna es
     * nullable: sin ícono elegido se guarda null, no ''.
     */
    public static function normalizeIcon(?string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/u', '', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * La abreviatura se respeta tal cual la escribe el usuario — misma lección que
     * Currency::normalizeSymbol: forzar mayúsculas acá rompería una abreviatura
     * que el usuario quiso escribir de otra forma. Solo se limpian espacios, y la
     * columna es nullable, así que vacío es null.
     */
    public static function normalizeAbbreviation(?string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeName($value),
        );
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeUrl($value),
        );
    }

    protected function icon(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::normalizeIcon($value),
        );
    }

    protected function abbreviation(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::normalizeAbbreviation($value),
        );
    }
}
