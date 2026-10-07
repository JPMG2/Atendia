<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlatformContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
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
    /**
     * The radar's board: who reaches the platform, the ones who talk to more
     * than one business first. That overlap is the only thing this table
     * knows that no business can see from its own panel.
     *
     * @return Collection<int, self>
     */
    public static function radar(int $limit = 100): Collection
    {
        return static::query()
            ->orderByDesc('businesses_count')
            ->orderByDesc('last_activity_at')
            ->limit($limit)
            ->get();
    }

    /**
     * What the board adds up to. `shared` is the headline: a person writing to
     * two businesses is proof the platform is a network and not a list.
     *
     * @return array{people: int, shared: int, conversations: int, countries: int}
     */
    public static function reach(): array
    {
        return [
            'people' => static::query()->count(),
            'shared' => static::query()->where('businesses_count', '>', 1)->count(),
            'conversations' => (int) static::query()->sum('conversations_count'),
            'countries' => static::query()->whereNotNull('country_code')->distinct()->count('country_code'),
        ];
    }

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
