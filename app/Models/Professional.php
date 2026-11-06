<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use App\Traits\TracksUserActions;
use Database\Factories\ProfessionalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Whoever attends the bookings of a business: a doctor, a stylist, a
 * manicurist. Not a team member — it has no login and takes no plan seat.
 *
 * `business_id` stays out of Fillable on purpose: it is the tenant boundary
 * and is only ever set through the relation, like {@see Service}.
 */
#[Fillable(['user_id', 'name', 'is_active', 'sort_order', 'appointment_capacity', 'appointments_per_day'])]
class Professional extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ProfessionalFactory> */
    use HasFactory;

    use LogsActivity;

    // Past bookings point at it: it leaves the lists but never the history.
    use SoftDeletes;
    use TracksUserActions;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'name', 'is_active', 'sort_order', 'appointment_capacity', 'appointments_per_day'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('professional');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'appointment_capacity' => 'integer',
            'appointments_per_day' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Offered for new bookings, in the order the owner arranged them.
     *
     * @param  Builder<self>  $query
     */
    public function scopeBookable(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * The services they attend, each with its own optional length and price.
     *
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->withPivot(['duration_minutes', 'price_type', 'price', 'deposit']);
    }

    /**
     * Their own weekly shifts; none means they follow the business hours.
     *
     * @return HasMany<ProfessionalHour, $this>
     */
    public function hours(): HasMany
    {
        return $this->hasMany(ProfessionalHour::class)->orderBy('day_of_week')->orderBy('opens_at');
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Set only when the professional also signs in to the panel.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
