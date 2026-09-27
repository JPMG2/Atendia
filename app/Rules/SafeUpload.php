<?php

declare(strict_types=1);

namespace App\Rules;

use App\Actions\Moderation\RecordModerationFlag;
use App\Enums\ModerationSeverity;
use App\Models\Business;
use App\Services\ContentModeration;
use App\Services\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * The gate every image a business uploads passes BEFORE it is stored: the
 * validator is the one step every upload path already shares. A live form
 * validates the same file twice, hence the recorded flag is keyed by hash.
 */
class SafeUpload implements ValidationRule
{
    public function __construct(private string $source) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! is_readable((string) $value->getRealPath())) {
            return;
        }

        $path = (string) $value->getRealPath();
        $verdict = app(ContentModeration::class)->image($path);

        if ($verdict->severity === ModerationSeverity::Clean) {
            return;
        }

        if ($verdict->severity === ModerationSeverity::Unavailable) {
            $fail(__('moderation.upload.unavailable'));

            return;
        }

        $business = Business::query()->find(app(Tenant::class)->id());

        if ($business !== null) {
            app(RecordModerationFlag::class)->handle($business, $this->source, 'image', $verdict, (string) hash_file('sha256', $path));
        }

        $fail(__('moderation.upload.'.$verdict->severity->value));
    }
}
