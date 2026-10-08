<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Account\SendEmailVerificationLink;
use App\Actions\Account\SendPasswordResetLink;
use App\Dto\AdoptionRowDto;
use App\Enums\AdoptionStep;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\PanelNotificationType;
use App\Traits\SearchesText;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'whatsapp', 'is_available'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SearchesText, SoftDeletes;

    /**
     * Mirrors of the column defaults: a user created in this very request
     * never read them back, and strict attributes would throw on the read.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'whatsapp' => null,
        'is_available' => true,
    ];

    /**
     * The business they belong to. NULL for the admin: AtendIa's owner is nobody's
     * tenant, and that null is exactly what tells them apart.
     *
     * `business_id` is kept out of Fillable on purpose — mass assignable, a
     * registration could send another business's id and walk in.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return HasMany<LoginDevice, $this> */
    public function loginDevices(): HasMany
    {
        return $this->hasMany(LoginDevice::class);
    }

    /**
     * The last time this person got in, and from where.
     *
     * A date alone says when; the place is what turns the column into a
     * security signal she can act on.
     *
     * @return HasOne<LoginActivity, $this>
     */
    public function latestLogin(): HasOne
    {
        return $this->hasOne(LoginActivity::class)->latestOfMany();
    }

    /** @return HasMany<LoginActivity, $this> */
    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class);
    }

    /**
     * The account's latest sign-ins for the "recent activity" card, newest
     * first. created_at is the login moment.
     *
     * @return Collection<int, LoginActivity>
     */
    public function recentLoginActivity(int $limit = 10): Collection
    {
        return $this->loginActivities()->latest('id')->limit($limit)->get();
    }

    /**
     * "i***@h***.com" for screens a stranger might see: enough for the owner
     * to recognise the inbox, nothing for anyone else to harvest.
     */
    public function maskedEmail(): string
    {
        [$local, $domain] = explode('@', $this->email, 2);
        $dot = strrpos($domain, '.');

        $maskedDomain = $dot === false
            ? mb_substr($domain, 0, 1).'***'
            : mb_substr($domain, 0, 1).'***'.substr($domain, $dot);

        return mb_substr($local, 0, 1).'***@'.$maskedDomain;
    }

    /**
     * A place is unusual until the account has signed in from it twice: the
     * second visit makes it routine, so the flag heals itself.
     */
    public function locationIsUnusual(?string $location): bool
    {
        if ($location === null) {
            return false;
        }

        return $this->loginActivities()->where('location', $location)->count() < 2;
    }

    /**
     * Landing panel by capability: admin → /admin, everyone else → /dashboard.
     */
    public function panelHome(): string
    {
        return $this->can('access-admin-panel')
            ? route('admin.dashboard', absolute: false)
            : route('dashboard', absolute: false);
    }

    /**
     * The account's devices for the "connected devices" screen, freshest
     * login first.
     *
     * @return Collection<int, LoginDevice>
     */
    public function recentDevices(): Collection
    {
        return $this->loginDevices()->orderByDesc('last_login_at')->get();
    }

    /**
     * "Close every other session": drops all devices but the one in use, and
     * LogoutRevokedDevice does the rest on their next request. Returns how
     * many fell.
     */
    public function revokeOtherDevices(string $currentFingerprint): int
    {
        return $this->loginDevices()->where('fingerprint', '!=', $currentFingerprint)->delete();
    }

    /**
     * True when the account already tracks devices and this browser is not
     * among them. Two doors key on it: the login gate (unknown device →
     * e-mail code) and LogoutRevokedDevice (revoked session dies on its next
     * request). An empty list never triggers — sessions older than the
     * feature survive.
     */
    public function deviceIsUnknown(?string $userAgent): bool
    {
        $fingerprints = $this->loginDevices()->pluck('fingerprint');

        return $fingerprints->isNotEmpty()
            && ! $fingerprints->contains(LoginDevice::fingerprintFor($userAgent));
    }

    /**
     * A closed account its owner can still bring back by signing in: only
     * inside the restore window, after it the admin is the only way back.
     */
    public static function restorableByEmail(string $email): ?self
    {
        return self::onlyTrashed()
            ->where('email', mb_strtolower($email))
            ->where('deleted_at', '>=', now()->subDays((int) config('atendia.account_restore_days')))
            ->first();
    }

    /**
     * The platform's OWN people: whoever may open the admin panel.
     *
     * Not every row of `users`. A business owner is a CLIENT and lives in
     * Negocios; listing them here read as if they were staff, which is a
     * screen telling a lie about who holds the keys. The gate is the area
     * permission, so a new staff role joins the list by being granted it.
     *
     * @param  string  $state  '' | active | unverified | closed
     * @return Collection<int, self>
     */
    public static function directory(string $search = '', string $state = ''): Collection
    {
        return self::withTrashed()
            ->permission('access-admin-panel')
            ->with(['roles:id,name', 'latestLogin'])
            ->when(trim($search) !== '', fn (Builder $query) => $query->whereTextMatches(['name', 'email'], trim($search)))
            ->when($state === 'active', fn (Builder $q) => $q->whereNull('deleted_at')->whereNotNull('email_verified_at'))
            ->when($state === 'unverified', fn (Builder $q) => $q->whereNull('deleted_at')->whereNull('email_verified_at'))
            ->when($state === 'closed', fn (Builder $q) => $q->whereNotNull('deleted_at'))
            ->orderBy('name')
            ->get();
    }

    /**
     * The role names that open the admin panel.
     *
     * The access screen shows these and no other: almost everybody also holds
     * `client`, and printing it there turned the column into noise about a
     * panel this screen is not about.
     *
     * @return list<string>
     */
    public static function panelRoleNames(): array
    {
        return Role::query()
            ->whereHas('permissions', fn (Builder $query) => $query->where('name', 'access-admin-panel'))
            ->pluck('name')
            ->all();
    }

    /** A role's name in her words, falling back to the stored one. */
    public static function roleLabel(string $role): string
    {
        $key = 'admin.users.roles.'.$role;

        return Lang::has($key) ? __($key) : $role;
    }

    /**
     * A staff account still waiting on its address, by id.
     *
     * The state is part of the lookup on purpose: resending a link to an
     * address somebody already verified is a mail that confuses whoever
     * receives it.
     */
    public static function staffAwaitingVerification(int $id): ?self
    {
        return self::query()
            ->permission('access-admin-panel')
            ->whereKey($id)
            ->whereNull('email_verified_at')
            ->first();
    }

    /**
     * What the access screen filters by, with the keys its labels use.
     *
     * @return array<string, string>
     */
    public static function accessStates(): array
    {
        return collect(['active', 'unverified', 'closed', 'admin'])
            ->mapWithKeys(fn (string $state): array => [$state => __('admin.users.states.'.$state)])
            ->all();
    }

    /** Where this account stands, in the one word the screen shows. */
    public string $accessState {
        get => match (true) {
            $this->trashed() => 'closed',
            $this->email_verified_at === null => 'unverified',
            default => 'active',
        };
    }

    /** Closed accounts count: their address stays reserved for the restore. */
    public static function emailIsTaken(string $email, ?int $exceptId = null): bool
    {
        return self::withTrashed()
            ->where('email', mb_strtolower($email))
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    /** Whether a closed account still sits inside its self-service restore window. */
    public function isRestorable(): bool
    {
        return $this->trashed()
            && $this->deleted_at->greaterThanOrEqualTo(now()->subDays((int) config('atendia.account_restore_days')));
    }

    /**
     * Laravel's own senders (Breeze's resend button, "forgot my password")
     * are routed through the house channel: every mail leaves by one door.
     */
    #[\Override]
    public function sendEmailVerificationNotification(): void
    {
        app(SendEmailVerificationLink::class)->handle($this);
    }

    #[\Override]
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        app(SendPasswordResetLink::class)->handle($this, $token);
    }

    /** The profile photo, or null for the initials fallback. */
    public function avatarUrl(): ?string
    {
        $path = $this->rawAttribute('avatar_path');

        return $path === null ? null : Storage::disk('public')->url($path);
    }

    /**
     * Where WhatsApp login codes go: the owner's own number from the business
     * contact card. Null while there is none worth dialing.
     */
    public function secondFactorPhone(): ?string
    {
        $digits = $this->business?->ownerWhatsAppDigits() ?? '';

        return strlen($digits) >= 8 ? $digits : null;
    }

    /** Switched on AND still reachable: a number deleted later falls back to e-mail. */
    public function sendsLoginCodesByWhatsApp(): bool
    {
        return $this->rawAttribute('two_factor_whatsapp_at') !== null && $this->secondFactorPhone() !== null;
    }

    /** Backup codes still unspent, for the card's "N left" line. */
    public function recoveryCodesLeft(): int
    {
        return count($this->rawAttribute('two_factor_recovery_codes') === null ? [] : $this->two_factor_recovery_codes);
    }

    /** "•••• 4455": enough for the owner to recognise the phone. */
    public function maskedSecondFactorPhone(): ?string
    {
        $digits = $this->secondFactorPhone();

        return $digits === null ? null : '•••• '.substr($digits, -4);
    }

    /**
     * Columns born after the model was instantiated (a user created in this
     * very request) are absent, and the strict-attributes guard would throw.
     */
    private function rawAttribute(string $key): mixed
    {
        return $this->getAttributes()[$key] ?? null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'two_factor_whatsapp_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
            'is_available' => 'boolean',
            'bell_muted' => 'array',
        ];
    }

    /**
     * The bell kinds this person chose not to see.
     *
     * @return list<string>
     */
    public function mutedBellTypes(): array
    {
        return array_values($this->bell_muted ?? []);
    }

    /** Flips one kind on or off for this person only: the notice stays for the rest. */
    public function toggleBellType(PanelNotificationType $type, bool $wanted): void
    {
        $muted = collect($this->mutedBellTypes())->reject(fn (string $value): bool => $value === $type->value);

        $this->forceFill([
            'bell_muted' => $wanted ? $muted->values()->all() : $muted->push($type->value)->values()->all(),
        ])->save();
    }

    /** @return BelongsToMany<Department, $this> */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_user_id');
    }

    /** One login, one business: an address with any account (closed ones too) can't take a new seat. */
    public static function emailHasAccount(string $email): bool
    {
        return self::withTrashed()->where('email', mb_strtolower(trim($email)))->exists();
    }

    /** A team member invited by the owner: sees the inbox, never the business setup. */
    public function isAgent(): bool
    {
        return $this->hasRole('agent');
    }

    /** The handoff pings' number as bare digits; empty when unset. */
    public function whatsappDigits(): string
    {
        return (string) preg_replace('/\D/', '', (string) $this->whatsapp);
    }

    /**
     * Every owner account with the step it reached, the ones that stalled
     * first and, inside that, the longest away. Anchored on the ACCOUNT and
     * not the business on purpose: the first drop-off happens before a
     * business row exists, in the middle of the wizard.
     *
     * @return Collection<int, AdoptionRowDto>
     */
    public static function adoptionRows(): Collection
    {
        $owners = self::query()
            ->select('users.*')
            ->role('client')
            ->with('business:id,name,whatsapp_connected_at,created_at')
            ->addSelect(['last_seen_at' => LoginActivity::query()
                ->selectRaw('max(created_at)')
                ->whereColumn('user_id', 'users.id')])
            ->get();

        $businessIds = $owners->pluck('business_id')->filter()->unique()->all();

        $services = self::totalsByBusiness(Service::query(), $businessIds);
        $products = self::totalsByBusiness(Product::query(), $businessIds);
        $conversations = self::totalsByBusiness(Conversation::query(), $businessIds);
        $tickets = self::totalsByBusiness(SupportTicket::query(), $businessIds);
        $answerQuery = fn (): Builder => ConversationMessage::query()
            ->where('direction', MessageDirection::Out)
            ->where('author', MessageAuthor::Assistant);

        $answers = self::totalsByBusiness($answerQuery(), $businessIds);

        // When each rung happened, for the legs of the funnel and the row's
        // own detail. Catalog takes whichever came first, a service or a product.
        $firstCatalog = self::earliestByBusiness(Service::query(), $businessIds);
        foreach (self::earliestByBusiness(Product::query(), $businessIds) as $business => $at) {
            $firstCatalog[$business] = min($at, $firstCatalog[$business] ?? $at);
        }

        $firstConversation = self::earliestByBusiness(Conversation::query(), $businessIds);
        $firstAnswer = self::earliestByBusiness($answerQuery(), $businessIds);

        return $owners
            ->map(function (self $owner) use ($services, $products, $conversations, $tickets, $answers, $firstCatalog, $firstConversation, $firstAnswer): AdoptionRowDto {
                $id = (int) $owner->business_id;
                $lastSeen = $owner->getAttribute('last_seen_at');

                return new AdoptionRowDto(
                    owner: (string) $owner->name,
                    email: (string) $owner->email,
                    business: $owner->business?->name,
                    step: AdoptionStep::reached(
                        hasBusiness: $owner->business !== null,
                        catalogItems: ($services[$id] ?? 0) + ($products[$id] ?? 0),
                        connected: $owner->business?->whatsapp_connected_at !== null,
                        conversations: $conversations[$id] ?? 0,
                        answers: $answers[$id] ?? 0,
                    ),
                    registeredAt: CarbonImmutable::parse($owner->created_at),
                    lastSeenAt: $lastSeen === null ? null : CarbonImmutable::parse($lastSeen),
                    conversations: $conversations[$id] ?? 0,
                    tickets: $tickets[$id] ?? 0,
                    milestones: [
                        AdoptionStep::BusinessCreated->value => self::momentOf($owner->business?->created_at),
                        AdoptionStep::CatalogLoaded->value => self::momentOf($firstCatalog[$id] ?? null),
                        AdoptionStep::WhatsAppConnected->value => self::momentOf($owner->business?->whatsapp_connected_at),
                        AdoptionStep::FirstConversation->value => self::momentOf($firstConversation[$id] ?? null),
                        AdoptionStep::AssistantAnswered->value => self::momentOf($firstAnswer[$id] ?? null),
                    ],
                );
            })
            ->sortBy([
                [fn (AdoptionRowDto $row): int => $row->step->position(), 'asc'],
                [fn (AdoptionRowDto $row): int => $row->daysIdle ?? 0, 'desc'],
            ])
            ->values();
    }

    /**
     * One grouped count per business, so a screen of N rows still costs one
     * query per counter instead of N.
     *
     * @param  Builder<covariant Model>  $query
     * @param  list<int>  $businessIds
     * @return array<int, int>
     */
    private static function totalsByBusiness(Builder $query, array $businessIds): array
    {
        if ($businessIds === []) {
            return [];
        }

        return $query->whereIn('business_id', $businessIds)
            ->selectRaw('business_id, count(*) as total')
            ->groupBy('business_id')
            ->pluck('total', 'business_id')
            ->map(fn (int|string $total): int => (int) $total)
            ->all();
    }

    /**
     * When each business first did this, which is what turns the funnel into
     * durations instead of a pile of counts.
     *
     * @param  Builder<covariant Model>  $query
     * @param  list<int>  $businessIds
     * @return array<int, string>
     */
    private static function earliestByBusiness(Builder $query, array $businessIds): array
    {
        if ($businessIds === []) {
            return [];
        }

        return $query->whereIn('business_id', $businessIds)
            ->selectRaw('business_id, min(created_at) as first_at')
            ->groupBy('business_id')
            ->pluck('first_at', 'business_id')
            ->all();
    }

    /**
     * Everybody who can work a support report: the owner, who passes every
     * gate, and the people whose role carries the support key. A report can only
     * be handed to someone who can open it.
     *
     * @return array<int, string> Name by user id.
     */
    public static function supportTeam(): array
    {
        return self::query()
            ->whereHas('roles', fn (Builder $roles): Builder => $roles
                ->where('name', 'admin')
                ->orWhereHas('permissions', fn (Builder $permissions): Builder => $permissions->where('name', 'support.view')))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function momentOf(CarbonInterface|string|null $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value);
    }
}
