<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SkillAudience;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Something the assistant can do with the business's real data. */
#[Fillable(['key', 'name', 'description', 'audience', 'is_universal', 'is_active', 'sort_order'])]
class AssistantSkill extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => SkillAudience::class,
            'is_universal' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The skill keys one business gets: the universal ones, sent upfront,
     * and its trades' own, deferred behind ToolSearch.
     *
     * @return array{universal: list<string>, trade: list<string>}
     */
    public static function keysFor(Business $business): array
    {
        $activityIds = $business->activities()->pluck('business_activities.id')->all();

        $skills = self::query()
            ->where('is_active', true)
            ->where('audience', SkillAudience::Customer)
            ->where(fn ($reach) => $reach->where('is_universal', true)
                ->orWhereHas('activities', fn ($trade) => $trade->whereIn('business_activities.id', $activityIds)))
            ->orderBy('sort_order')
            ->get(['key', 'is_universal']);

        return [
            'universal' => $skills->where('is_universal', true)->pluck('key')->values()->all(),
            'trade' => $skills->where('is_universal', false)->pluck('key')->values()->all(),
        ];
    }

    /**
     * The owner's skills, all upfront: a handful, and every one of them
     * serves any business — the owner asks about their own data, not a trade's.
     *
     * @return list<string>
     */
    public static function ownerKeys(): array
    {
        return self::query()
            ->where('is_active', true)
            ->where('audience', SkillAudience::Owner)
            ->orderBy('sort_order')
            ->pluck('key')
            ->all();
    }

    /**
     * @return BelongsToMany<BusinessActivity, $this>
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(BusinessActivity::class, 'activity_assistant_skill')->withTimestamps();
    }
}
