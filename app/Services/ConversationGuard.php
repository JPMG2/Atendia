<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GuardVerdict;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The bouncer in front of the assistant: flood control and content
 * moderation BEFORE any model call, so time-wasters and abuse never spend
 * a token. Penalties escalate per sender (10 min → 1 h → 24 h) and live in
 * cache with a TTL — a persistent blacklist waits for the Conversations
 * screen, where the owner can manage it.
 */
class ConversationGuard
{
    /** Messages per sender per hour before the cooldown ladder kicks in. */
    private const int HOURLY_CAP = 30;

    /** Second offensive message inside a day earns the mute, not the first. */
    private const int OFFENSE_TOLERANCE = 1;

    private const array MUTE_MINUTES = [10, 60, 1440];

    public function verdict(string $instance, string $from, string $text): GuardVerdict
    {
        $sender = "{$instance}:{$from}";

        if (Cache::has("wa:mute:{$sender}")) {
            return GuardVerdict::Muted;
        }

        if ($this->countMessage($sender) > self::HOURLY_CAP) {
            $this->mute($sender);

            return GuardVerdict::TooMany;
        }

        if ($this->flagged($text)) {
            $offenses = (int) Cache::increment("wa:offense:{$sender}");
            Cache::put("wa:offense:{$sender}", $offenses, now()->addDay());

            if ($offenses > self::OFFENSE_TOLERANCE) {
                $this->mute($sender);
            }

            return GuardVerdict::Offensive;
        }

        return GuardVerdict::Ok;
    }

    private function countMessage(string $sender): int
    {
        $key = "wa:count:{$sender}";

        if (! Cache::has($key)) {
            Cache::put($key, 1, now()->addHour());

            return 1;
        }

        return (int) Cache::increment($key);
    }

    private function mute(string $sender): void
    {
        $mutes = (int) Cache::increment("wa:mutes:{$sender}");
        Cache::put("wa:mutes:{$sender}", $mutes, now()->addWeek());

        $minutes = self::MUTE_MINUTES[min($mutes, count(self::MUTE_MINUTES)) - 1];

        Cache::put("wa:mute:{$sender}", true, now()->addMinutes($minutes));
    }

    /**
     * OpenAI's moderation endpoint: free, ~100 ms, and it fails OPEN — a
     * moderation outage must never silence real customers.
     */
    private function flagged(string $text): bool
    {
        $key = (string) config('ai.providers.openai.key');

        if ($key === '') {
            return false;
        }

        try {
            return (bool) Http::withToken($key)
                ->connectTimeout(3)
                ->timeout(5)
                ->post(rtrim((string) config('ai.providers.openai.url'), '/').'/moderations', [
                    'model' => 'omni-moderation-latest',
                    'input' => $text,
                ])
                ->throw()
                ->json('results.0.flagged', false);
        } catch (\Throwable) {
            return false;
        }
    }
}
