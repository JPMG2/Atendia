<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

class KnowledgeChunker
{
    /**
     * Parte un texto en fragmentos de ~`max_chars` con `overlap_chars` de solape,
     * cortando en límites de palabra (nunca a mitad de palabra). El solape evita
     * perder contexto en el borde entre fragmentos contiguos.
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

                continue; // reintenta la misma palabra sobre el fragmento con solape
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
     * Devuelve la cola del texto (~$chars caracteres) empezando en un límite de
     * palabra, para sembrar el siguiente fragmento con solape.
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
