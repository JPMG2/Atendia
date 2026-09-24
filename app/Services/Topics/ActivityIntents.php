<?php

declare(strict_types=1);

namespace App\Services\Topics;

use App\Ai\Agents\ActivityIntentDesigner;
use App\Models\Business;
use App\Models\BusinessActivity;
use App\Models\QuestionIntent;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Gives each trade its own intents the first time one of its businesses
 * needs them, and keeps every shared intent's meaning vector filled. Lazy
 * on purpose: an activity added from the admin tomorrow is covered too.
 */
class ActivityIntents
{
    public function __construct(private KnowledgeEmbedder $embedder) {}

    public function ensureFor(Business $business): void
    {
        $pending = $business->activities()->whereNull('intents_generated_at')->get();

        foreach ($pending as $activity) {
            // Two businesses of one trade finishing at once must not generate twice.
            Cache::lock("activity-intents:{$activity->id}", 120)->get(fn () => $this->generate($activity));
        }

        $this->embedMissing();
    }

    private function generate(BusinessActivity $activity): void
    {
        if ($activity->fresh()?->intents_generated_at !== null) {
            return;
        }

        $universal = QuestionIntent::query()
            ->whereNull('business_activity_id')
            ->whereNull('proposed_by_business_id')
            ->orderBy('sort_order')
            ->get(['name', 'description'])
            ->map(fn (QuestionIntent $intent): string => "- {$intent->name}: {$intent->description}")
            ->implode("\n");

        $sector = $activity->sector()->value('name');
        $response = ActivityIntentDesigner::make()->prompt("Rubro: {$activity->name}".($sector !== null ? " (sector {$sector})" : '')."\n\nIntenciones universales:\n{$universal}");

        foreach (array_values((array) ($response['intents'] ?? [])) as $order => $intent) {
            $name = Str::limit(trim((string) ($intent['name'] ?? '')), 77);

            if ($name === '') {
                continue;
            }

            QuestionIntent::query()->updateOrCreate(
                ['key' => Str::limit($activity->code.'.'.Str::slug($name, '_'), 80, '')],
                [
                    'business_activity_id' => $activity->id,
                    'name' => $name,
                    'description' => Str::limit(trim((string) ($intent['description'] ?? '')), 250),
                    'sort_order' => $order + 1,
                ],
            );
        }

        $activity->forceFill(['intents_generated_at' => now()])->saveQuietly();
    }

    /** One batch for every shared intent still without its vector. */
    private function embedMissing(): void
    {
        $intents = QuestionIntent::query()->whereNull('embedding')->get(['id', 'name', 'description']);

        if ($intents->isEmpty()) {
            return;
        }

        $vectors = $this->embedder->embed($intents->map(fn (QuestionIntent $intent): string => self::meaning($intent->name, $intent->description))->all());

        $intents->values()->each(fn (QuestionIntent $intent, int $index) => $intent->forceFill(['embedding' => $vectors[$index]])->save());
    }

    /** The text an intent's vector is made of: name and scope, like the calibration. */
    public static function meaning(string $name, string $description): string
    {
        return trim($name.': '.$description, ': ');
    }
}
