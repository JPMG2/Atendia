<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * A chunk retrieved by similarity, ready to build the assistant's context.
 * `distance` is the cosine distance (0 = identical); the matching similarity
 * is 1 - distance.
 */
final readonly class RetrievedChunkDto
{
    public function __construct(
        public int $documentId,
        public string $documentTitle,
        public string $content,
        public float $distance,
    ) {}

    public function similarity(): float
    {
        return 1.0 - $this->distance;
    }
}
