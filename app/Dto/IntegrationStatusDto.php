<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\IntegrationState;

/**
 * One integration's health, ready for the screen. `detail` says what WAS
 * reached (a version, a latency companion); `hint` only exists while failing
 * and says where to look, in the client's words.
 */
final readonly class IntegrationStatusDto
{
    public function __construct(
        public string $key,
        public IntegrationState $state,
        public ?int $latencyMs = null,
        public ?string $detail = null,
        public ?string $hint = null,
    ) {}

    /**
     * @return array{key: string, state: string, latency_ms: int|null, detail: string|null, hint: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'state' => $this->state->value,
            'latency_ms' => $this->latencyMs,
            'detail' => $this->detail,
            'hint' => $this->hint,
        ];
    }
}
