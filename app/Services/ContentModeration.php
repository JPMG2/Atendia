<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\ModerationVerdictDto;
use App\Enums\ModerationSeverity;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Screens what a BUSINESS puts on the platform, images and texts, with
 * OpenAI's free moderation. Unlike the customer guard it fails CLOSED: with
 * zero tolerance, what could not be checked does not get in. Its blind spot:
 * sexual/minors is judged on text only, never on images (lawyer + PhotoDNA).
 */
class ContentModeration
{
    /** The API reads long inputs as a list: one verdict per slice, the worst wins. */
    private const int TEXT_SLICE = 8000;

    public function image(string $path): ModerationVerdictDto
    {
        $mime = (string) (mime_content_type($path) ?: '');

        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
            return ModerationVerdictDto::unavailable();
        }

        return $this->moderate([[
            'type' => 'image_url',
            'image_url' => ['url' => 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path))],
        ]]);
    }

    public function text(string $text): ModerationVerdictDto
    {
        $text = trim($text);

        if ($text === '') {
            return ModerationVerdictDto::clean();
        }

        return $this->moderate(mb_str_split($text, self::TEXT_SLICE));
    }

    /** @param  list<mixed>  $input */
    private function moderate(array $input): ModerationVerdictDto
    {
        $key = (string) config('ai.providers.openai.key');

        if ($key === '') {
            return ModerationVerdictDto::unavailable();
        }

        try {
            $results = Http::withToken($key)
                ->connectTimeout(5)
                ->timeout(20)
                ->post(rtrim((string) config('ai.providers.openai.url'), '/').'/moderations', [
                    'model' => 'omni-moderation-latest',
                    'input' => $input,
                ])
                ->throw()
                ->json('results', []);
        } catch (Throwable $exception) {
            report($exception);

            return ModerationVerdictDto::unavailable();
        }

        return collect($results)
            ->map(fn (array $result): ModerationVerdictDto => $this->judge($result))
            ->sortByDesc(fn (ModerationVerdictDto $verdict): int => $this->weight($verdict->severity))
            ->first() ?? ModerationVerdictDto::unavailable();
    }

    /** @param  array{categories?: array<string, bool>, category_scores?: array<string, float>}  $result */
    private function judge(array $result): ModerationVerdictDto
    {
        $flags = $result['categories'] ?? [];
        $scores = $result['category_scores'] ?? [];
        $sexual = (float) ($scores['sexual'] ?? 0);

        if (($flags['sexual/minors'] ?? false) === true) {
            return new ModerationVerdictDto(ModerationSeverity::Severe, 'sexual/minors', (float) ($scores['sexual/minors'] ?? 1));
        }

        if ($sexual >= (float) config('atendia.moderation.severe_score')) {
            return new ModerationVerdictDto(ModerationSeverity::Severe, 'sexual', $sexual);
        }

        if (($flags['sexual'] ?? false) === true) {
            return new ModerationVerdictDto(ModerationSeverity::Rejected, 'sexual', $sexual);
        }

        return ModerationVerdictDto::clean();
    }

    private function weight(ModerationSeverity $severity): int
    {
        return match ($severity) {
            ModerationSeverity::Severe => 3,
            ModerationSeverity::Rejected => 2,
            ModerationSeverity::Unavailable => 1,
            ModerationSeverity::Clean => 0,
        };
    }
}
