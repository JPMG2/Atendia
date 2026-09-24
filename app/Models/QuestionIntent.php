<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the customer is after — the stable half of a topic. Universal (no
 * activity), the trade's own (generated once per activity) or proposed by
 * one business until enough businesses of its trade see it too.
 */
#[Fillable(['business_activity_id', 'proposed_by_business_id', 'key', 'name', 'description', 'sort_order', 'embedding'])]
class QuestionIntent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_activity_id' => 'integer',
            'proposed_by_business_id' => 'integer',
            'embedding' => 'array',
        ];
    }

    /**
     * The menu the conversation analyst chooses from for ONE business:
     * universal, its trades' own and what it proposed itself.
     *
     * @return array<string, array{id: int, name: string, description: string}>
     */
    public static function menuFor(Business $business): array
    {
        $activityIds = $business->activities()->pluck('business_activities.id')->all();

        return self::query()
            ->where(fn (Builder $visible): Builder => $visible
                ->where(fn (Builder $shared): Builder => $shared
                    ->whereNull('proposed_by_business_id')
                    ->where(fn (Builder $layer): Builder => $layer->whereNull('business_activity_id')->orWhereIn('business_activity_id', $activityIds)))
                ->orWhere('proposed_by_business_id', $business->id))
            ->orderByRaw('business_activity_id is not null')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'key', 'name', 'description'])
            ->mapWithKeys(fn (self $intent): array => [$intent->key => [
                'id' => (int) $intent->id,
                'name' => $intent->name,
                'description' => $intent->description,
            ]])
            ->all();
    }

    /**
     * The intent closest in meaning within reach of a trade: universal, the
     * trade's own and ANY business's proposal in it — that is how two
     * businesses proposing the same thing end up on one topic.
     *
     * @param  list<float>  $vector
     * @return array{id: int, similarity: float}|null
     */
    public static function closestFor(array $vector, ?int $activityId): ?array
    {
        $intent = self::query()
            ->whereNotNull('embedding')
            ->where(fn (Builder $reach): Builder => $reach->whereNull('business_activity_id')->when(
                $activityId !== null,
                fn (Builder $trade): Builder => $trade->orWhere('business_activity_id', $activityId),
            ))
            ->select('id')
            ->selectVectorDistance('embedding', $vector, as: 'distance')
            ->orderByVectorDistance('embedding', $vector)
            ->first();

        return $intent === null ? null : [
            'id' => (int) $intent->id,
            'similarity' => 1 - (float) $intent->getAttribute('distance'),
        ];
    }

    /**
     * A proposal seen by enough distinct businesses becomes the trade's
     * own. Counts across tenants on purpose, hence the tenant-less context.
     */
    public function promoteIfShared(int $businesses): void
    {
        if ($this->proposed_by_business_id === null) {
            return;
        }

        $seenBy = app(Tenant::class)->for(null, fn (): int => ConversationQuestion::query()
            ->where('question_intent_id', $this->id)
            ->distinct()
            ->count('business_id'));

        if ($seenBy >= $businesses) {
            $this->forceFill(['proposed_by_business_id' => null])->save();
        }
    }

    /**
     * @return BelongsTo<BusinessActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(BusinessActivity::class, 'business_activity_id');
    }
}
