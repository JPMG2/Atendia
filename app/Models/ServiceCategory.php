<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use App\Traits\TracksUserActions;
use Database\Factories\ServiceCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A shelf the client groups its services under ({@see Service}). It maps to
 * a WhatsApp catalog collection later, so the drag order is data.
 *
 * `business_id` is deliberately NOT fillable, same boundary as Service: a
 * shelf is created THROUGH its owner, never moved by a request.
 */
#[Fillable(['name', 'sort_order'])]
class ServiceCategory extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ServiceCategoryFactory> */
    use HasFactory;

    use LogsActivity;

    // A dropped shelf keeps its trail: restoring the name brings it back.
    use SoftDeletes;
    use TracksUserActions;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('service_category');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
