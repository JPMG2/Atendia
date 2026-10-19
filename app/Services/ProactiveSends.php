<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\WhatsAppLinkEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

/**
 * The one gate for what a business sends WITHOUT the customer having just
 * written: reminders and greetings. WhatsApp bans numbers that message people
 * who never wrote to them, that keep writing to people who never answer, or
 * that burst, so each of those is checked here and nowhere else. A reply to a
 * customer message never goes through this.
 */
class ProactiveSends
{
    /** Set once something left in this run: the next one waits first. */
    private bool $sentOnce = false;

    public function __construct(private readonly Business $business) {}

    public static function for(Business $business): self
    {
        return new self($business);
    }

    /** The customer wrote: the unanswered streak starts over. */
    public static function heard(Business $business, string $phone): void
    {
        Cache::forget(self::streakKey($business, $phone));
    }

    /**
     * Why this person cannot be written to now, or null when it is fine.
     *
     * @return 'cold'|'ignored'|'cap'|null
     */
    public function refusal(string $phone, ?Customer $customer = null): ?string
    {
        if (! $this->hasWrittenOrConsented($phone, $customer)) {
            return 'cold';
        }

        if ((int) Cache::get(self::streakKey($this->business, $phone), 0) >= (int) config('atendia.outbound.max_unanswered')) {
            return 'ignored';
        }

        return $this->sentToday() >= $this->dailyCap() ? 'cap' : null;
    }

    /**
     * One of several wordings of the same message: identical text to many
     * people is what a spam filter learns first. The seed (a record id) keeps a
     * retry of the SAME message in the same words and spreads the rest.
     *
     * @param  array<string, string>  $replace
     */
    public static function wording(string $key, int $seed, array $replace = []): string
    {
        $variants = array_values((array) __($key));

        if ($variants === []) {
            return $key;
        }

        $line = (string) $variants[$seed % count($variants)];

        foreach ($replace as $name => $value) {
            $line = str_replace(':'.$name, $value, $line);
        }

        return $line;
    }

    /** Waits a random few seconds when something already left in this run. */
    public function pace(): void
    {
        if (! $this->sentOnce) {
            return;
        }

        [$min, $max] = array_map(intval(...), (array) config('atendia.outbound.gap_seconds'));

        if ($max > 0) {
            Sleep::for(random_int(min($min, $max), $max))->seconds();
        }
    }

    /** Counts a send that went out: against today's cap and the person's streak. */
    public function sent(string $phone): void
    {
        $this->sentOnce = true;

        Cache::add($this->dayKey(), 0, now()->addDays(2));
        Cache::increment($this->dayKey());

        Cache::add(self::streakKey($this->business, $phone), 0, now()->addDays(30));
        Cache::increment(self::streakKey($this->business, $phone));
    }

    /** How many it may send today: low while the number is young, then the plain cap. */
    public function dailyCap(): int
    {
        $age = $this->linkedDays();

        foreach ((array) config('atendia.outbound.warmup') as $days => $cap) {
            if ($age < (int) $days) {
                return (int) $cap;
            }
        }

        return (int) config('atendia.outbound.daily_cap');
    }

    private function hasWrittenOrConsented(string $phone, ?Customer $customer): bool
    {
        if ($customer?->marketing_opt_in_at !== null) {
            return true;
        }

        return Conversation::query()
            ->where('contact_phone', $phone)
            ->whereHas('messages', fn ($messages) => $messages->where('direction', MessageDirection::In))
            ->exists();
    }

    private function sentToday(): int
    {
        return (int) Cache::get($this->dayKey(), 0);
    }

    /** Whole days since the number first linked; one that never did counts as brand new. */
    private function linkedDays(): int
    {
        $since = WhatsAppLinkEvent::query()
            ->where('business_id', $this->business->id)
            ->where('connected', true)
            ->min('occurred_at') ?? $this->business->whatsapp_connected_at;

        return $since === null ? 0 : (int) Carbon::parse($since)->diffInDays(now());
    }

    private function dayKey(): string
    {
        return 'wa:out:'.$this->business->id.':'.now($this->business->localTimezone())->toDateString();
    }

    private static function streakKey(Business $business, string $phone): string
    {
        return 'wa:unanswered:'.$business->id.':'.$phone;
    }
}
