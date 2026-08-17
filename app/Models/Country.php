<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['currency_id', 'name', 'code', 'phone_code', 'is_active'])]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Moneda del país (FK obligatoria en la tabla).
     *
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public static function normalizeCode(string $value): string
    {
        return mb_strtoupper($value);
    }

    /**
     * Los nombres de país son NOMBRES PROPIOS: "República Dominicana", "El
     * Salvador", "Costa Rica". Se respeta lo que escribe el usuario (mismo
     * criterio que Currency::normalizeName); solo se limpian espacios.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * La columna es nullable: un campo vacío se guarda como null, no como ''.
     * Si no, la mitad de los países quedarían con un código telefónico "vacío
     * pero presente" y `whereNull` no los encontraría nunca.
     */
    public static function normalizePhoneCode(?string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/u', '', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeName($value),
        );
    }

    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeCode($value),
        );
    }

    protected function phoneCode(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::normalizePhoneCode($value),
        );
    }
}
