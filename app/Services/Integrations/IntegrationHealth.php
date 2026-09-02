<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Dto\IntegrationStatusDto;
use App\Enums\IntegrationState;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Probes every external piece AtendIa consumes and says how each one is
 * doing: on and answering, configured but failing, or simply not configured.
 *
 * Every probe is bounded by a short timeout: this feeds a screen, and one
 * dead integration must not hang the seven others behind it.
 */
class IntegrationHealth
{
    private const TIMEOUT_SECONDS = 3;

    /**
     * The TCP probe is injectable because tests cannot fake a raw socket the
     * way Http::fake() covers HTTP.
     *
     * @param  (Closure(string, int): bool)|null  $tcpProbe
     */
    public function __construct(
        private readonly ?Closure $tcpProbe = null,
    ) {}

    /**
     * Every integration the platform consumes, in display order.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return ['database', 'redis', 'mail', 'whatsapp', 'n8n', 'chatwoot', 'openai', 'reverb'];
    }

    /**
     * @return Collection<int, IntegrationStatusDto>
     */
    public function statuses(): Collection
    {
        return collect($this->keys())->map(fn (string $key): IntegrationStatusDto => $this->check($key));
    }

    public function check(string $key): IntegrationStatusDto
    {
        return match ($key) {
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'mail' => $this->checkMail(),
            'whatsapp' => $this->checkEvolution(),
            'n8n' => $this->checkN8n(),
            'chatwoot' => $this->checkChatwoot(),
            'openai' => $this->checkOpenAi(),
            'reverb' => $this->checkReverb(),
            default => new IntegrationStatusDto($key, IntegrationState::Off, detail: __('integrations.detail.unknown')),
        };
    }

    private function checkDatabase(): IntegrationStatusDto
    {
        return $this->timed('database', function (): array {
            DB::select('select 1');

            return [__('integrations.detail.database', ['name' => (string) config('database.default')]), null];
        }, __('integrations.hint.database'));
    }

    private function checkRedis(): IntegrationStatusDto
    {
        return $this->timed('redis', function (): array {
            Redis::connection()->ping();

            return [__('integrations.detail.answering'), null];
        }, __('integrations.hint.redis'));
    }

    private function checkMail(): IntegrationStatusDto
    {
        if (config('mail.default') !== 'smtp') {
            return new IntegrationStatusDto('mail', IntegrationState::Off, detail: __('integrations.detail.mail_off', ['mailer' => (string) config('mail.default')]));
        }

        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');

        return $this->timed('mail', function () use ($host, $port): array {
            $this->probeTcp($host, $port);

            return [__('integrations.detail.mail', ['host' => $host, 'port' => $port]), null];
        }, __('integrations.hint.mail'));
    }

    private function checkEvolution(): IntegrationStatusDto
    {
        $url = (string) config('services.evolution.url');
        $key = (string) config('services.evolution.key');

        if ($url === '' || $key === '') {
            return new IntegrationStatusDto('whatsapp', IntegrationState::Off, detail: __('integrations.detail.not_configured'));
        }

        return $this->timed('whatsapp', function () use ($url, $key): array {
            $response = Http::connectTimeout(self::TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['apikey' => $key])
                ->get($url)
                ->throw();

            $version = (string) $response->json('version', '');

            return [$version === ''
                ? __('integrations.detail.answering')
                : __('integrations.detail.version', ['version' => $version]), null];
        }, __('integrations.hint.whatsapp'));
    }

    private function checkN8n(): IntegrationStatusDto
    {
        $api = (string) config('services.n8n.api_url');

        if ($api === '') {
            return new IntegrationStatusDto('n8n', IntegrationState::Off, detail: __('integrations.detail.not_configured'));
        }

        // The health endpoint lives at the ROOT, not under /api/v1: the base
        // is rebuilt from the configured URL instead of asking for one more.
        $parts = parse_url($api);
        $base = ($parts['scheme'] ?? 'http').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        return $this->timed('n8n', function () use ($base): array {
            Http::connectTimeout(self::TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->get($base.'/healthz')
                ->throw();

            return [__('integrations.detail.answering'), null];
        }, __('integrations.hint.n8n'));
    }

    private function checkChatwoot(): IntegrationStatusDto
    {
        $url = (string) config('services.chatwoot.url');

        if ($url === '') {
            return new IntegrationStatusDto('chatwoot', IntegrationState::Off, detail: __('integrations.detail.not_configured'));
        }

        return $this->timed('chatwoot', function () use ($url): array {
            $response = Http::connectTimeout(self::TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->get(rtrim($url, '/').'/api')
                ->throw();

            $version = (string) $response->json('version', '');

            return [$version === ''
                ? __('integrations.detail.answering')
                : __('integrations.detail.version', ['version' => $version]), null];
        }, __('integrations.hint.chatwoot'));
    }

    private function checkOpenAi(): IntegrationStatusDto
    {
        $key = (string) config('ai.providers.openai.key');

        if ($key === '') {
            return new IntegrationStatusDto('openai', IntegrationState::Off, detail: __('integrations.detail.not_configured'));
        }

        $url = rtrim((string) config('ai.providers.openai.url'), '/');

        return $this->timed('openai', function () use ($url, $key): array {
            Http::connectTimeout(self::TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->withToken($key)
                ->get($url.'/models')
                ->throw();

            return [__('integrations.detail.answering'), null];
        }, __('integrations.hint.openai'));
    }

    private function checkReverb(): IntegrationStatusDto
    {
        $port = (int) config('reverb.servers.reverb.port');

        return $this->timed('reverb', function () use ($port): array {
            $this->probeTcp('127.0.0.1', $port);

            return [__('integrations.detail.reverb', ['port' => $port]), null];
        }, __('integrations.hint.reverb'));
    }

    /**
     * Runs one probe, measuring it. Whatever it throws becomes a Failing
     * status carrying the hint — a dead service is a result here, not an
     * exception for the screen to crash on.
     *
     * @param  Closure(): array{string|null, mixed}  $probe
     */
    private function timed(string $key, Closure $probe, string $hint): IntegrationStatusDto
    {
        $start = hrtime(true);

        try {
            [$detail] = $probe();

            return new IntegrationStatusDto(
                $key,
                IntegrationState::Connected,
                latencyMs: (int) round((hrtime(true) - $start) / 1_000_000),
                detail: $detail,
            );
        } catch (Throwable $failure) {
            return new IntegrationStatusDto(
                $key,
                IntegrationState::Failing,
                detail: $failure->getMessage(),
                hint: $hint,
            );
        }
    }

    /** @throws \RuntimeException when the port does not answer */
    private function probeTcp(string $host, int $port): void
    {
        $probe = $this->tcpProbe ?? static function (string $host, int $port): bool {
            $socket = @fsockopen($host, $port, $errorCode, $errorMessage, self::TIMEOUT_SECONDS);

            if ($socket === false) {
                return false;
            }

            fclose($socket);

            return true;
        };

        if (! $probe($host, $port)) {
            throw new \RuntimeException(__('integrations.detail.port_closed', ['host' => $host, 'port' => $port]));
        }
    }
}
