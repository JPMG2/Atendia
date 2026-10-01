<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Filesystem\Filesystem;

/**
 * Finds action controls that run nothing: a button styled like every other
 * button that answers a click with silence. The opening tag is read by
 * balancing braces and quotes, never by regex: a directive or a `$model->field`
 * inside the tag holds a `>` that cuts a regex short and hides the handler.
 *
 * Recipe: .ai/guidelines/reglas-de-oro-enforcement.md
 */
final class ControlScanner
{
    /** The tags that look like an offer to act. */
    private const CONTROLS = ['x-ui.button', 'x-ui.icon-button', 'button'];

    /**
     * Anything here makes the control do something — its own handler, a link,
     * a form submit, or the caller's attribute bag passed through.
     *
     * @var list<string>
     */
    private const WIRED = [
        'wire:click', 'wire:submit', 'wire:confirm', 'wire:navigate', 'wire:model',
        'href', 'type="submit"', 'onclick', 'form=',
        'x-on:', '@click', '@mousedown', '@keydown', '$wire.', 'x-model', 'x-data',
        // A component that forwards the bag takes its action from the caller.
        'attributes',
    ];

    /**
     * A `data-` hook is how a plain script reaches a control (`hero.js` binds
     * the landing demo that way). `data-testid` is the one that never acts: the
     * bell's rows carry it beside their wire:click.
     */
    private const SCRIPT_HOOK = '/\bdata-(?!testid)[\w-]+/';

    /**
     * Controls that report a state instead of offering one, with the reason.
     * NEVER grow this list: a silent button is fixed, not registered.
     *
     * @var array<string, string>
     */
    public const ALLOWED = [
        // The "Copiado" echo that swaps in for two seconds. It answers the copy
        // that just happened; the button beside it is the one that acts.
        'resources/views/components/referrals/⚡index.blade.php' => 'state echo beside its acting twin',
    ];

    /**
     * Every Blade view of the project, relative to its root.
     *
     * @return list<string>
     */
    public static function views(): array
    {
        $files = new Filesystem()->allFiles(base_path('resources/views'));

        return collect($files)
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn ($file): string => 'resources/views/'.$file->getRelativePathname())
            ->sort()
            ->values()
            ->all();
    }

    /**
     * The silent controls of one file, as the line each sits on and its tag.
     *
     * @return list<array{line: int, tag: string}>
     */
    public static function silentControlsIn(string $relative, string $source): array
    {
        if (array_key_exists($relative, self::ALLOWED)) {
            return [];
        }

        $pattern = '/<('.implode('|', array_map(
            fn (string $control): string => preg_quote($control, '/'),
            self::CONTROLS,
        )).')\b/';

        if (! preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $silent = [];

        foreach ($matches[0] as [, $offset]) {
            $tag = self::openingTagAt($source, $offset);

            // A tag with no attributes at all is not an offer: a bare <button>
            // submits the form it sits in, which is exactly what it should do.
            if (self::attributesOf($tag) === '' || self::isWired($tag)) {
                continue;
            }

            $silent[] = [
                'line' => substr_count(substr($source, 0, $offset), "\n") + 1,
                'tag' => trim((string) preg_replace('/\s+/', ' ', mb_substr($tag, 0, 160))),
            ];
        }

        return $silent;
    }

    private static function isWired(string $tag): bool
    {
        foreach (self::WIRED as $wired) {
            if (str_contains($tag, $wired)) {
                return true;
            }
        }

        return preg_match(self::SCRIPT_HOOK, $tag) === 1;
    }

    /** The tag without its name and closing bracket: what is left is attributes. */
    private static function attributesOf(string $tag): string
    {
        return trim((string) preg_replace('/^<[\w.\-]+|\/?>$/', '', $tag));
    }

    /**
     * The opening tag that starts at `$offset`, up to the `>` that closes it
     * for real — not one quoted in an attribute or nested in a directive.
     */
    private static function openingTagAt(string $source, int $offset): string
    {
        $depth = 0;
        $quote = null;
        $length = strlen($source);

        for ($i = $offset; $i < $length; $i++) {
            $character = $source[$i];

            if ($quote !== null) {
                $quote = $character === $quote ? null : $quote;

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif (in_array($character, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($character, [')', ']', '}'], true)) {
                $depth--;
            } elseif ($character === '>' && $depth <= 0) {
                return substr($source, $offset, $i - $offset + 1);
            }
        }

        return substr($source, $offset, 400);
    }
}
