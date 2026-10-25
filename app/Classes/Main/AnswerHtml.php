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
    public static function of(string $text): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $html = e($text);

        $html = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', function (array $link) use ($host): string {
            $url = html_entity_decode($link[2]);
            $parts = parse_url($url);

            // PHP and a browser read a backslash differently ("https://evil.test\@ours.com" is evil.test to a
            // browser), and credentials in the address are never ours: both mean "not our link".
            $ours = ($parts['host'] ?? null) === $host
                && ! isset($parts['user'])
                && ! str_contains($url, '\\');

            return $ours
                ? '<a href="'.e($url).'" wire:navigate class="ask-link">'.$link[1].'</a>'
                : $link[1];
        }, $html) ?? $html;

        $html = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $html) ?? $html;

        return nl2br($html);
    }
}
