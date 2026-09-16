<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Human wording for a raw user-agent, shared by the device and the activity
 * rows: nobody recognises the raw string. Expects a `user_agent` attribute.
 */
trait DescribesUserAgent
{
    /** "Chrome · Windows"; an unknown agent shows as-is, truncated. */
    public function label(): string
    {
        $agent = $this->user_agent;

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        $platform = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        if ($browser === null && $platform === null) {
            return Str::limit($agent, 40);
        }

        return implode(' · ', array_filter([$browser, $platform]));
    }

    public function isMobile(): bool
    {
        return str_contains($this->user_agent, 'Android')
            || str_contains($this->user_agent, 'iPhone')
            || str_contains($this->user_agent, 'iPad');
    }
}
