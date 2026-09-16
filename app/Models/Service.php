<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use App\Traits\TracksUserActions;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A service the business actually offers, named in its own words.
 *
 * `business_id` is deliberately NOT fillable, same boundary as User: a row is
 * created THROUGH its owner (`$business->services()->create()`), so an id
 * arriving from a request can never move a service to another tenant.
 */
#[Fillable(['service_type_id', 'service_category_id', 'name', 'description', 'prep_note', 'price_type', 'price', 'deposit', 'duration_minutes', 'is_active', 'is_featured'])]
class Service extends Model
{
    /**
     * Fresha's price semantics. Config-free on purpose: each value needs its
     * own rendering and the amount only means something for the first two.
     *
     * @var list<string>
     */
    public const array PRICE_TYPES = ['fixed', 'from', 'free', 'talk'];

    use BelongsToBusiness;

    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    use LogsActivity;

    // What stops being offered is deactivated or soft-deleted: the assistant's
    // conversations that mention it have to stay traceable.
    use SoftDeletes;
    use TracksUserActions;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['service_type_id', 'service_category_id', 'name', 'description', 'prep_note', 'price_type', 'price', 'deposit', 'duration_minutes', 'is_active', 'is_featured'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('service');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_type_id' => 'integer',
            'service_category_id' => 'integer',
            'price' => 'decimal:2',
            'deposit' => 'decimal:2',
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * The catalog mould this service borrows its behavior from.
     *
     * @return BelongsTo<ServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * The shelf the client filed it under, if any.
     *
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * Whether the price question is answered: an amount, or a type that
     * needs none. What the completeness meter counts.
     */
    public function hasResolvedPrice(): bool
    {
        return $this->price !== null || in_array($this->price_type, ['free', 'talk'], true);
    }
}
