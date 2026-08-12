<?php

declare(strict_types=1);

use App\Services\Knowledge\KnowledgeChunker;

test('returns no chunks for blank content', function (): void {
    expect(app(KnowledgeChunker::class)->chunk("   \n\t "))->toBe([]);
});

test('keeps short content in a single chunk', function (): void {
    $chunks = app(KnowledgeChunker::class)->chunk('Texto corto de prueba.');

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]['content'])->toBe('Texto corto de prueba.')
        ->and($chunks[0]['token_count'])->toBeGreaterThan(0);
});

test('splits long content into multiple chunks without cutting words', function (): void {
    config(['rag.chunk.max_chars' => 50, 'rag.chunk.overlap_chars' => 10]);

    $content = trim(str_repeat('palabra ', 100)); // 799 chars, one word repeated

    $chunks = app(KnowledgeChunker::class)->chunk($content);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        // never exceeds the max and never splits a word ("palabr" alone must not appear)
        expect(mb_strlen($chunk['content']))->toBeLessThanOrEqual(50)
            ->and(trim($chunk['content']))->toMatch('/^palabra( palabra)*$/');
    }
});
