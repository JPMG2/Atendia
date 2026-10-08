<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\AiModel;
use App\Models\AiTask;
use App\Models\AiUsage;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What a metered row was worth, at the price its model had that month.
 *
 * It lives apart from the month that reads it because two readings price the
 * same rows now: the month on screen and the twelve behind the trend. Valuing
 * them twice is how a figure and its own history end up disagreeing.
 */
final class AiPrices
{
    /** @var Collection<string, Collection<int, AiModel>> Every published price, read once. */
    private Collection $book;

    /** @var array<string, mixed> */
    private array $rates;

    public function __construct()
    {
        $this->book = AiModel::priceBook();
        $this->rates = config('atendia.ai_rates');
    }

    /** Whether this row can be put a price on at all. */
    public function valuable(object $row, CarbonInterface $month): bool
    {
        return in_array($row->kind, [AiUsage::TRANSCRIPTION, AiUsage::EMBEDDINGS], true)
            || $this->priceOf($row->model, $month) !== null;
    }

    /** The price the model had THAT month, out of the book read once. */
    public function priceOf(?string $code, CarbonInterface $month): ?AiModel
    {
        if ($code === null) {
            return null;
        }

        return $this->book->get($code, new Collection)
            ->first(fn (AiModel $price): bool => $price->effective_from->lessThanOrEqualTo($month));
    }

    /**
     * Transcription is priced by the audio minute, so its tokens add nothing
     * here. A row with no price adds nothing either: it is counted apart as
     * unpriced, because zero would read as "it was free".
     *
     * @param  Collection<int, object>  $rows
     */
    public function costOf(Collection $rows, CarbonInterface $month, int $audioSeconds = 0): float
    {
        $tokens = $rows->sum(function (object $row) use ($month): float {
            if ($row->kind === AiUsage::TRANSCRIPTION) {
                return 0.0;
            }

            if ($row->kind === AiUsage::EMBEDDINGS) {
                // The catalog price of the model that made them, so a change of model
                // is billed at its own rate; the configured one covers a model not listed.
                $price = $this->priceOf($row->model, $month);

                return $row->input_tokens * (float) ($price?->prompt_per_million ?? $this->rates['embedding_per_million']);
            }

            $price = $this->priceOf($row->model, $month);

            return $price === null ? 0.0 : $row->input_tokens * (float) $price->prompt_per_million
                + $row->cached_tokens * (float) $price->cached_per_million
                + $row->output_tokens * (float) $price->completion_per_million;
        });

        return $tokens / 1_000_000 + ($audioSeconds / 60) * $this->audioRate($month);
    }

    /**
     * USD per audio minute: the price of the model assigned to transcription
     * that month, and the configured rate while nothing is assigned. Seconds
     * come from the messages, not from a model, so one rate has to stand for
     * the month.
     */
    public function audioRate(CarbonInterface $month): float
    {
        $code = AiTask::query()->where('key', AiTask::TRANSCRIPTION)->value('model_code');

        return (float) ($this->priceOf($code, $month)?->per_minute ?? $this->rates['audio_per_minute']);
    }

    /**
     * What the cache took off the bill: every cached token billed at the cheap
     * rate instead of the full one. It is what turns the cache share into a
     * reason to keep the prompts in the order they are in.
     *
     * @param  Collection<int, object>  $rows
     */
    public function savingOf(Collection $rows, CarbonInterface $month): float
    {
        $saved = $rows->sum(function (object $row) use ($month): float {
            if (in_array($row->kind, [AiUsage::TRANSCRIPTION, AiUsage::EMBEDDINGS], true)) {
                return 0.0;
            }

            $price = $this->priceOf($row->model, $month);

            return $price === null ? 0.0 : $row->cached_tokens
                * ((float) $price->prompt_per_million - (float) $price->cached_per_million);
        });

        return $saved / 1_000_000;
    }
}
