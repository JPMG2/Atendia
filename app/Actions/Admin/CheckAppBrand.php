<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\PlatformEventKind;
use App\Models\Company;
use Illuminate\Support\Str;

/**
 * The name under the phone's icon is baked into the install, so a rename on the web
 * leaves old installs behind. Each install says what it was built with; when it
 * differs from the company row, one line on the feed counts them.
 *
 * It never closes itself: a new build matching says nothing about the old installs
 * still out there, so the admin marks it attended once the new version is published.
 */
class CheckAppBrand
{
    public function __construct(private readonly RaisePlatformEvent $raise) {}

    public function handle(?string $built): void
    {
        // The header is the client's word, sent URL-encoded: decoded, trimmed and cut, and an install that says nothing is an older build.
        $built = Str::limit(trim(strip_tags(rawurldecode((string) $built))), 60, '');

        if ($built === '') {
            return;
        }

        $web = Company::brand();

        if (mb_strtolower($built) === mb_strtolower($web)) {
            return;
        }

        $this->raise->handle(
            PlatformEventKind::AppBrandStale,
            null,
            __('platform_events.app_brand_stale_detail', ['built' => $built, 'web' => $web]),
            'app_brand_stale',
        );
    }
}
