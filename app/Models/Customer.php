<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageDirection;
use App\Traits\BelongsToBusiness;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * The business's customer record, auto-born from the first WhatsApp message.
 * The AI enriches it with provenance and confidence; a human-written value
 * is never overwritten by the machine.
 */
#[Fillable(['business_id', 'platform_contact_id', 'phone', 'profile_name', 'name', 'email',
    'country_code', 'language', 'birthday', 'notes', 'custom_attributes', 'ai_extracted',
    'marketing_opt_in_at', 'marketing_opt_in_requested_at', 'blocked_at',
    'first_seen_at', 'last_activity_at', 'conversations_count'])]
class Customer extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_attributes' => 'array',
            'ai_extracted' => 'array',
            'birthday' => 'date',
            'marketing_opt_in_at' => 'datetime',
            'marketing_opt_in_requested_at' => 'datetime',
            'blocked_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PlatformContact, $this>
     */
    public function platformContact(): BelongsTo
    {
        return $this->belongsTo(PlatformContact::class);
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** What the screen calls this person: the curated name wins over the pushName. */
    public function displayName(): ?string
    {
        return $this->name ?? $this->profile_name;
    }

    /**
     * Freshen the living fields on every exchange. The pushName is refreshed
     * because people change it; identity data is only filled when missing.
     */
    public function recordExchange(?string $profileName, ?string $language = null): void
    {
        if ($profileName !== null && $profileName !== $this->profile_name) {
            $this->profile_name = $profileName;
        }

        $this->language ??= $language;
        $this->country_code ??= Country::iso2FromPhone($this->phone);
        $this->last_activity_at = now();

        $this->save();
    }

    /**
     * The AI learned something mid-conversation. It always lands in
     * ai_extracted with its provenance; it is promoted to the real column
     * ONLY when that column is empty — a human value is never overwritten.
     */
    public function rememberFact(string $field, string $value, string $confidence = 'high'): void
    {
        $extracted = $this->ai_extracted ?? [];
        $extracted[$field] = [
            'value' => $value,
            'confidence' => $confidence,
            'source' => 'ai',
            'at' => now()->toIso8601String(),
        ];
        $this->ai_extracted = $extracted;

        if (in_array($field, ['name', 'email', 'birthday'], true) && $this->{$field} === null && $confidence === 'high') {
            $this->{$field} = $value;
        }

        $this->save();
    }

    /**
     * True when this text is the customer's FIRST answer after the business
     * asked for marketing consent, and it is a yes. Checked in code, not left
     * to the model: a thread held by the team has the assistant silent, and a
     * consent must never hinge on that (2026-09-23).
     */
    public function answersOptInYes(Conversation $conversation, string $text): bool
    {
        if ($this->marketing_opt_in_requested_at === null || $this->marketing_opt_in_at !== null) {
            return false;
        }

        $answeredAlready = $conversation->messages()
            ->where('direction', MessageDirection::In)
            ->where('created_at', '>=', $this->marketing_opt_in_requested_at)
            ->exists();

        $normalized = Str::lower(Str::ascii(trim($text)));

        return ! $answeredAlready
            && preg_match('/^(si+|dale|ok(ey|a)?|acepto|claro|por supuesto|obvio|de una|bueno|yes|sim|oui)\b/u', $normalized) === 1;
    }

    /** The explicit yes that unlocks campaigns; asking again would nag. */
    public function sealOptIn(): void
    {
        if ($this->marketing_opt_in_at === null) {
            $this->forceFill(['marketing_opt_in_at' => now()])->save();
        }
    }

    /**
     * Another record of THIS business sharing the email: probably the same
     * person on a new number. Suggested, never merged on its own.
     */
    public function duplicateOf(): ?self
    {
        if ($this->email === null) {
            return null;
        }

        return self::query()
            ->whereKeyNot($this->id)
            ->where('email', $this->email)
            ->first();
    }

    /**
     * Absorb a duplicate: its threads move here, its data fills whatever this
     * record is missing, and the duplicate row dies. The platform layer stays
     * untouched — each phone remains its own person up there.
     */
    public function mergeFrom(self $duplicate): void
    {
        $duplicate->conversations()->update(['customer_id' => $this->id]);

        foreach (['name', 'email', 'birthday', 'notes', 'country_code', 'language'] as $field) {
            $this->{$field} ??= $duplicate->{$field};
        }

        $this->ai_extracted = array_merge($duplicate->ai_extracted ?? [], $this->ai_extracted ?? []);
        $this->marketing_opt_in_at ??= $duplicate->marketing_opt_in_at;
        $this->conversations_count = $this->conversations()->count();
        $this->save();

        $duplicate->delete();
    }

    /**
     * What the assistant already knows, so it never asks twice.
     *
     * @return array<string, string>
     */
    public function knownFacts(): array
    {
        return array_filter([
            'nombre' => $this->name,
            'correo' => $this->email,
        ], fn (?string $value): bool => $value !== null);
    }
}
