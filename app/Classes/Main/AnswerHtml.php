<?php

declare(strict_types=1);

namespace App\Classes\Main;

/**
 * An assistant's answer made safe to print: the text escaped, with only this
 * app's own links turned live. A link the model wrote to anywhere else stays
 * plain text, so an injected "click here" can never leave the panel.
 */
class AnswerHtml
{
    private const string LINK = '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/';

    public static function of(string $text): string
    {
        $html = e($text);

        $html = preg_replace_callback(self::LINK, function (array $link): string {
            $url = html_entity_decode($link[2]);

            return self::isOurs($url)
                ? '<a href="'.e($url).'" wire:navigate class="ask-link">'.$link[1].'</a>'
                : $link[1];
        }, $html) ?? $html;

        $html = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $html) ?? $html;

        return nl2br($html);
    }

    /**
     * The same answer as pieces, for a screen that draws its own text (the
     * phone): the same links count as ours, the rest stays words. Bold is cut
     * first, as the web resolves it last: a bold run may hold a link.
     *
     * @return list<array{kind: 'text'|'bold'|'link', text: string, url?: string}>
     */
    public static function parts(string $text): array
    {
        $parts = [];
        // A bold label inside a link would otherwise cut the link in two.
        $text = preg_replace_callback(self::LINK, fn (array $link): string => '['.str_replace('**', '', $link[1]).']('.$link[2].')', $text) ?? $text;

        foreach (preg_split('/(\*\*.+?\*\*)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
            $bold = strlen($piece) > 4 && str_starts_with($piece, '**') && str_ends_with($piece, '**');
            $parts = [...$parts, ...self::linkParts($bold ? substr($piece, 2, -2) : $piece, $bold ? 'bold' : 'text')];
        }

        return $parts;
    }

    /**
     * @param  'text'|'bold'  $kind
     * @return list<array{kind: 'text'|'bold'|'link', text: string, url?: string}>
     */
    private static function linkParts(string $text, string $kind): array
    {
        $parts = [];
        $offset = 0;
        preg_match_all(self::LINK, $text, $links, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($links as $link) {
            if ($link[0][1] > $offset) {
                $parts[] = ['kind' => $kind, 'text' => substr($text, $offset, $link[0][1] - $offset)];
            }

            $parts[] = self::isOurs($link[2][0])
                ? ['kind' => 'link', 'text' => $link[1][0], 'url' => $link[2][0]]
                : ['kind' => $kind, 'text' => $link[1][0]];
            $offset = $link[0][1] + strlen($link[0][0]);
        }

        return $offset < strlen($text) ? [...$parts, ['kind' => $kind, 'text' => substr($text, $offset)]] : $parts;
    }

    /**
     * PHP and a browser read a backslash differently ("https://evil.test\@ours.com"
     * is evil.test to a browser), and credentials in the address are never ours:
     * both mean "not our link".
     */
    private static function isOurs(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['host'] ?? null) === parse_url((string) config('app.url'), PHP_URL_HOST)
            && ! isset($parts['user'])
            && ! str_contains($url, '\\');
    }
}
