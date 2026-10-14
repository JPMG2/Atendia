<?php

declare(strict_types=1);

namespace App\Messaging\WhatsApp;

use App\Messaging\WhatsAppMessage;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

/**
 * The evening summary: what is still waiting on the admin, in a few lines.
 * Unlike an alert it interrupts nobody mid-day; it closes the day.
 */
class PlatformDigest extends WhatsAppMessage
{
    /**
     * @param  array{critical: int, important: int}  $counts
     * @param  list<string>  $lines  Already-worded lines of the worst items.
     * @param  string  $platform  Names of the infrastructure components with problems, or ''.
     * @param  int  $more  How many open items did not fit in `$lines`.
     */
    public function __construct(Model $model, private readonly array $counts, private readonly array $lines, private readonly string $platform, private readonly int $more)
    {
        parent::__construct($model);
    }

    public function text(): string
    {
        $parts = [__('platform_events.digest.head', ['brand' => Company::brand(), 'hour' => '20:00'])];

        if ($this->counts['critical'] > 0) {
            $parts[] = trans_choice('platform_events.digest.critical', $this->counts['critical']);
        }

        if ($this->counts['important'] > 0) {
            $parts[] = trans_choice('platform_events.digest.important', $this->counts['important']);
        }

        if ($this->platform !== '') {
            $parts[] = __('platform_events.digest.platform', ['names' => $this->platform]);
        }

        foreach ($this->lines as $line) {
            $parts[] = '• '.$line;
        }

        if ($this->more > 0) {
            $parts[] = __('platform_events.digest.more', ['count' => $this->more]);
        }

        $parts[] = __('platform_events.digest.open', ['url' => route('admin.alerts')]);

        return implode("\n", $parts);
    }
}
