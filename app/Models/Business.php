<?php

declare(strict_types=1);

namespace App\Models;

use App\Classes\Main\Plan;
use App\Traits\TracksUserActions;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The business that hires AtendIa: the TENANT.
 *
 * Not to be confused with {@see Company}, which is AtendIa itself — the one
 * issuing the invoice, a single row. Every operational record hangs off here.
 */
#[Fillable(['name', 'country_id', 'province_id', 'timezone', 'billing_email', 'whatsapp_number', 'fallback_whatsapp_number', 'whatsapp_instance', 'whatsapp_connected_at', 'email', 'web', 'logo_path', 'address', 'city', 'has_premises', 'description', 'currency_id', 'reference_currency_id', 'tax_condition_id', 'tax_id', 'is_active'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    use LogsActivity;

    // A business is never deleted, only deactivated: the records hanging off it
    // have to stay traceable.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * Tenant audit: who changed what, and when.
     *
     * The `*_by` columns are the shortcut for showing the author on screen; the
     * FULL trail, old and new values, lives in `activity_log`. Those columns stay
     * out of the log — spatie resolves the causer on its own.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'country_id', 'province_id', 'timezone', 'billing_email', 'whatsapp_number', 'fallback_whatsapp_number', 'whatsapp_instance', 'whatsapp_connected_at', 'email', 'web', 'logo_path', 'address', 'city', 'has_premises', 'description', 'currency_id', 'reference_currency_id', 'tax_condition_id', 'tax_id', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('business');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_premises' => 'boolean',
            'whatsapp_connected_at' => 'datetime',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Every business is born with its referral code and on the reverse
     * trial: full taste of the trial plan, automatic fall to the floor when
     * it expires (resolved by Plan, no downgrade job). One place, every
     * creation path covered; a referred business gets the longer trial.
     */
    protected static function booted(): void
    {
        static::creating(function (self $business): void {
            $business->referral_code ??= self::freshReferralCode();
        });

        static::created(function (self $business): void {
            $business->adoptReferrer();

            $days = $business->referred_by_business_id !== null
                ? (int) config('atendia.referral.invited_trial_days')
                : (int) config('atendia.trial.days');

            $business->subscription()->create([
                'business_id' => $business->id,
                'plan' => config('atendia.trial.plan'),
                'trial_ends_at' => now()->addDays($days),
            ]);
        });
    }

    /** Unmistakable in a WhatsApp message: no lookalike 0/O/1/I characters. */
    private static function freshReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
            $code = strtr($code, ['0' => '2', 'O' => '3', '1' => '4', 'I' => '5', 'L' => '6']);
        } while (self::query()->where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Ties the newborn business to whoever shared the link. The code rides
     * the session (same-visit signup) or the 30-day cookie; outside a web
     * request — seeders, jobs, factories — both read empty and nothing sticks.
     */
    private function adoptReferrer(): void
    {
        $code = session('atendia_ref') ?? request()->cookie('atendia_ref');

        if (! is_string($code) || $code === '') {
            return;
        }

        $referrer = self::query()
            ->where('referral_code', $code)
            ->whereKeyNot($this->id)
            ->first();

        // forceFill: the column stays out of $fillable so no public form can
        // fake an attribution; only this hook writes it.
        if ($referrer !== null) {
            $this->forceFill(['referred_by_business_id' => $referrer->id])->save();
        }
    }

    /** How many businesses signed up through this business's link. */
    public function referredCount(): int
    {
        return self::query()->where('referred_by_business_id', $this->id)->count();
    }

    /** This week's referred signups — the nightly digest brags about them. */
    public function referredThisWeek(): int
    {
        return self::query()
            ->where('referred_by_business_id', $this->id)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();
    }

    /** Open founder seats: honest scarcity for the /gana screen, never negative. */
    public static function founderSeatsLeft(): int
    {
        $taken = self::query()
            ->whereNotNull('referred_by_business_id')
            ->distinct('referred_by_business_id')
            ->count('referred_by_business_id');

        return max(0, (int) config('atendia.referral.founders') - $taken);
    }

    /**
     * Founding partner: among the first N businesses whose link brought a
     * signup, ranked by their earliest referral. Earned forever — the badge
     * never expires once won.
     */
    public function isFoundingPartner(): bool
    {
        $firstReferralAt = self::query()
            ->where('referred_by_business_id', $this->id)
            ->min('created_at');

        if ($firstReferralAt === null) {
            return false;
        }

        $earlierReferrers = self::query()
            ->whereNotNull('referred_by_business_id')
            ->where('created_at', '<', $firstReferralAt)
            ->distinct('referred_by_business_id')
            ->count('referred_by_business_id');

        return $earlierReferrers < (int) config('atendia.referral.founders');
    }

    /** The shareable "Gana con AtendIa" URL. */
    public function referralLink(): string
    {
        return route('referral.landing', ['code' => $this->referral_code]);
    }

    /**
     * The landing's social proof: how many businesses already answer through
     * the assistant. The view hides it below a floor so the early days never
     * read as an empty room.
     */
    public static function servedCount(): int
    {
        return self::query()->count();
    }

    /**
     * Non-destructive hygiene only: outer spaces trimmed, inner runs
     * collapsed. Casing is sacred — it must match the owner's real-world
     * branding exactly (Meta's display-name rule), so it is never re-cased.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => trim((string) preg_replace('/\s+/u', ' ', $value)),
        );
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * The legal currency its prices are published in.
     *
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * The currency shown NEXT to the price as a reference — the Venezuelan
     * "Ref": local currency by law, the dollar is what people actually pay.
     *
     * @return BelongsTo<Currency, $this>
     */
    public function referenceCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'reference_currency_id');
    }

    /**
     * Null is a valid answer: a seamstress with no tax registration gets her
     * invoice as a natural person.
     *
     * @return BelongsTo<TaxCondition, $this>
     */
    public function taxCondition(): BelongsTo
    {
        return $this->belongsTo(TaxCondition::class);
    }

    /**
     * Opening shifts, several rows per day when the business splits its hours.
     *
     * @return HasMany<BusinessHour, $this>
     */
    public function hours(): HasMany
    {
        return $this->hasMany(BusinessHour::class)->orderBy('day_of_week')->orderBy('opens_at');
    }

    /**
     * The activities the business declared, the primary one first.
     *
     * Several on purpose: a bakery that puts out tables adds a coffee-shop
     * activity and starts seeing the room's service types — no special case in
     * the code, and nobody unlocking anything by hand.
     *
     * @return BelongsToMany<BusinessActivity, $this>
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(BusinessActivity::class, 'activity_business')
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps()
            ->orderByDesc('activity_business.is_primary')
            ->orderBy('activity_business.sort_order');
    }

    /**
     * The PRIMARY activity, or null while the business has picked none.
     *
     * It drives the assistant's tone, the trade's knowledge pack and the reports.
     * A method and not a relation: as a property Eloquent would look for a
     * relation and fail. It reuses the loaded collection when there is one, so a
     * grid does not fire a query per business.
     */
    public function primaryActivity(): ?BusinessActivity
    {
        if ($this->relationLoaded('activities')) {
            return $this->activities->firstWhere('pivot.is_primary', true);
        }

        return $this->activities()->wherePivot('is_primary', true)->first();
    }

    /**
     * Leaves the business's activities at exactly this.
     *
     * One primary — a partial unique index guarantees it too — and the secondary
     * ones in the order they arrive. Passing the primary among the secondary ones
     * does not duplicate it: it is ignored.
     *
     * @param  list<int>  $secondaryIds
     */
    public function syncActivities(?int $primaryId, array $secondaryIds = []): void
    {
        $pivot = [];

        if ($primaryId !== null) {
            $pivot[$primaryId] = ['is_primary' => true, 'sort_order' => 0];
        }

        $order = 0;

        foreach ($secondaryIds as $id) {
            if ($id === $primaryId || isset($pivot[$id])) {
                continue;
            }

            $pivot[$id] = ['is_primary' => false, 'sort_order' => ++$order];
        }

        $this->activities()->sync($pivot);
    }

    /**
     * The service types SUGGESTED to this business: the union of what each of its
     * activities suggests.
     *
     * Union and not intersection — the bakery that also serves coffee has to see
     * both. And it stays a suggestion: nothing stops adopting a type that is not
     * here. This is what shows up first, not what is allowed.
     *
     * @return Collection<int, ServiceType>
     */
    public function suggestedServiceTypes(): Collection
    {
        return ServiceType::query()
            ->whereHas(
                'activities',
                fn (Builder $query): Builder => $query->whereIn(
                    'business_activities.id',
                    $this->activities()->select('business_activities.id'),
                ),
            )
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * The services this business actually offers, in its own words — not to
     * be confused with the catalog's service TYPES, which are the moulds.
     *
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * The shelves this business groups its services under.
     *
     * @return HasMany<ServiceCategory, $this>
     */
    public function serviceCategories(): HasMany
    {
        return $this->hasMany(ServiceCategory::class);
    }

    /** @return list<string> service names in creation order, as the wizard shows them */
    public function serviceNames(): array
    {
        return $this->services()->orderBy('id')->pluck('name')->all();
    }

    /**
     * The goods this business sells — the universal core the import maps
     * onto; anything beyond it lives in the product's knowledge.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return list<string> product names in creation order, as the wizard shows them */
    public function productNames(): array
    {
        return $this->products()->orderBy('id')->pluck('name')->all();
    }

    /**
     * @return HasMany<ProductImport, $this>
     */
    public function productImports(): HasMany
    {
        return $this->hasMany(ProductImport::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<KnowledgeDocument, $this>
     */
    public function knowledgeDocuments(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    /**
     * @return HasMany<KnowledgeMiss, $this>
     */
    public function knowledgeMisses(): HasMany
    {
        return $this->hasMany(KnowledgeMiss::class);
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Live sidebar badges for the catalog leaves: the static seeder numbers
     * lied to the owner (audit, 2026-09-20).
     *
     * @return array{my-services: int, my-products: int}
     */
    public function offerCounts(): array
    {
        return [
            'my-services' => $this->services()->count(),
            'my-products' => $this->products()->count(),
        ];
    }

    /**
     * The CURRENT subscription: plan changes append rows, the latest rules.
     *
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /** The effective entitlements, trial and floor already resolved. */
    public function plan(): Plan
    {
        return Plan::for($this);
    }

    /**
     * Active conversations this month — the market's metric and the plan's
     * cap: threads with at least one message, not threads ever created.
     */
    public function conversationsThisMonth(): int
    {
        return ConversationMessage::query()
            ->where('business_id', $this->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->distinct('conversation_id')
            ->count('conversation_id');
    }

    /** Transcribed audio spent this month, in whole seconds. */
    public function audioSecondsThisMonth(): int
    {
        return (int) ConversationMessage::query()
            ->where('business_id', $this->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('audio_seconds');
    }

    /**
     * The networks the account is on, in display order.
     *
     * The relation is polymorphic: one table holds the company's networks and
     * every business's ({@see SocialLink}).
     *
     * @return MorphMany<SocialLink, $this>
     */
    /**
     * The business a bridge instance delivers for. Resolved with no tenant in
     * place on purpose: the webhook worker only holds the instance name, and
     * this lookup is how it finds out WHICH tenant to adopt.
     */
    public static function forWhatsAppInstance(string $instance): ?self
    {
        return static::query()->where('whatsapp_instance', $instance)->first();
    }

    /** True while a linked number is answering; the connection webhook keeps it honest. */
    public function isConnected(): bool
    {
        return $this->whatsapp_connected_at !== null;
    }

    /**
     * What the wizard actually persisted, for its closing recap.
     *
     * @return array{name: string, activity: ?string, services: int, products: int, phones: bool, email: bool}
     */
    public function wizardSummary(): array
    {
        return [
            'name' => $this->name,
            'activity' => $this->primaryActivity()?->name,
            'services' => $this->services()->count(),
            'products' => $this->products()->count(),
            'phones' => filled($this->whatsapp_number) && filled($this->fallback_whatsapp_number),
            'email' => filled($this->email),
        ];
    }

    public function socialLinks(): MorphMany
    {
        return $this->morphMany(SocialLink::class, 'linkable')->orderBy('sort_order');
    }
}
