<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TracksUserActions;
use Database\Factories\SuggestedServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A concrete service the catalog SUGGESTS to one activity, GBP-style:
 * "Corte de caballero" to the barber, "Menú del día" to the restaurant.
 * Not to be confused with {@see Service} (what a tenant actually offers):
 * adopting a suggestion creates a Service already carrying its type.
 */
#[Fillable(['business_activity_id', 'service_type_id', 'name', 'regional_names', 'sort_order', 'is_active'])]
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
            ->logOnly(['business_activity_id', 'service_type_id', 'name', 'regional_names', 'sort_order', 'is_active'])
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
            'regional_names' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * The catalog type a hand-typed name adopts when the curated suggestions
     * know it — shared by every writer so no service saver re-invents it.
     */
    public static function typeIdFor(string $name): ?int
    {
        $name = mb_strtolower($name);

        return self::query()
            ->where(fn ($query) => $query
                ->whereRaw('lower(name) = ?', [$name])
                ->orWhereRaw('exists (select 1 from jsonb_each_text(regional_names) as regional where lower(regional.value) = ?)', [$name]))
            ->value('service_type_id');
    }

    /**
     * How this suggestion is said in a Spanish variant (es_AR says "fiambrería"
     * where the base says "charcutería"); a variant with no entry reads the base.
     */
    public function nameFor(string $locale): string
    {
        return $this->regional_names[$locale] ?? $this->name;
    }

    /**
     * The specialties this suggestion is for. A suggestion with any is left out
     * of its activity's general list: it is offered to the businesses that marked one of them.
     *
     * @return BelongsToMany<Specialty, $this>
     */
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'specialty_suggested_service')->withTimestamps();
    }

    /**
     * The names the marked specialties suggest, in demand order, with the same
     * product/service split as the activity's own list. Empty when none is marked.
     *
     * @param  list<int>  $specialtyIds
     * @param  bool  $goods  true = only the product names, false = only the services
     * @return list<string>
     */
    public static function namesForSpecialties(array $specialtyIds, bool $goods = false): array
    {
        if ($specialtyIds === []) {
            return [];
        }

        return self::query()
            ->where('is_active', true)
            ->whereHas('specialties', fn ($query) => $query->whereIn('specialties.id', $specialtyIds))
            ->with('serviceType.modality')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (self $row): bool => ($row->serviceType?->modality?->code === 'producto') === $goods)
            ->map(fn (self $row): string => $row->nameFor(app()->getLocale()))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Every name an activity can put on a business's list: its general ones and
     * the ones tied to a specialty. Tells what a trade added, so that changing
     * trade can take its names back out.
     *
     * @param  bool  $goods  true = only the product names, false = only the services
     * @return list<string>
     */
    public static function namesOfActivity(string $code, bool $goods = false): array
    {
        return self::query()
            ->whereHas('activity', fn ($query) => $query->where('code', $code))
            ->with('serviceType.modality')
            ->get()
            ->filter(fn (self $row): bool => ($row->serviceType?->modality?->code === 'producto') === $goods)
            ->map(fn (self $row): string => $row->nameFor(app()->getLocale()))
            ->unique()
            ->values()
            ->all();
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
