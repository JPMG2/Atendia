<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Reads the comments out of a source file so the guardian can judge them.
 *
 * PHP goes through the tokenizer, not a regex: a regex cannot tell a comment
 * from the same characters inside a string, and URLs are full of `//`.
 *
 * Recipe: .ai/guidelines/reglas-de-oro-enforcement.md
 */
final class CommentScanner
{
    /**
     * Function words that only belong to one language.
     *
     * A comment with Spanish ones and no English ones is Spanish, however
     * technical the rest of it reads. It catches what the list below misses:
     * "Solo prevenir peticiones HTTP en testing" holds no accent and no
     * telltale noun.
     *
     * @var list<string>
     */
    private const SPANISH_FUNCTION_WORDS = [
        'el', 'la', 'los', 'las', 'un', 'una', 'de', 'del', 'al', 'en', 'y',
        'que', 'para', 'con', 'por', 'se', 'es', 'lo', 'su', 'sus', 'si',
        'como', 'pero', 'no', 'son', 'esta', 'este', 'esto', 'hay', 'ya',
    ];

    /** @var list<string> */
    private const ENGLISH_FUNCTION_WORDS = [
        'the', 'of', 'to', 'is', 'and', 'that', 'it', 'for', 'in', 'on',
        'with', 'not', 'but', 'so', 'as', 'at', 'by', 'a', 'an', 'this',
        'are', 'be', 'from', 'or', 'its', 'has', 'have', 'would', 'when',
        'what', 'which', 'they', 'we', 'you', 'one', 'only', 'never',
    ];

    /**
     * Words that place a comment in Spanish on their own.
     *
     * @var list<string>
     */
    private const SPANISH_WORDS = [
        'que', 'para', 'los', 'las', 'del', 'una', 'por', 'pero', 'porque',
        'cuando', 'donde', 'esto', 'esta', 'este', 'hay', 'tiene', 'cada',
        'desde', 'hasta', 'entre', 'segun', 'sobre', 'ser', 'son', 'esta',
        'mas', 'tambien', 'aqui', 'asi', 'sino', 'hace', 'hacer', 'puede',
        'debe', 'sea', 'sus', 'nos', 'les', 'como', 'nunca', 'siempre',
        'campo', 'tabla', 'registro', 'pantalla', 'formulario', 'usuario',
    ];

    /** Longest run of consecutive single-line comments allowed. */
    public const MAX_INLINE_LINES = 3;

    /** Longest prose block allowed inside a docblock (lines that are not @tags). */
    public const MAX_DOCBLOCK_PROSE = 5;

    /**
     * Every comment in a file, as plain text.
     *
     * @return list<string>
     */
    public static function commentsIn(string $path, string $contents): array
    {
        if (str_ends_with($path, '.blade.php')) {
            preg_match_all('/\{\{--(.*?)--\}\}/s', $contents, $matches);

            return $matches[1];
        }

        if (str_ends_with($path, '.js')) {
            preg_match_all('#(?:^|\s)//(.*)$|/\*(.*?)\*/#ms', $contents, $matches);

            return array_values(array_filter(array_map(
                fn (string $a, string $b): string => trim($a.$b),
                $matches[1],
                $matches[2],
            )));
        }

        $sourceLines = preg_split('/\R/', $contents) ?: [];
        $found = [];

        foreach (token_get_all($contents) as $token) {
            if (! is_array($token) || ! in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $text = rtrim($token[1]);

            if (self::isFrameworkBanner($text)) {
                continue;
            }

            // A comment trailing code is its own remark, never part of the
            // paragraph above it.
            $sourceLine = trim($sourceLines[$token[2] - 1] ?? '');
            $ownsItsLine = str_starts_with($sourceLine, '//')
                || str_starts_with($sourceLine, '#')
                || str_starts_with($sourceLine, '/*')
                || str_starts_with($sourceLine, '*');

            $found[] = ['text' => $text, 'line' => $token[2], 'own' => $ownsItsLine];
        }

        return self::mergeRuns($found);
    }

    /**
     * Merges a run of `//` lines sitting on consecutive lines into one comment,
     * so a wall of them is judged as the single paragraph it reads as.
     *
     * @param  list<array{text: string, line: int, own: bool}>  $found
     * @return list<string>
     */
    private static function mergeRuns(array $found): array
    {
        $merged = [];
        $run = [];
        $previousLine = null;

        foreach ($found as $comment) {
            $isInline = ! str_starts_with(ltrim($comment['text']), '/*') && $comment['own'];
            $follows = $previousLine !== null && $comment['line'] === $previousLine + 1;

            if ($isInline && $follows && $run !== []) {
                $run[] = $comment['text'];
                $previousLine = $comment['line'];

                continue;
            }

            if ($run !== []) {
                $merged[] = implode("\n", $run);
                $run = [];
            }

            if ($isInline) {
                $run = [$comment['text']];
                $previousLine = $comment['line'];

                continue;
            }

            $merged[] = $comment['text'];
            $previousLine = null;
        }

        if ($run !== []) {
            $merged[] = implode("\n", $run);
        }

        return $merged;
    }

    /**
     * Laravel ships its config and stubs with `|-----|` banner blocks. They are
     * the framework's, not ours, and rewriting them is pure churn.
     */
    private static function isFrameworkBanner(string $comment): bool
    {
        return str_contains($comment, '|-----');
    }

    /**
     * Whether a file is judged on comment length.
     *
     * Published config is vendor text — Laravel's and spatie's — rewritten on
     * every package update. Its language still has to be English, but its
     * length is not ours to trim.
     */
    public static function judgesLength(string $path): bool
    {
        return ! str_starts_with($path, 'config/');
    }

    /** Whether a comment reads as Spanish. */
    public static function isSpanish(string $comment): bool
    {
        if (preg_match('/[áéíóúÁÉÍÓÚñÑ¿¡]/u', $comment) === 1) {
            return true;
        }

        // A URL is not prose: "docs.example.org/en/stable" would read as the
        // Spanish "en" and drag the whole comment with it.
        $prose = preg_replace('#\bhttps?://\S+#i', ' ', $comment) ?? $comment;

        $words = preg_split('/[^a-z]+/', mb_strtolower($prose)) ?: [];

        if (array_intersect($words, self::SPANISH_WORDS) !== []) {
            return true;
        }

        return array_intersect($words, self::SPANISH_FUNCTION_WORDS) !== []
            && array_intersect($words, self::ENGLISH_FUNCTION_WORDS) === [];
    }

    /**
     * Whether a comment is longer than the rule allows.
     *
     * A docblock is measured on the description above its first tag. Whatever
     * follows a tag is structure — an array shape spans lines and is not an
     * essay.
     */
    public static function isTooLong(string $comment): bool
    {
        $lines = preg_split('/\R/', trim($comment)) ?: [];

        if (! str_starts_with(trim($comment), '/**')) {
            return count($lines) > self::MAX_INLINE_LINES;
        }

        $prose = [];

        foreach ($lines as $line) {
            $line = trim(ltrim(trim($line), '*/ '));

            if (str_starts_with($line, '@')) {
                break;
            }

            if ($line !== '') {
                $prose[] = $line;
            }
        }

        return count($prose) > self::MAX_DOCBLOCK_PROSE;
    }
}
