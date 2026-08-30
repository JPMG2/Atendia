<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Stamps the author of each change ON THE ROW ITSELF.
 *
 * Not a replacement for spatie/activitylog, which keeps the full trail: this
 * is the shortcut that shows "created by X" in a grid without joining the log,
 * and survives its purge. It only writes when a user is logged in — a wiped
 * author is worse than a missing one. Needs the three `*_by` columns.
 */
trait TracksUserActions
{
    public static function bootTracksUserActions(): void
    {
        static::creating(function (Model $model): void {
            $userId = Auth::id();

            if ($userId === null) {
                return;
            }

            // ??=: an action that already named the author wins.
            $model->created_by ??= $userId;
            $model->updated_by ??= $userId;
        });

        static::updating(function (Model $model): void {
            if (Auth::id() !== null) {
                $model->updated_by = Auth::id();
            }
        });

        static::deleting(function (Model $model): void {
            // A forceDelete takes the row with it: stamping the author serves
            // nobody and fires a pointless UPDATE.
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            if (Auth::id() === null) {
                return;
            }

            // saveQuietly: the delete fires its own events; without this the stamp
            // would add an `updating`/`updated` of its own.
            $model->deleted_by = Auth::id();
            $model->saveQuietly();
        });

        static::restored(function (Model $model): void {
            // A restored record has no deleter: leaving the stamp shows the screen
            // someone who deleted a row that is alive.
            $model->deleted_by = null;
            $model->saveQuietly();
        });
    }

    /**
     * Who created the record. Null when a seeder or a background process did, or
     * when that account was deleted afterwards.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
