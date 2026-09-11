<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * A chunk retrieved by similarity, ready to build the assistant's context.
 * `distance` is the cosine distance (0 = identical); the matching similarity
 * is 1 - distance.
 */
final class RetrievedChunkDto
{
    // Readonly sits on each field, not the class: a hooked property may not
    // be readonly, and $similarity below is one.
    public function __construct(
        public readonly int $documentId,
        public readonly string $documentTitle,
        public readonly string $content,
        public readonly float $distance,
    ) {}

    public float $similarity {
        get => 1.0 - $this->distance;
    }
}
