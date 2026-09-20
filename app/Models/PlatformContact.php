<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlatformContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AtendIa's own layer: one row per PERSON across every business. Counters
 * move by increments only — reading across tenants from a request would
 * fight the isolation scope, and this table is admin territory anyway.
 */
#[Fillable(['phone', 'name', 'language', 'country_code', 'first_seen_at', 'last_activity_at'])]
class PlatformContact extends Model
{
    /** @use HasFactory<PlatformContactFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** The cross-business pulse, fed one exchange at a time. */
    public function recordExchange(bool $newBusiness, bool $newConversation, ?string $language, ?string $countryCode): void
    {
        if ($newBusiness) {
            $this->businesses_count++;
        }

        if ($newConversation) {
            $this->conversations_count++;
        }

        $this->language ??= $language;
        $this->country_code ??= $countryCode;
        $this->last_activity_at = now();

        $this->save();
    }
}
