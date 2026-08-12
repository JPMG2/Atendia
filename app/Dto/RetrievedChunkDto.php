<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Un fragmento recuperado por similitud, listo para armar el contexto del
 * asistente. `distance` es la distancia coseno (0 = idéntico); la similitud
 * equivalente es 1 - distance.
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
