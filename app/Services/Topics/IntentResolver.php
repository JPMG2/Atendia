<?php

declare(strict_types=1);

namespace App\Services\Topics;

use App\Models\Business;
use App\Models\QuestionIntent;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Support\Str;

/**
 * Turns an intent the analyst had to name itself into a topic: the closest
 * existing one when it means the same, a new proposal of this business
 * otherwise. The proposal carries the trade so its peers can converge on it.
 */
class IntentResolver
{
    public function __construct(private KnowledgeEmbedder $embedder) {}

    public function resolve(Business $business, string $name, string $description): int
    {
        $vector = $this->embedder->embedOne(ActivityIntents::meaning($name, $description));
        $activityId = $business->primaryActivity()?->id;
        $closest = QuestionIntent::closestFor($vector, $activityId);

        if ($closest !== null && $closest['similarity'] >= (float) config('atendia.analysis.same_intent_similarity')) {
            return $closest['id'];
        }

        return (int) QuestionIntent::query()->firstOrCreate(
            ['key' => Str::limit('proposed.'.$business->id.'.'.Str::slug($name, '_'), 80, '')],
            [
                'business_activity_id' => $activityId,
                'proposed_by_business_id' => $business->id,
                'name' => Str::limit($name, 77),
                'description' => Str::limit($description, 250),
                'sort_order' => 0,
                'embedding' => $vector,
            ],
        )->id;
    }
}
