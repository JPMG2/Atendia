<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TracksUserActions;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The business that hires AtendIa: the TENANT.
 *
 * Not to be confused with {@see Company}, which is AtendIa itself — the one
 * issuing the invoice, a single row. Every operational record hangs off here.
 */
#[Fillable(['name', 'country_id', 'province_id', 'timezone', 'billing_email', 'whatsapp_number', 'fallback_whatsapp_number', 'email', 'web', 'is_active'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    use LogsActivity;

    // A business is never deleted, only deactivated: the records hanging off it
    // have to stay traceable.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * Tenant audit: who changed what, and when.
     *
     * The `*_by` columns are the shortcut for showing the author on screen; the
     * FULL trail, old and new values, lives in `activity_log`. Those columns stay
     * out of the log — spatie resolves the causer on its own.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'country_id', 'province_id', 'timezone', 'billing_email', 'whatsapp_number', 'fallback_whatsapp_number', 'email', 'web', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('business');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * The activities the business declared, the primary one first.
     *
     * Several on purpose: a bakery that puts out tables adds a coffee-shop
     * activity and starts seeing the room's service types — no special case in
     * the code, and nobody unlocking anything by hand.
     *
     * @return BelongsToMany<BusinessActivity, $this>
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(BusinessActivity::class, 'activity_business')
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps()
            ->orderByDesc('activity_business.is_primary')
            ->orderBy('activity_business.sort_order');
    }

    /**
     * The PRIMARY activity, or null while the business has picked none.
     *
     * It drives the assistant's tone, the trade's knowledge pack and the reports.
     * A method and not a relation: as a property Eloquent would look for a
     * relation and fail. It reuses the loaded collection when there is one, so a
     * grid does not fire a query per business.
     */
    public function primaryActivity(): ?BusinessActivity
    {
        if ($this->relationLoaded('activities')) {
            return $this->activities->firstWhere('pivot.is_primary', true);
        }

        return $this->activities()->wherePivot('is_primary', true)->first();
    }

    /**
     * Leaves the business's activities at exactly this.
     *
     * One primary — a partial unique index guarantees it too — and the secondary
     * ones in the order they arrive. Passing the primary among the secondary ones
     * does not duplicate it: it is ignored.
     *
     * @param  list<int>  $secondaryIds
     */
    public function syncActivities(?int $primaryId, array $secondaryIds = []): void
    {
        $pivot = [];

        if ($primaryId !== null) {
            $pivot[$primaryId] = ['is_primary' => true, 'sort_order' => 0];
        }

        $order = 0;

        foreach ($secondaryIds as $id) {
            if ($id === $primaryId || isset($pivot[$id])) {
                continue;
            }

            $pivot[$id] = ['is_primary' => false, 'sort_order' => ++$order];
        }

        $this->activities()->sync($pivot);
    }

    /**
     * The service types SUGGESTED to this business: the union of what each of its
     * activities suggests.
     *
     * Union and not intersection — the bakery that also serves coffee has to see
     * both. And it stays a suggestion: nothing stops adopting a type that is not
     * here. This is what shows up first, not what is allowed.
     *
     * @return Collection<int, ServiceType>
     */
    public function suggestedServiceTypes(): Collection
    {
        return ServiceType::query()
            ->whereHas(
                'activities',
                fn (Builder $query): Builder => $query->whereIn(
                    'business_activities.id',
                    $this->activities()->select('business_activities.id'),
                ),
            )
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * The services this business actually offers, in its own words — not to
     * be confused with the catalog's service TYPES, which are the moulds.
     *
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * The goods this business sells — the universal core the import maps
     * onto; anything beyond it lives in the product's knowledge.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<ProductImport, $this>
     */
    public function productImports(): HasMany
    {
        return $this->hasMany(ProductImport::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<KnowledgeDocument, $this>
     */
    public function knowledgeDocuments(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    /**
     * The networks the account is on, in display order.
     *
     * The relation is polymorphic: one table holds the company's networks and
     * every business's ({@see SocialLink}).
     *
     * @return MorphMany<SocialLink, $this>
     */
    public function socialLinks(): MorphMany
    {
        return $this->morphMany(SocialLink::class, 'linkable')->orderBy('sort_order');
    }
}
