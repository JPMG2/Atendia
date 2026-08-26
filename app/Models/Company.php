<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * AtendIa: el emisor de la factura. Un ÚNICO registro, para siempre.
 *
 * No confundir con {@see Business}, que es el negocio del cliente (el tenant).
 * Acá viven los datos que encabezan una factura emitida por AtendIa.
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'legal_name',
        'tax_id',
        'region_id',
        'tax_condition_id',
        'address',
        'email',
        'phone',
        'web',
        'logo_path_light',
        'logo_path_dark',
        'text_copyright',
        'tagline',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'region_id' => 'integer',
            'tax_condition_id' => 'integer',
        ];
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

    /**
     * Las redes donde está la cuenta, en el orden en que se muestran.
     *
     * La relación es polimórfica: la misma tabla guarda las redes de la compañía
     * y las de cada negocio (ver {@see SocialLink}).
     *
     * @return MorphMany<SocialLink, $this>
     */
    public function socialLinks(): MorphMany
    {
        return $this->morphMany(SocialLink::class, 'linkable')->orderBy('sort_order');
    }
}
