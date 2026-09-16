<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\DescribesUserAgent;
use Database\Factories\LoginDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

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

    /** The stable identity of a device: the browser, not the network it is on. */
    public static function fingerprintFor(?string $userAgent): string
    {
        return hash('sha256', mb_strtolower((string) $userAgent));
    }
}
