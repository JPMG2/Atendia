<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;

/**
 * A team seat as the "Equipo" sheet edits it: the invitation when it is new,
 * the member's routing when it exists. `business_id` never travels here —
 * a seat is created THROUGH its business.
 */
class TeamMemberDto implements FormData
{
    public function __construct(
        public string $name = '',
        public string $email = '',
        public ?string $whatsapp = null,
        /** @var list<int> */
        public array $department_ids = [],
    ) {}

    public function toLivewire()
    {
        return $this->toArray();
    }

    public static function fromLivewire($value)
    {
        return self::fromArray(is_array($value) ? $value : []);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
            'department_ids' => $this->department_ids,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            whatsapp: DtoCast::toNullableString($data['whatsapp'] ?? null),
            department_ids: array_values(array_map('intval', (array) ($data['department_ids'] ?? []))),
        );
    }

    /** The email lowercased (it is the login), the phone as bare digits. */
    public function toPayload(): array
    {
        $digits = preg_replace('/\D/', '', (string) $this->whatsapp);

        return [
            'name' => DtoCast::squish($this->name) ?? '',
            'email' => mb_strtolower(trim($this->email)),
            'whatsapp' => $digits === '' ? null : $digits,
            'department_ids' => array_values(array_unique($this->department_ids)),
        ];
    }
}
