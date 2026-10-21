<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * The mobile app names its own install: an id generated on its first run and
 * sent as `X-Device-Id` on every call. Folding it into the input lets the
 * ordinary validator reject a call that arrives without it.
 */
trait ReadsAppDevice
{
    protected function prepareForValidation(): void
    {
        $this->merge(['device_id' => $this->header('X-Device-Id')]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function deviceRules(): array
    {
        return [
            'device_id' => ['required', 'string', 'between:16,64', 'regex:/^[A-Za-z0-9-]+$/'],
            'device_name' => ['sometimes', 'string', 'max:80'],
        ];
    }

    public function deviceId(): string
    {
        return (string) $this->validated('device_id');
    }

    public function deviceName(): string
    {
        return (string) ($this->validated('device_name') ?? 'app');
    }
}
