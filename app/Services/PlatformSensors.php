<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Admin\FindOrphanInstances;
use App\Dto\SensorReadingDto;
use App\Enums\PaymentStatus;
use App\Enums\PlatformEventKind;
use App\Enums\PlatformSensorStatus as Status;
use App\Enums\SubscriptionStatus;
use App\Jobs\PlatformHeartbeat;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\Payment;
use App\Models\PlatformEvent;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Everything the platform watches, one small question each. A probe that
 * cannot answer is itself an answer (red), never an exception that takes the
 * rest of the board down with it.
 */
class PlatformSensors
{
    public function __construct(private readonly CertificateReader $certificates) {}

    /** @return list<SensorReadingDto> */
    public function read(): array
    {
        return [
            $this->guard('queue', 'system', fn (): SensorReadingDto => $this->queue()),
            $this->guard('database', 'system', fn (): SensorReadingDto => $this->database()),
            $this->guard('redis', 'system', fn (): SensorReadingDto => $this->redis()),
            $this->guard('platform_whatsapp', 'system', fn (): SensorReadingDto => $this->platformWhatsApp()),
            $this->guard('disk', 'system', fn (): SensorReadingDto => $this->disk()),
            $this->guard('backup', 'system', fn (): SensorReadingDto => $this->backup()),
            $this->guard('mail', 'system', fn (): SensorReadingDto => $this->mail()),
            $this->guard('certificate', 'system', fn (): SensorReadingDto => $this->certificate()),
            $this->guard('ai', 'system', fn (): SensorReadingDto => $this->ai()),
            $this->guard('whatsapp_links', 'customers', fn (): SensorReadingDto => $this->whatsappLinks()),
            $this->guard('bridge_orphans', 'customers', fn (): SensorReadingDto => $this->bridgeOrphans()),
            $this->guard('signups', 'customers', fn (): SensorReadingDto => $this->signups()),
            $this->guard('receipts', 'money', fn (): SensorReadingDto => $this->receipts()),
            $this->guard('late', 'money', fn (): SensorReadingDto => $this->subscriptions('late', SubscriptionStatus::PastDue, Status::Warn)),
            $this->guard('paused', 'money', fn (): SensorReadingDto => $this->subscriptions('paused', SubscriptionStatus::Paused, Status::Crit)),
            $this->guard('suspended', 'compliance', fn (): SensorReadingDto => $this->suspended()),
            $this->guard('appeals', 'compliance', fn (): SensorReadingDto => $this->appeals()),
            $this->guard('content', 'compliance', fn (): SensorReadingDto => $this->content()),
            $this->guard('heartbeat', 'external', fn (): SensorReadingDto => $this->externalHeartbeat()),
        ];
    }

    private function guard(string $key, string $group, Closure $probe): SensorReadingDto
    {
        try {
            return $probe();
        } catch (Throwable $e) {
            return new SensorReadingDto($key, $group, Status::Crit, null, __('platform_events.detail.no_answer', ['reason' => mb_substr($e->getMessage(), 0, 100)]));
        }
    }

    private function ago(CarbonInterface $at): string
    {
        return now()->locale('es')->diffForHumans($at, CarbonInterface::DIFF_ABSOLUTE, true);
    }

    private function queue(): SensorReadingDto
    {
        $beat = Cache::get(PlatformHeartbeat::CACHE_KEY);
        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();

        if ($beat === null) {
            return new SensorReadingDto('queue', 'system', Status::Unknown, null, __('platform_events.detail.queue_none'));
        }

        $at = Carbon::createFromTimestamp((int) $beat);
        $minutes = (int) $at->diffInMinutes(now());
        $status = match (true) {
            $minutes <= (int) config('atendia.health.queue_warn_minutes') => Status::Ok,
            $minutes <= (int) config('atendia.health.queue_crit_minutes') => Status::Warn,
            default => Status::Crit,
        };

        if ($status === Status::Ok && $failed > 0) {
            return new SensorReadingDto('queue', 'system', Status::Warn, $failed.' '.__('platform_events.detail.failed_short'), __('platform_events.detail.queue_failed', ['count' => $failed]));
        }

        $detail = $status === Status::Ok
            ? __('platform_events.detail.queue_beat', ['age' => $this->ago($at)])
            : __('platform_events.detail.queue_stalled', ['age' => $this->ago($at)]);

        return new SensorReadingDto('queue', 'system', $status, $status === Status::Ok ? 'OK' : $this->ago($at), $detail);
    }

    private function database(): SensorReadingDto
    {
        $start = microtime(true);
        DB::select('select 1');
        $ms = (int) round((microtime(true) - $start) * 1000);

        return new SensorReadingDto('database', 'system', Status::Ok, 'OK', __('platform_events.detail.answers_in', ['ms' => $ms]));
    }

    private function redis(): SensorReadingDto
    {
        $start = microtime(true);
        Redis::connection()->ping();
        $ms = (int) round((microtime(true) - $start) * 1000);

        return new SensorReadingDto('redis', 'system', Status::Ok, 'OK', __('platform_events.detail.answers_in', ['ms' => $ms]));
    }

    private function platformWhatsApp(): SensorReadingDto
    {
        $instance = PlatformWhatsApp::instance();

        if ($instance === '') {
            return new SensorReadingDto('platform_whatsapp', 'system', Status::Unknown, null, __('platform_events.detail.no_instance'));
        }

        $state = app(EvolutionApi::class)->connectionState($instance);

        return $state === 'open'
            ? new SensorReadingDto('platform_whatsapp', 'system', Status::Ok, 'open', __('platform_events.detail.instance_open'))
            : new SensorReadingDto('platform_whatsapp', 'system', Status::Crit, $state, __('platform_events.detail.instance_state', ['state' => $state]));
    }

    private function disk(): SensorReadingDto
    {
        $total = (float) disk_total_space(base_path());
        $free = (float) disk_free_space(base_path());
        $used = $total > 0 ? (int) round(($total - $free) / $total * 100) : 0;
        $status = match (true) {
            $used >= (int) config('atendia.health.disk_crit_percent') => Status::Crit,
            $used >= (int) config('atendia.health.disk_warn_percent') => Status::Warn,
            default => Status::Ok,
        };

        return new SensorReadingDto('disk', 'system', $status, $used.' %', __('platform_events.detail.disk_free', [
            'free' => number_format($free / 1_073_741_824, 0),
            'total' => number_format($total / 1_073_741_824, 0),
        ]));
    }

    /**
     * The nightly script drops a small status file into the project's storage,
     * which this container shares: the host's own cron cannot be read from
     * inside, but what it leaves behind can.
     */
    private function backup(): SensorReadingDto
    {
        $path = storage_path('app/backup-status.json');

        if (! is_file($path)) {
            return new SensorReadingDto('backup', 'system', Status::Unknown, null, __('platform_events.detail.backup_none'));
        }

        $status = json_decode((string) file_get_contents($path), true);
        $at = isset($status['at']) ? Carbon::parse((string) $status['at']) : null;

        if ($at === null) {
            return new SensorReadingDto('backup', 'system', Status::Unknown, null, __('platform_events.detail.backup_none'));
        }

        $hours = (int) $at->diffInHours(now());
        $state = match (true) {
            $hours <= (int) config('atendia.health.backup_warn_hours') => Status::Ok,
            $hours <= (int) config('atendia.health.backup_crit_hours') => Status::Warn,
            default => Status::Crit,
        };

        return new SensorReadingDto('backup', 'system', $state, $state === Status::Ok ? 'OK' : $this->ago($at), __('platform_events.detail.backup_last', [
            'age' => $this->ago($at),
            'size' => number_format(((int) ($status['bytes'] ?? 0)) / 1_048_576, 1),
        ]));
    }

    private function mail(): SensorReadingDto
    {
        $mailer = (string) config('mail.default');
        $host = (string) config("mail.mailers.{$mailer}.host", '');

        if (in_array($mailer, ['log', 'array'], true) || in_array($host, ['mailpit', 'localhost', '127.0.0.1'], true)) {
            return new SensorReadingDto('mail', 'system', Status::Warn, __('platform_events.detail.mail_test'), __('platform_events.detail.mail_sink'));
        }

        return new SensorReadingDto('mail', 'system', Status::Ok, 'OK', __('platform_events.detail.mail_via', ['host' => $host]));
    }

    private function certificate(): SensorReadingDto
    {
        $url = parse_url((string) config('app.url'));
        $host = (string) ($url['host'] ?? '');

        // Without HTTPS of its own (local development) there is no certificate to watch.
        if (($url['scheme'] ?? '') !== 'https' || $host === '') {
            return new SensorReadingDto('certificate', 'system', Status::Unknown, null, __('platform_events.detail.certificate_none'));
        }

        $expires = $this->certificates->expiresAt((string) config('atendia.health.certificate_origin') ?: $host, $host);
        $days = (int) floor(now()->diffInDays($expires, false));

        $status = match (true) {
            $days <= (int) config('atendia.health.certificate_crit_days') => Status::Crit,
            $days <= (int) config('atendia.health.certificate_warn_days') => Status::Warn,
            default => Status::Ok,
        };

        if ($days < 0) {
            return new SensorReadingDto('certificate', 'system', $status, __('platform_events.detail.certificate_expired_short'), __('platform_events.detail.certificate_expired', [
                'date' => $expires->format('d/m/Y'),
            ]));
        }

        return new SensorReadingDto('certificate', 'system', $status, $days.' d', __('platform_events.detail.certificate_expires', [
            'date' => $expires->format('d/m/Y'),
            'days' => $days,
        ]));
    }

    private function ai(): SensorReadingDto
    {
        // The text agents run on the app key and fall to the other one only when
        // it fails; a call that went through the second key is a fall.
        $fell = AiUsage::query()
            ->where('created_at', '>=', now()->subHour())
            ->where('connection_key', 'openai')
            ->whereNotIn('kind', [AiUsage::EMBEDDINGS, AiUsage::TRANSCRIPTION])
            ->count();

        return $fell > 0
            ? new SensorReadingDto('ai', 'system', Status::Warn, (string) $fell, __('platform_events.detail.ai_fell', ['count' => $fell]))
            : new SensorReadingDto('ai', 'system', Status::Ok, 'OK', __('platform_events.detail.ai_clear'));
    }

    private function whatsappLinks(): SensorReadingDto
    {
        $linked = Business::query()->where('is_demo', false)->whereNotNull('whatsapp_instance');
        $total = (clone $linked)->count();
        $up = (clone $linked)->whereNotNull('whatsapp_connected_at')->count();
        $down = $total - $up;

        return $down > 0
            ? new SensorReadingDto('whatsapp_links', 'customers', Status::Warn, $up.' / '.$total, __('platform_events.detail.links_down', ['count' => $down]))
            : new SensorReadingDto('whatsapp_links', 'customers', Status::Ok, $up.' / '.$total, __('platform_events.detail.links_ok'));
    }

    /** Instances the bridge keeps running for a business that no longer holds them. */
    private function bridgeOrphans(): SensorReadingDto
    {
        $orphans = app(FindOrphanInstances::class)->handle();

        return $orphans === []
            ? new SensorReadingDto('bridge_orphans', 'customers', Status::Ok, '0', __('platform_events.detail.orphans_none'))
            : new SensorReadingDto('bridge_orphans', 'customers', Status::Warn, (string) count($orphans), __('platform_events.detail.orphans_some', ['names' => implode(', ', $orphans)]));
    }

    private function signups(): SensorReadingDto
    {
        $today = Business::query()->where('is_demo', false)->where('created_at', '>=', now()->startOfDay());
        $count = (clone $today)->count();
        $last = (clone $today)->max('created_at');

        return new SensorReadingDto('signups', 'customers', Status::Ok, (string) $count, $count === 0
            ? __('platform_events.detail.signups_none')
            : __('platform_events.detail.signups_last', ['age' => $this->ago(Carbon::parse((string) $last))]));
    }

    private function receipts(): SensorReadingDto
    {
        $waiting = Payment::query()->where('status', PaymentStatus::Pending->value);
        $count = (clone $waiting)->count();

        if ($count === 0) {
            return new SensorReadingDto('receipts', 'money', Status::Ok, '0', __('platform_events.detail.receipts_none'));
        }

        $oldest = Carbon::parse((string) (clone $waiting)->min('created_at'));
        $status = $oldest->lt(now()->subDays(3)) ? Status::Crit : Status::Warn;

        return new SensorReadingDto('receipts', 'money', $status, (string) $count, __('platform_events.detail.receipts_oldest', ['age' => $this->ago($oldest)]));
    }

    private function subscriptions(string $key, SubscriptionStatus $state, Status $whenAny): SensorReadingDto
    {
        $count = Subscription::query()
            ->where('status', $state->value)
            ->whereIn('business_id', Business::query()->where('is_demo', false)->select('id'))
            ->count();

        return $count === 0
            ? new SensorReadingDto($key, 'money', Status::Ok, '0', __("platform_events.detail.{$key}_none"))
            : new SensorReadingDto($key, 'money', $whenAny, (string) $count, __("platform_events.detail.{$key}_some"));
    }

    private function suspended(): SensorReadingDto
    {
        $count = Business::query()->where('is_demo', false)->whereNotNull('suspended_at')->count();

        return $count === 0
            ? new SensorReadingDto('suspended', 'compliance', Status::Ok, '0', __('platform_events.detail.suspended_none'))
            : new SensorReadingDto('suspended', 'compliance', Status::Warn, (string) $count, __('platform_events.detail.suspended_some'));
    }

    private function appeals(): SensorReadingDto
    {
        $count = Business::query()->where('is_demo', false)->whereNotNull('suspended_at')->whereNotNull('appeal_message')->count();

        return $count === 0
            ? new SensorReadingDto('appeals', 'compliance', Status::Ok, '0', __('platform_events.detail.appeals_none'))
            : new SensorReadingDto('appeals', 'compliance', Status::Warn, (string) $count, __('platform_events.detail.appeals_some'));
    }

    private function content(): SensorReadingDto
    {
        $count = PlatformEvent::query()->active()->where('kind', PlatformEventKind::ContentToReview->value)->count();

        return $count === 0
            ? new SensorReadingDto('content', 'compliance', Status::Ok, '0', __('platform_events.detail.content_none'))
            : new SensorReadingDto('content', 'compliance', Status::Warn, (string) $count, __('platform_events.detail.content_some'));
    }

    /**
     * A server that is down cannot say so: this pings an outside service
     * every sweep, and THAT service raises the alarm when the pings stop.
     */
    private function externalHeartbeat(): SensorReadingDto
    {
        $url = (string) config('atendia.health.heartbeat_url');

        if ($url === '') {
            return new SensorReadingDto('heartbeat', 'external', Status::Unknown, null, __('platform_events.detail.heartbeat_unset'));
        }

        // The ping is a dead-man's switch: it must stop when the database does,
        // or the outside monitor keeps seeing a pulse from a platform that is down.
        DB::select('select 1');

        if (! Http::timeout(5)->get($url)->successful()) {
            return new SensorReadingDto('heartbeat', 'external', Status::Crit, null, __('platform_events.detail.heartbeat_failed'));
        }

        Cache::put('platform.heartbeat.external', now()->getTimestamp(), now()->addDay());

        return new SensorReadingDto('heartbeat', 'external', Status::Ok, 'OK', __('platform_events.detail.heartbeat_sent'));
    }
}
