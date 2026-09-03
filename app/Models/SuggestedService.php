<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TracksUserActions;
use Database\Factories\SuggestedServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A concrete service the catalog SUGGESTS to one activity, GBP-style:
 * "Corte de caballero" to the barber, "Menú del día" to the restaurant.
 * Not to be confused with {@see Service} (what a tenant actually offers):
 * adopting a suggestion creates a Service already carrying its type.
 */
#[Fillable(['business_activity_id', 'service_type_id', 'name', 'sort_order', 'is_active'])]
class SuggestedService extends Model
{
    /** @use HasFactory<SuggestedServiceFactory> */
    use HasFactory;

    use LogsActivity;

    // A master row is never deleted: whatever references it would dangle.
    use SoftDeletes;
    use TracksUserActions;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['business_activity_id', 'service_type_id', 'name', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('suggested_service');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_activity_id' => 'integer',
            'service_type_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BusinessActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(BusinessActivity::class, 'business_activity_id');
    }

    /**
     * @return BelongsTo<ServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }
}
