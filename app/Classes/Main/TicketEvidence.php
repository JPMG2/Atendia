<?php

declare(strict_types=1);

namespace App\Classes\Main;

use Illuminate\Support\Str;

/**
 * What the widget captured when a report was sent, read the way a person
 * reads it: "Chrome 141 · Windows", "390×844 · teléfono". The raw context is
 * evidence for a machine; this is what the one answering needs in front of her.
 */
final class TicketEvidence
{
    /** @param array<string, mixed>|null $context */
    public function __construct(private readonly ?array $context) {}

    /**
     * The captured facts, labelled, in the order they help: where, on what,
     * and who.
     *
     * @var list<array{label: string, value: string, mono: bool}>
     */
    public array $rows {
        get => array_values(array_filter([
            $this->row('url', (string) ($this->context['url'] ?? ''), mono: true),
            $this->row('window', $this->window),
            $this->row('browser', $this->browser),
            $this->row('plan', ucfirst((string) ($this->context['plan'] ?? ''))),
            $this->row('locale', (string) ($this->context['locale'] ?? '')),
        ]));
    }

    /**
     * The errors the screen logged before the report, one per line. They are
     * the closest thing to a stack trace a business ever sends.
     *
     * @var list<string>
     */
    public array $errors {
        get => array_values(array_filter(
            array_map('trim', explode(' | ', (string) ($this->context['errors'] ?? ''))),
            fn (string $line): bool => $line !== '',
        ));
    }

    /** Whether there is anything to show: an old report may carry none of it. */
    public bool $isEmpty {
        get => $this->rows === [] && $this->errors === [];
    }

    /** "Chrome 141 · Windows", or null when the agent says nothing usable. */
    public ?string $browser {
        get {
            $agent = (string) ($this->context['agent'] ?? '');

            if ($agent === '') {
                return null;
            }

            // Order matters: Edge and Opera both say Chrome, and Chrome says Safari.
            $name = match (true) {
                str_contains($agent, 'Edg/') => ['Edge', 'Edg/'],
                str_contains($agent, 'OPR/') => ['Opera', 'OPR/'],
                str_contains($agent, 'Firefox/') => ['Firefox', 'Firefox/'],
                str_contains($agent, 'Chrome/') => ['Chrome', 'Chrome/'],
                str_contains($agent, 'Safari/') => ['Safari', 'Version/'],
                default => null,
            };

            $system = match (true) {
                str_contains($agent, 'Android') => 'Android',
                str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
                str_contains($agent, 'Windows') => 'Windows',
                str_contains($agent, 'Mac OS X') => 'macOS',
                str_contains($agent, 'Linux') => 'Linux',
                default => null,
            };

            $version = $name === null ? null : Str::of($agent)->after($name[1])->before('.')->before(' ')->toString();

            return collect([$name === null ? null : trim("{$name[0]} {$version}"), $system])->filter()->implode(' · ') ?: null;
        }
    }

    /** "390×844 · teléfono": the size says more than the number when it is a phone. */
    public ?string $window {
        get {
            if (! preg_match('/^(\d+)x(\d+)$/', (string) ($this->context['viewport'] ?? ''), $size)) {
                return null;
            }

            $device = match (true) {
                (int) $size[1] < 600 => 'phone',
                (int) $size[1] < 1000 => 'tablet',
                default => 'desktop',
            };

            return "{$size[1]}×{$size[2]} · ".__("support.admin.evidence.devices.{$device}");
        }
    }

    /** @return array{label: string, value: string, mono: bool}|null */
    private function row(string $key, ?string $value, bool $mono = false): ?array
    {
        return $value === null || $value === '' ? null : ['label' => __("support.admin.evidence.{$key}"), 'value' => $value, 'mono' => $mono];
    }
}
