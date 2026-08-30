<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

class KnowledgeChunker
{
    /**
     * Splits text into ~`max_chars` chunks overlapping by `overlap_chars`, always
     * cutting on a word boundary. The overlap keeps the edge between two
     * neighbouring chunks from losing its context.
     *
     * @return list<array{content: string, token_count: int}>
     */
    public function chunk(string $content): array
    {
        $content = trim((string) preg_replace('/\s+/', ' ', $content));

        if ($content === '') {
            return [];
        }

        $maxChars = (int) config('rag.chunk.max_chars');
        $overlapChars = (int) config('rag.chunk.overlap_chars');

        $words = explode(' ', $content);
        $chunks = [];
        $current = '';
        $i = 0;

        while ($i < count($words)) {
            $candidate = $current === '' ? $words[$i] : $current.' '.$words[$i];

            if (mb_strlen($candidate) > $maxChars && $current !== '') {
                $chunks[] = $current;
                $current = $this->tail($current, $overlapChars);

                continue; // retry the same word against the overlapping chunk
            }

            $current = $candidate;
            $i++;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return array_map(
            static fn (string $chunk): array => [
                'content' => $chunk,
                'token_count' => (int) ceil(mb_strlen($chunk) / 4), // aprox. 4 chars ≈ 1 token
            ],
            $chunks,
        );
    }

    /**
     * The tail of the text (~$chars) starting on a word boundary, to seed the
     * next chunk with its overlap.
     */
    private function tail(string $text, int $chars): string
    {
        if ($chars <= 0 || mb_strlen($text) <= $chars) {
            return $chars <= 0 ? '' : $text;
        }

        $tail = mb_substr($text, -$chars);
        $space = mb_strpos($tail, ' ');

        return $space === false ? '' : trim(mb_substr($tail, $space + 1));
    }
}
