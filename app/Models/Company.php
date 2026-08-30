<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * AtendIa itself, the one issuing the invoice. A SINGLE row, forever.
 *
 * Not to be confused with {@see Business}, the customer's business — the
 * tenant. What heads an invoice issued by AtendIa lives here.
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
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * The issuer's tax standing in Argentina.
     *
     * @return BelongsTo<TaxCondition, $this>
     */
    public function taxCondition(): BelongsTo
    {
        return $this->belongsTo(TaxCondition::class);
    }

    /**
     * The networks the account is on, in display order.
     *
     * The relation is polymorphic: one table holds the company's networks and
     * every business's ({@see SocialLink}).
     *
     * @return MorphMany<SocialLink, $this>
     */
    public function socialLinks(): MorphMany
    {
        return $this->morphMany(SocialLink::class, 'linkable')->orderBy('sort_order');
    }
}
