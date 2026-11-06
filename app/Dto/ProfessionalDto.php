<?php

declare(strict_types=1);

namespace App\Dto;

use App\Classes\Main\OpeningHours;
use App\Interfaces\Catalog\FormData;

/**
 * A professional as its sheet edits it. The week keeps the shape the hours
 * editor speaks (all seven days, one shift each); `toPayload()` folds it to
 * what is stored: nothing at all when they follow the business's hours.
 */
class ProfessionalDto implements FormData
{
    public function __construct(
        public string $name = '',
        public bool $is_active = true,
        public bool $uses_business_hours = true,
        /** @var array<int, array{open: bool, opens_at: string, closes_at: string}> */
        public array $week = [],
        /** Empty inherits the business's; kept as typed until validated. */
        public ?string $appointment_capacity = null,
        public ?string $appointments_per_day = null,
        /** @var array<int, array{attends: bool, duration_minutes: ?string, price_type: ?string, price: ?string, deposit: ?string}> keyed by service id */
        public array $services = [],
    ) {
        $this->week = OpeningHours::formState($week === [] ? self::officeWeek() : $week);
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
            'is_active' => $this->is_active,
            'uses_business_hours' => $this->uses_business_hours,
            'week' => $this->week,
            'appointment_capacity' => $this->appointment_capacity,
            'appointments_per_day' => $this->appointments_per_day,
            'services' => $this->services,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            is_active: (bool) ($data['is_active'] ?? true),
            uses_business_hours: (bool) ($data['uses_business_hours'] ?? true),
            week: (array) ($data['week'] ?? []),
            appointment_capacity: DtoCast::toNullableString($data['appointment_capacity'] ?? null),
            appointments_per_day: DtoCast::toNullableString($data['appointments_per_day'] ?? null),
            services: (array) ($data['services'] ?? []),
        );
    }

    /**
     * Only the services they attend, each with what they change; an override
     * left empty stays null so the service's own value keeps applying.
     */
    public function toPayload(): array
    {
        $services = [];

        foreach ($this->services as $serviceId => $row) {
            if (! (bool) ($row['attends'] ?? false)) {
                continue;
            }

            $priceType = DtoCast::toNullableString($row['price_type'] ?? null);

            $services[(int) $serviceId] = [
                'duration_minutes' => DtoCast::toNullableString($row['duration_minutes'] ?? null),
                'price_type' => $priceType,
                // A price type that says "free" or "to talk" has no amount: keeping one would be quoted.
                'price' => in_array($priceType, ['free', 'talk'], true) ? null : DtoCast::toNullableString($row['price'] ?? null),
                'deposit' => DtoCast::toNullableString($row['deposit'] ?? null),
            ];
        }

        return [
            'name' => DtoCast::squish($this->name) ?? '',
            'is_active' => $this->is_active,
            'uses_business_hours' => $this->uses_business_hours,
            'week' => $this->week,
            'appointment_capacity' => DtoCast::toNullableString($this->appointment_capacity),
            'appointments_per_day' => DtoCast::toNullableString($this->appointments_per_day),
            'services' => $services,
        ];
    }

    /** Monday to Friday 09:00 to 18:00, the usual office week, until they say otherwise. */
    private static function officeWeek(): array
    {
        $week = [];

        foreach (OpeningHours::DAY_ORDER as $day) {
            $week[$day] = ['open' => $day >= 1 && $day <= 5, 'opens_at' => '09:00', 'closes_at' => '18:00'];
        }

        return $week;
    }
}
