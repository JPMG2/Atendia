<?php

declare(strict_types=1);

namespace App\Services\Logs;

/**
 * What makes two log lines the same problem.
 *
 * Same level, same exception class and place in the code, and the same message
 * once the parts that change every time (numbers, ids, hashes) are taken out.
 * Forty "HTTP request returned status code 502" with different timings are one
 * problem, and the same message from two places in the code are two.
 */
final readonly class LogFingerprint
{
    private const array LEVELS = [
        'emergency' => 'error', 'alert' => 'error', 'critical' => 'error', 'error' => 'error',
        'warning' => 'warning',
    ];

    private const int TITLE_LENGTH = 300;

    public function __construct(
        public string $hash,
        public string $level,
        public string $title,
        public ?string $exception,
        public ?string $location,
    ) {}

    /** Null for what is not a problem: info, debug and notice lines never get a row. */
    public static function of(string $level, string $headline): ?self
    {
        $level = self::LEVELS[strtolower($level)] ?? null;

        if ($level === null) {
            return null;
        }

        // Monolog appends its context as JSON after the message.
        $split = strpos($headline, ' {"');
        $message = $split === false ? $headline : substr($headline, 0, $split);
        $context = $split === false ? '' : substr($headline, $split);

        [$exception, $location] = self::origin($context);
        $title = self::normalize($message);

        if ($title === '') {
            $title = $exception ?? '—';
        }

        return new self(sha1(implode('|', [$level, $exception, $location, $title])), $level, $title, $exception, $location);
    }

    /**
     * The exception class and where it was thrown, when the context has them.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function origin(string $context): array
    {
        if (preg_match('/\[object\] \(([^()]+?)\(code: -?\d+\): .*? at (\S+?):(\d+)\)/', $context, $found) !== 1) {
            return [null, null];
        }

        $class = str_replace('\\\\', '\\', $found[1]);
        $file = str_replace(base_path().'/', '', $found[2]);

        return [mb_substr($class, 0, 200), mb_substr($file.':'.$found[3], 0, 300)];
    }

    private static function normalize(string $message): string
    {
        $message = (string) preg_replace('/\s*\(View: [^)]*\)/', '', $message);
        $message = (string) preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '{id}', $message);
        $message = (string) preg_replace('/\b[0-9a-f]{16,}\b/i', '{hash}', $message);
        // Generated names (a debugbar file, a temp key): long, and either shouting or mixing letters with digits.
        $message = (string) preg_replace('/\b(?:[A-Z]{16,}|(?=[A-Za-z0-9]*\d)(?=[A-Za-z0-9]*[A-Za-z])[A-Za-z0-9]{16,})\b/', '{id}', $message);
        $message = (string) preg_replace('/\d+/', 'N', $message);

        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $message)), 0, self::TITLE_LENGTH);
    }
}
