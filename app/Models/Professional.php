<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use App\Traits\TracksUserActions;
use Database\Factories\ProfessionalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * The database defaults, mirrored: a row just created in memory has not read
     * them back, and an empty `is_active` would read as switched off.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

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
     * Who can take a new booking for a service, in the owner's order. Pinned to
     * the business on purpose: a worker has no session, so the tenant scope
     * alone would not fence it. Empty means the business has nobody set up for
     * it, and the agenda behaves as it always did.
     *
     * @return Collection<int, self>
     */
    public static function offeredFor(Business $business, ?Service $service): Collection
    {
        return $business->professionals()
            ->where('is_active', true)
            ->with(['services', 'hours'])
            ->get()
            ->filter(fn (self $professional): bool => $professional->attends($service))
            ->values();
    }

    /** Bookings still to come that hold their hour: what stops them being removed. */
    public function upcomingCount(): int
    {
        return $this->appointments()->holdingSlot()->where('ends_at', '>', now())->count();
    }

    /** Whether they attend the service; a plain slot with no service is open to everyone. */
    public function attends(?Service $service): bool
    {
        return $service === null || $this->services->contains('id', $service->id);
    }

    /** Their own length for the service; null when they follow the service's own. */
    public function ownMinutesFor(?Service $service): ?int
    {
        $minutes = $service === null ? null : $this->services->firstWhere('id', $service->id)?->pivot->duration_minutes;

        return $minutes === null ? null : (int) $minutes;
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
