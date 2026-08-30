<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SocialLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Somebody's account on a social network.
 *
 * The owner is polymorphic (`linkable`): the company and the businesses. The
 * network itself is the `SocialNetwork` master; the link lives here.
 */
#[Fillable(['social_network_id', 'url', 'sort_order'])]
class SocialLink extends Model
{
    /** @use HasFactory<SocialLinkFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * Link audit: who added, fixed or removed it, and when.
     *
     * The trail matters more here than in a master: the delete is IMMEDIATE and
     * there is no bin. Without the log, a link that disappears leaves no way of
     * knowing who took it out.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['social_network_id', 'url', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('social');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Who owns the account: a `Company` or a `Business`.
     *
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<SocialNetwork, $this>
     */
    public function socialNetwork(): BelongsTo
    {
        return $this->belongsTo(SocialNetwork::class);
    }

    /**
     * A link carries no spaces: ALL of them go, not only the outer ones. Same
     * criterion as `SocialNetwork::normalizeUrl` — one pasted along with the URL
     * bounces without the person seeing why.
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => SocialNetwork::normalizeUrl($value),
        );
    }
}
