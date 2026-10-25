<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Dto\PlatformWhatsAppDto;
use App\Enums\PlatformSensorStatus;
use App\Enums\PlatformWhatsAppState;
use App\Models\PlatformSensor;
use App\Services\EvolutionApi;
use App\Services\PlatformWhatsApp;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Asks the bridge, now, what the platform's own WhatsApp line is doing. A
 * bridge that does not answer is its own state: nothing is claimed about the
 * line, and nothing is cached as if it were true.
 */
class ReadPlatformWhatsApp
{
    public function __construct(private readonly EvolutionApi $evolution) {}

    public function handle(): PlatformWhatsAppDto
    {
        $line = $this->ask();

        PlatformWhatsApp::remember($line->state, $line->checkedAt);

        return $line;
    }

    private function ask(): PlatformWhatsAppDto
    {
        $instance = PlatformWhatsApp::instance();
        $now = CarbonImmutable::now();

        if ($instance === '') {
            return new PlatformWhatsAppDto('', PlatformWhatsAppState::NotConfigured, null, null, null, $now);
        }

        try {
            if (! in_array($instance, $this->evolution->instanceNames(), true)) {
                return new PlatformWhatsAppDto($instance, PlatformWhatsAppState::Missing, null, null, null, $now);
            }

            $state = match ($this->evolution->connectionState($instance)) {
                'open' => PlatformWhatsAppState::Connected,
                'connecting' => PlatformWhatsAppState::Pairing,
                default => PlatformWhatsAppState::Closed,
            };

            $row = $this->evolution->instanceRow($instance);
        } catch (Throwable $e) {
            // Whatever stops the bridge from answering is an answer, like the board's own probes: the screen never breaks.
            if (! $e instanceof ConnectionException && ! $e instanceof RequestException) {
                report($e);
            }

            return new PlatformWhatsAppDto($instance, PlatformWhatsAppState::Unreachable, null, null, null, $now);
        }

        $jid = (string) ($row['ownerJid'] ?? '');

        return new PlatformWhatsAppDto(
            $instance,
            $state,
            $jid === '' ? null : (string) strstr($jid, '@', true),
            $this->since($state),
            $state === PlatformWhatsAppState::Closed ? $this->reason($row) : null,
            $now,
        );
    }

    /**
     * Since when the line has been in this state, from the board's own sensor, and only
     * when the sensor agrees with what the bridge just said: otherwise no date is better than a wrong one.
     */
    private function since(PlatformWhatsAppState $state): ?CarbonImmutable
    {
        $sensor = PlatformSensor::query()->where('sensor_key', 'platform_whatsapp')->first();

        if ($sensor === null || $sensor->changed_at === null || ($sensor->status === PlatformSensorStatus::Ok) !== $state->isConnected()) {
            return null;
        }

        return CarbonImmutable::instance($sensor->changed_at);
    }

    /**
     * Why the session closed, in the bridge's own word ("device_removed"), when it says one.
     *
     * @param  array<string, mixed>  $row
     */
    private function reason(array $row): ?string
    {
        $object = json_decode((string) ($row['disconnectionObject'] ?? ''), true);
        $type = is_array($object) ? ($object['error']['data']['attrs']['type'] ?? null) : null;

        return is_string($type) && $type !== '' ? $type : null;
    }
}
