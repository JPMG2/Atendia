<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\DescribesUserAgent;
use Database\Factories\LoginActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per sign-in, known device or not — the account's own trail of who
 * got in, from where and when. Membership data like login_devices: no
 * business_id on purpose, the row is written before any tenant context.
 */
class LoginActivity extends Model
{
    use DescribesUserAgent;

    /** @use HasFactory<LoginActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ip',
        'location',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
