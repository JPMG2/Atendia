<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;

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

            // The delimiters travel back: a Blade comment is a block, and
            // without them it would be measured as a run of `//` lines.
            $comments = array_map(fn (string $c): string => '{{--'.$c.'--}}', $matches[1]);

            // Half a Blade file is not Blade: the SFC class, the `@props` and
            // the `@script` are all code. Reading only `{{-- --}}` left every
            // one of them unwatched, and that is where they drifted to Spanish.
            return array_merge(
                $comments,
                self::phpComments(self::bladePhp($contents)),
                self::jsComments(self::bladeScripts($contents)),
            );
        }

        if (str_ends_with($path, '.js')) {
            return self::jsComments($contents);
        }

        return self::phpComments($contents);
    }

    /**
     * The comments of a JavaScript source.
     *
     * @return list<string>
     */
    private static function jsComments(string $contents): array
    {
        // Two passes on purpose: one regex with the `s` flag would let a `//`
        // line swallow the rest of the file. Only a `//` that OWNS its line
        // counts, or every `https://` would read as a comment.
        preg_match_all('#/\*(.*?)\*/#s', $contents, $blocks);
        preg_match_all('#^\s*//(.*)$#m', $contents, $lines);

        $comments = array_map(fn (string $b): string => '/*'.$b.'*/', $blocks[1]);

        foreach ($lines[1] as $line) {
            if (trim($line) !== '') {
                $comments[] = '//'.$line;
            }
        }

        return array_values($comments);
    }

    /**
     * The PHP a Blade file turns into, so the tokenizer can read all of it.
     *
     * Compiled instead of having its regions listed by hand: naming them one
     * by one is what left `@php`, `@props` and `@script` unwatched, and the
     * next directive would have slipped through the same way. Component tags
     * are skipped — they need a container and carry no comments.
     */
    private static function bladePhp(string $contents): string
    {
        $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir());

        $compiler->withoutComponentTags();

        return $compiler->compileString($contents);
    }

    /**
     * The JavaScript of every `<script>` in a Blade file, `@script` included.
     *
     * Blade hands this through untouched, so the tokenizer never sees it.
     */
    private static function bladeScripts(string $contents): string
    {
        preg_match_all('#<script\\b[^>]*>(.*?)</script>#is', $contents, $matches);

        return implode("\n", $matches[1]);
    }

    /**
     * The PHP comments in a source string, runs already merged.
     *
     * @return list<string>
     */
    private static function phpComments(string $contents): array
    {
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
     * The part of a comment that is actually prose.
     *
     * A URL and a quoted fragment are data being referred to, not writing: an
     * English comment quoting the Spanish copy on screen, or a place name, is
     * still an English comment.
     */
    private static function proseOf(string $comment): string
    {
        $prose = preg_replace('#\bhttps?://\S+#i', ' ', $comment) ?? $comment;

        return preg_replace('/"[^"]*"|\x27[^\x27]*\x27/u', ' ', $prose) ?? $prose;
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
        $prose = self::proseOf($comment);

        if (preg_match('/[áéíóúÁÉÍÓÚñÑ¿¡]/u', $prose) === 1) {
            return true;
        }

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

        $trimmed = trim($comment);

        if (! str_starts_with($trimmed, '/*') && ! str_starts_with($trimmed, '{{--')) {
            return count($lines) > self::MAX_INLINE_LINES;
        }

        $prose = [];

        foreach ($lines as $line) {
            // The `*` and the ONE space after it go; whatever indentation is
            // left marks a code sample apart from prose.
            $line = trim($line);
            $line = str_starts_with($line, '{{--') ? substr($line, 4) : $line;
            $line = str_ends_with($line, '--}}') ? substr($line, 0, -4) : $line;
            $line = rtrim($line, '*/');
            $line = ltrim($line, '*');
            $line = rtrim($line);

            if (str_starts_with($line, ' ')) {
                $line = substr($line, 1);
                $isSample = str_starts_with($line, ' ');
            } else {
                $isSample = false;
            }

            if (str_starts_with(ltrim($line), '@')) {
                break;
            }

            if ($line !== '' && ! $isSample) {
                $prose[] = $line;
            }
        }

        return count($prose) > self::MAX_DOCBLOCK_PROSE;
    }
}
