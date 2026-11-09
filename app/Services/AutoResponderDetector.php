<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AutoReplySignal;
use App\Models\Conversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Tells a machine from a person before the assistant answers, without a
 * model call: two bots replying to each other loop until the plan's hourly
 * cap, and every lap is a paid answer. A false alarm costs little — the
 * chat lands with the team, who hands it back in one click.
 */
class AutoResponderDetector
{
    public function detect(Conversation $conversation, string $sender, string $text, ?int $arrivedAtMs = null): ?AutoReplySignal
    {
        // The owner said "it was a person": that number is left alone for a while.
        if (Cache::has("wa:person:{$sender}")) {
            return null;
        }

        $normal = self::normalise($text);

        // A short text ("sí", "ok", "dale") is how people answer: it neither
        // repeats nor races into a verdict. Bot lines are long and canned.
        $long = strlen($normal) >= (int) config('atendia.bots.min_chars');

        // Updated on every inbound, so a slow or short reply resets the streak.
        $fastStreak = $this->fastStreak($sender, $arrivedAtMs, $long);

        if ($normal === '') {
            return null;
        }

        return match (true) {
            $this->echoes($conversation, $normal) => AutoReplySignal::Echo,
            $this->soundsCanned($normal) => AutoReplySignal::Phrase,
            $long && $this->repeats($conversation, $normal) => AutoReplySignal::Repeated,
            $fastStreak >= (int) config('atendia.bots.fast_replies') => AutoReplySignal::Fast,
            $this->bursts($conversation) => AutoReplySignal::Burst,
            default => null,
        };
    }

    /** Stamps the moment the assistant finished answering this sender. */
    public static function replied(string $sender): void
    {
        Cache::put("wa:replied:{$sender}", now()->getTimestampMs(), now()->addHour());
    }

    /**
     * A wrong call undone by the owner: no signal is read for this sender for
     * the configured hours, and the streaks start from zero afterwards.
     */
    public static function trustAsPerson(string $sender): void
    {
        Cache::put("wa:person:{$sender}", true, now()->addHours((int) config('atendia.bots.person_trust_hours')));
        Cache::forget("wa:fast:{$sender}");
    }

    public static function normalise(string $text): string
    {
        $plain = Str::lower(Str::ascii($text));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $plain));
    }

    /**
     * Our WHOLE reply coming back. Only that way round: a person confirming
     * quotes a piece of it ("el martes 12 a las 3"), which is no echo.
     */
    private function echoes(Conversation $conversation, string $normal): bool
    {
        $ours = self::normalise((string) $conversation->lastAssistantReply());

        if (strlen($ours) < (int) config('atendia.bots.echo_min_chars')) {
            return false;
        }

        return str_contains($normal, $ours);
    }

    /** A listed phrase as whole words, never inside another word ("con" in "conmigo"). */
    private function soundsCanned(string $normal): bool
    {
        foreach (self::phrases() as $needle) {
            if (str_contains(" {$normal} ", " {$needle} ")) {
                return true;
            }
        }

        return false;
    }

    /**
     * The usable lines of the editable list, normalised. A line under three
     * words is skipped: "ok" or "si" there would silence almost every chat.
     *
     * @return list<string>
     */
    public static function phrases(): array
    {
        $lines = preg_split('/\R/', (string) config('atendia.bots.phrases')) ?: [];

        return array_values(array_filter(
            array_map(self::normalise(...), $lines),
            fn (string $line): bool => $line !== '' && str_word_count($line) >= 3,
        ));
    }

    /** The same text again, back to back: the current one plus the copies right before it. */
    private function repeats(Conversation $conversation, string $normal): bool
    {
        $needed = (int) config('atendia.bots.repeat_threshold') - 1;
        $lastOnes = array_slice($conversation->recentInboundBodies(), 0, $needed);

        return count($lastOnes) === $needed
            && collect($lastOnes)->every(fn (string $body): bool => self::normalise($body) === $normal);
    }

    /**
     * No person reads, thinks and types a full line in under two seconds, lap
     * after lap. A short "ok" that fast is a person: it resets the streak.
     */
    private function fastStreak(string $sender, ?int $arrivedAtMs, bool $long): int
    {
        $repliedAt = Cache::get("wa:replied:{$sender}");
        $key = "wa:fast:{$sender}";

        if ($arrivedAtMs === null || $repliedAt === null) {
            return (int) Cache::get($key, 0);
        }

        if (! $long) {
            Cache::put($key, 0, now()->addHour());

            return 0;
        }

        $gap = $arrivedAtMs - (int) $repliedAt;

        // Written while our bubbles were still going out: a person adding a line
        // does that too, so it neither counts nor resets.
        if ($gap < 0) {
            return (int) Cache::get($key, 0);
        }

        if ($gap >= (int) config('atendia.bots.fast_reply_ms')) {
            Cache::put($key, 0, now()->addHour());

            return 0;
        }

        $streak = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $streak, now()->addHour());

        return $streak;
    }

    private function bursts(Conversation $conversation): bool
    {
        $since = now()->subMinutes((int) config('atendia.bots.burst_minutes'));

        return $conversation->assistantRepliesSince($since) >= (int) config('atendia.bots.burst_cap');
    }
}
