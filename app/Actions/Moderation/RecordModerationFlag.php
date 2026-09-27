<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Dto\ModerationVerdictDto;
use App\Enums\ModerationSeverity;
use App\Mail\ModerationAlert;
use App\Messaging\Channels\Email;
use App\Models\Business;
use App\Models\ModerationFlag;

/**
 * Writes down a catch and sets its consequence: the admin hears of every
 * new one, and a severe one suspends the business. Keyed by fingerprint,
 * because a live form validates the same file more than once.
 */
class RecordModerationFlag
{
    public function __construct(private SuspendBusiness $suspend) {}

    public function handle(Business $business, string $source, string $kind, ModerationVerdictDto $verdict, string $fingerprint): void
    {
        if (! in_array($verdict->severity, [ModerationSeverity::Rejected, ModerationSeverity::Severe], true)) {
            return;
        }

        $flag = ModerationFlag::query()->firstOrCreate(
            ['business_id' => $business->id, 'fingerprint' => $fingerprint, 'source' => $source],
            ['kind' => $kind, 'severity' => $verdict->severity, 'category' => $verdict->category, 'score' => $verdict->score],
        );

        if ($verdict->severity === ModerationSeverity::Severe) {
            $this->suspend->handle($business, $verdict->category);
        } elseif ($flag->wasRecentlyCreated && $this->isRepeatOffender($business)) {
            $this->suspend->handle($business, 'repeat');
        }

        $admin = (string) config('atendia.admin_email');

        if ($flag->wasRecentlyCreated && $admin !== '') {
            (new Email($flag, [$admin], ModerationAlert::class))->send();
        }
    }

    /** None alone was severe, but a pattern of refused files is the fachada's signature. */
    private function isRepeatOffender(Business $business): bool
    {
        return ModerationFlag::query()
            ->where('business_id', $business->id)
            ->where('severity', ModerationSeverity::Rejected)
            ->where('created_at', '>=', now()->subDays((int) config('atendia.moderation.repeat_days')))
            ->count() >= (int) config('atendia.moderation.repeat_limit');
    }
}
