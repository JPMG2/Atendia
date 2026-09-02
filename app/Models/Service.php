<?php

declare(strict_types=1);

namespace App\Models;

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
#[Fillable(['service_type_id', 'name', 'description', 'price', 'duration_minutes', 'is_active'])]
class Service extends Model
{
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
            ->logOnly(['service_type_id', 'name', 'description', 'price', 'duration_minutes', 'is_active'])
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
            'price' => 'decimal:2',
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
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
}
