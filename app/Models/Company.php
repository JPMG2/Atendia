<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AtendIa: el emisor de la factura. Un ÚNICO registro, para siempre.
 *
 * No confundir con {@see Business}, que es el negocio del cliente (el tenant).
 * Acá viven los datos que encabezan una factura emitida por AtendIa.
 */
#[Fillable([
    'legal_name',
    'tax_id',
    'region_id',
    'tax_condition_id',
    'address',
    'email',
    'phone',
    'web',
    'logo_path',
    'text_copyright',
    'tagline',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * El emisor, o null si todavía no se cargaron los datos de facturación.
     */
    public static function issuer(): ?self
    {
        return self::query()->first();
    }

    /**
     * Región del emisor.
     *
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Condición fiscal del emisor en Argentina.
     *
     * @return BelongsTo<TaxCondition, $this>
     */
    public function taxCondition(): BelongsTo
    {
        return $this->belongsTo(TaxCondition::class);
    }
}
