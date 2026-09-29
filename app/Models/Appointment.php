<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Traits\BelongsToBusiness;
use App\Traits\TracksUserActions;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A booked slot of the business's agenda.
 *
 * `business_id` stays out of Fillable, same boundary as {@see Service}: a row
 * is born THROUGH its owner, so an id arriving in a request cannot move a
 * booking to another tenant.
 */
#[Fillable(['customer_id', 'service_id', 'conversation_id', 'starts_at', 'ends_at', 'status', 'source', 'notes', 'reminder_sent_at'])]
class Appointment extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    use LogsActivity;

    // A cancelled booking is history the assistant may have to explain: it is
    // soft-deleted, never wiped.
    use SoftDeletes;
    use TracksUserActions;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['customer_id', 'service_id', 'starts_at', 'ends_at', 'status', 'notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('appointment');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'service_id' => 'integer',
            'conversation_id' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
            'reminder_sent_at' => 'immutable_datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** The ones that still hold their hour: a cancelled slot is free again. */
    public function scopeHoldingSlot(Builder $query): void
    {
        $query->where('status', '!=', AppointmentStatus::Cancelled);
    }

    /**
     * Everything booked inside a window, earliest first — what the day view
     * paints and what the slot finder subtracts from the open hours. Pinned
     * to the business on purpose: a worker has no session, so the tenant
     * scope alone would not fence it.
     *
     * @return Collection<int, self>
     */
    public static function between(Business $business, CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        return $business->appointments()
            ->holdingSlot()
            ->where('starts_at', '<', $until->utc())
            ->where('ends_at', '>', $from->utc())
            ->with('customer:id,name,phone,profile_name', 'service:id,name,duration_minutes')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * The customer's own bookings still to come: what the assistant reads to
     * cancel or move "mi turno" without asking which one.
     *
     * @return Collection<int, self>
     */
    public static function upcomingFor(Customer $customer, CarbonImmutable $now): Collection
    {
        return $customer->appointments()
            ->where('status', AppointmentStatus::Confirmed)
            ->where('starts_at', '>=', $now->utc())
            ->with('service:id,name,duration_minutes')
            ->orderBy('starts_at')
            ->get();
    }

    /** Minutes the slot takes, the way the customer hears it. */
    public function durationMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }
}
