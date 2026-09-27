<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;

/**
 * A department as its sheet edits it. The week is always seven rows so the
 * screen has a fixed shape; `toPayload()` folds it back to the stored map,
 * or to null when it follows the business's hours.
 */
class DepartmentDto implements FormData
{
    public function __construct(
        public string $name = '',
        public string $routing_hint = '',
        public bool $uses_business_hours = true,
        /** @var array<int, array{open: bool, opens: string, closes: string}> day 0-6 */
        public array $week = [],
        /** @var list<int> */
        public array $user_ids = [],
    ) {
        $this->week = self::fullWeek($week);
    }

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
            'routing_hint' => $this->routing_hint,
            'uses_business_hours' => $this->uses_business_hours,
            'week' => $this->week,
            'user_ids' => $this->user_ids,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            routing_hint: (string) ($data['routing_hint'] ?? ''),
            uses_business_hours: (bool) ($data['uses_business_hours'] ?? true),
            week: (array) ($data['week'] ?? []),
            user_ids: array_values(array_map('intval', (array) ($data['user_ids'] ?? []))),
        );
    }

    /** From the stored row: `hours` null means the business's schedule. */
    public static function fromStored(?array $hours, string $name, string $routingHint, array $userIds): self
    {
        $week = [];

        foreach (range(0, 6) as $day) {
            $shift = $hours[(string) $day] ?? null;
            $week[$day] = is_array($shift)
                ? ['open' => true, 'opens' => $shift[0], 'closes' => $shift[1]]
                : ['open' => false, 'opens' => '09:00', 'closes' => '18:00'];
        }

        return new self($name, $routingHint, $hours === null, $week, $userIds);
    }

    public function toPayload(): array
    {
        return [
            'name' => DtoCast::squish($this->name) ?? '',
            'routing_hint' => DtoCast::squish($this->routing_hint) ?? '',
            'uses_business_hours' => $this->uses_business_hours,
            'week' => $this->week,
            'user_ids' => array_values(array_unique($this->user_ids)),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $week
     * @return array<int, array{open: bool, opens: string, closes: string}>
     */
    private static function fullWeek(array $week): array
    {
        $full = [];

        foreach (range(0, 6) as $day) {
            $row = (array) ($week[$day] ?? []);
            // Monday to Friday open by default: the usual office week.
            $full[$day] = [
                'open' => (bool) ($row['open'] ?? ($day >= 1 && $day <= 5)),
                'opens' => (string) ($row['opens'] ?? '09:00'),
                'closes' => (string) ($row['closes'] ?? '18:00'),
            ];
        }

        return $full;
    }
}
