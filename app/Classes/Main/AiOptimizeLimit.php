<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Business;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * The daily ceiling on "Optimizar con IA", one for every screen that has it
 * (identity, services, products) and for the app: the platform pays for these
 * calls, so a business gets a good-use limit, not a plan quota. The Actions
 * read it before they call the model; the screens read the same sentence.
 */
class AiOptimizeLimit
{
    private ?int $spent = null;

    public function __construct(private Business $business) {}

    public static function for(Business $business): self
    {
        return new self($business);
    }

    /** @var int uses allowed per day (a platform setting overrides the file) */
    public int $cap {
        get => max(1, (int) config('atendia.ai_optimize.daily_uses'));
    }

    /** @var int uses spent today, read once per instance */
    public int $used {
        get => $this->spent ??= $this->business->aiOptimizeUsesToday();
    }

    /** @var bool no use is left today */
    public bool $reached {
        get => $this->used >= $this->cap;
    }

    /** @var int uses still available today */
    public int $left {
        get => max(0, $this->cap - $this->used);
    }

    /** @var string `ok`, `warn` from 80% of the ceiling, `over` once it is spent: the same states the plan meters use */
    public string $state {
        get => Plan::usageState($this->used, $this->cap);
    }

    /** @var string the counter beside the button: how many uses are left, and a plain warning near the end */
    public string $note {
        get => trans_choice(
            $this->state === 'warn' ? 'client.ai_left_warn' : 'client.ai_left',
            $this->left,
            ['left' => $this->left, 'cap' => $this->cap],
        );
    }

    /** @var string what the owner reads when she is out of uses, on the web and in the app */
    public string $message {
        get => __('client.ai_limit', ['cap' => $this->cap]);
    }

    /**
     * Runs one "Optimizar con IA" of this business at a time, web and app alike: the meter
     * writes its row only once the model answers, so calls side by side would all read the
     * same count and all go through. A call waits for the one before it (up to a minute).
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     *
     * @throws LockTimeoutException
     */
    public static function serially(Business $business, callable $call): mixed
    {
        return Cache::lock('ai-optimize:'.$business->id, 90)->block(60, $call);
    }

    /**
     * The counter as the app draws it beside its button: the web's sentence and its tone.
     *
     * @return array{note: string, state: string, reached: bool}
     */
    public function toApi(): array
    {
        return ['note' => $this->note, 'state' => $this->state, 'reached' => $this->reached];
    }
}
