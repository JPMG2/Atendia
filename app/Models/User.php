<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

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
        ];
    }
}
