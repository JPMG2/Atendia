<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\DescribesUserAgent;
use Database\Factories\LoginDeviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A device a user has signed in from. Membership data hanging off the user
 * (like users itself), NOT tenant data: no BelongsToBusiness on purpose —
 * the row is written during login, before any tenant context exists.
 */
class LoginDevice extends Model
{
    use DescribesUserAgent;

    /** @use HasFactory<LoginDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fingerprint',
        'ip',
        'location',
        'user_agent',
        'last_login_at',
        'push_token',
    ];

    /** @var list<string> */
    protected $hidden = ['push_token'];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Points the push address at this install. The token is unique, so a phone
     * that changed hands is released from its previous row in the same step.
     */
    public function registerPushToken(string $token): void
    {
        DB::transaction(function () use ($token): void {
            self::query()->where('push_token', $token)->whereKeyNot($this->id)->update(['push_token' => null]);

            $this->forceFill(['push_token' => $token])->save();
        });
    }

    public function clearPushToken(): void
    {
        $this->forceFill(['push_token' => null])->save();
    }

    /**
     * Installs of this business's team that can be pushed to right now.
     * A token only counts while the install still holds a live session: a
     * password reset or a remote sign-out kills the Sanctum token, and a phone
     * that was signed out must not keep buzzing with the business's news.
     * A closed (soft-deleted) account drops out through the user relation.
     *
     * @return Collection<int, self>
     */
    public static function pushTargetsFor(int $businessId): Collection
    {
        return self::query()
            ->whereNotNull('push_token')
            ->whereHas('user', fn (Builder $user): Builder => $user->where('business_id', $businessId))
            ->whereExists(fn ($token) => $token->from('personal_access_tokens')
                ->whereColumn('personal_access_tokens.name', 'login_devices.fingerprint')
                ->whereColumn('personal_access_tokens.tokenable_id', 'login_devices.user_id')
                ->where('personal_access_tokens.tokenable_type', User::class)
                ->where(fn ($live) => $live->whereNull('personal_access_tokens.expires_at')
                    ->orWhere('personal_access_tokens.expires_at', '>', now()))
                // Sanctum's `expiration` counts from creation and the app never writes expires_at.
                ->when(config('sanctum.expiration'), fn ($age, $minutes) => $age
                    ->where('personal_access_tokens.created_at', '>', now()->subMinutes((int) $minutes))))
            ->with('user')
            ->get();
    }

    /** The stable identity of a device: the browser, not the network it is on. */
    public static function fingerprintFor(?string $userAgent): string
    {
        return hash('sha256', mb_strtolower((string) $userAgent));
    }

    /**
     * A mobile install is told apart by the id the app generated on its first
     * run: its user agent is the same on every phone, so it proves nothing.
     */
    public static function fingerprintForApp(string $deviceId): string
    {
        return hash('sha256', 'app:'.mb_strtolower($deviceId));
    }
}
