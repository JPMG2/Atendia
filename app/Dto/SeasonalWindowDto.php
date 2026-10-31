<?php

declare(strict_types=1);

namespace App\Dto;

use App\Interfaces\Catalog\FormData;
use App\Models\SeasonalWindow;
use Carbon\CarbonImmutable;

class SeasonalWindowDto implements FormData
{
    /**
     * `range` is what the datepicker writes ("Y-m-d..Y-m-d") and the only date
     * the form binds: the table keeps two ends, the picker speaks one field.
     */
    public function __construct(
        public string $name = '',
        public ?string $range = null,
        public int $priority = 0,
        public ?string $notice = null,
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
            'name' => $this->name,
            'range' => $this->range,
            'priority' => $this->priority,
            'notice' => $this->notice,
            'is_active' => $this->is_active,
        ];
    }

    public static function fromArray(array $data): self
    {
        $from = self::toIsoDate($data['starts_at'] ?? null);
        $to = self::toIsoDate($data['ends_at'] ?? null);

        return new self(
            name: $data['name'] ?? '',
            range: $data['range'] ?? ($from === null ? null : $from.'..'.($to ?? $from)),
            priority: (int) ($data['priority'] ?? 0),
            notice: DtoCast::squish($data['notice'] ?? null),
            is_active: $data['is_active'] ?? true,
        );
    }

    public function toPayload(): array
    {
        [$from, $to] = $this->ends();

        return [
            'name' => SeasonalWindow::normalizeName($this->name),
            'starts_at' => $from,
            'ends_at' => $to,
            'priority' => $this->priority,
            'notice' => DtoCast::squish($this->notice),
            'is_active' => $this->is_active,
        ];
    }

    /**
     * A range closed on one date is a season of one day — Flatpickr hands back
     * a single value for it, and taking that as "no end" would leave a season
     * that never stops. An end left blank beside a typed one ("..2026-12-31",
     * from the phone's two boxes) stays blank, so its rule names it.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function ends(): array
    {
        $range = (string) $this->range;

        if (trim($range) === '') {
            return [null, null];
        }

        if (! str_contains($range, '..')) {
            return [$range, $range];
        }

        [$from, $to] = explode('..', $range, 2);

        return [$from === '' ? null : $from, $to === '' ? null : $to];
    }

    /**
     * The picker speaks `Y-m-d`; a loaded row arrives as the cast's full
     * timestamp, which the hidden input would hand back to Flatpickr unparsed.
     */
    private static function toIsoDate(mixed $value): ?string
    {
        $date = DtoCast::toNullableString(is_string($value) ? $value : (string) $value);

        return $date === null ? null : CarbonImmutable::parse($date)->toDateString();
    }
}
