<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TaxConditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['country_id', 'name', 'code', 'discriminate_tax', 'is_active'])]
class TaxCondition extends Model
{
    /** @use HasFactory<TaxConditionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'discriminate_tax' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
