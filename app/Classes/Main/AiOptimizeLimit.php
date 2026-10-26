<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Business;

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

    /** @var string what the owner reads when she is out of uses, on the web and in the app */
    public string $message {
        get => __('client.ai_limit', ['cap' => $this->cap]);
    }
}
