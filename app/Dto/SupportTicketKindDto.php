<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\SupportTicketKind;

class SupportTicketKindDto implements FormData
{
    public function __construct(
        public string $code = '',
        public string $name = '',
        public string $icon = 'message-circle',
        public string $starting_priority = 'normal',
        public bool $runs_clock = true,
        public int $sort_order = 0,
        public bool $is_active = true
    ) {}

    /**
     * Prepare the object for Livewire.
     */
    public function toLivewire()
    {
        return $this->toArray();
    }

    /**
     * Recreate the object from Livewire data.
     */
    public static function fromLivewire($value)
    {
        return self::fromArray(is_array($value) ? $value : []);
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'icon' => $this->icon,
            'starting_priority' => $this->starting_priority,
            'runs_clock' => $this->runs_clock,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        $priority = $data['starting_priority'] ?? 'normal';

        return new self(
            code: (string) ($data['code'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            icon: (string) ($data['icon'] ?? 'message-circle'),
            // An enum case arrives from the model's cast; a string from the form.
            starting_priority: $priority instanceof \BackedEnum ? (string) $priority->value : (string) $priority,
            runs_clock: (bool) ($data['runs_clock'] ?? true),
            sort_order: (int) ($data['sort_order'] ?? 0),
            is_active: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toPayload(): array
    {
        return [
            'code' => SupportTicketKind::normalizeCode($this->code),
            'name' => DtoCast::squish($this->name) ?? '',
            'icon' => trim($this->icon),
            'starting_priority' => $this->starting_priority,
            'runs_clock' => $this->runs_clock,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
