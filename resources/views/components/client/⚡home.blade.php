<?php

use App\Classes\Main\Client;
use App\Classes\Main\Plan;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Client home. Two states DERIVED from real data (the mock switch died with
 * the audit, 2026-09-20): a fresh client meets the setup guide — the
 * checklist IS the dashboard, KPIs at zero would only depress — and the
 * first real thread earns the day-to-day view.
 */
new class extends Component
{
    /**
     * The real setup trail. The account step arrives done on purpose:
     * endowed progress makes people finish what did not start from zero.
     * "Try" counts as done once the assistant answered someone for real.
     *
     * @return array<string, bool>
     */
    #[Computed]
    public function steps(): array
    {
        $user = Auth::user();
        $business = $user?->business;
        $offer = $business?->offerCounts();
        $strength = $user === null ? null : Client::for($user)->profileStrength;

        return [
            'account' => true,
            'business' => $strength !== null && $strength['done'] === $strength['total'],
            'catalog' => (($offer['my-services'] ?? 0) + ($offer['my-products'] ?? 0)) > 0,
            'try' => $this->isActive,
            'whatsapp' => $business?->isConnected() ?? false,
        ];
    }

    /** The day-to-day view is earned by the first real thread. */
    #[Computed]
    public function isActive(): bool
    {
        return Auth::user()?->business?->conversations()->exists() ?? false;
    }

    /** @return array{conversations: int, new_contacts: int, questions: int, audio_minutes: int}|null */
    #[Computed]
    public function kpis(): ?array
    {
        $user = Auth::user();

        return $user === null ? null : Client::for($user)->statistics?->monthKpis();
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\Conversation> */
    #[Computed]
    public function recent()
    {
        $user = Auth::user();

        return ($user === null ? null : Client::for($user)->inbox?->threads->take(3)) ?? collect();
    }

    /**
     * The plan usage strip, REAL data amid the mock-up: Vercel keeps this
     * meter always in sight and it upsells by itself. Null hides it.
     *
     * @return array{used: int, cap: int, percent: int, state: string}|null
     */
    #[Computed]
    public function usage(): ?array
    {
        $business = auth()->user()?->business;

        if ($business === null) {
            return null;
        }

        $used = $business->conversationsThisMonth();
        $cap = $business->plan()->conversationsPerMonth;

        return [
            'used' => $used,
            'cap' => $cap,
            'percent' => min(100, (int) round($used / max(1, $cap) * 100)),
            'state' => Plan::usageState($used, $cap),
        ];
    }
};
?>

<div>
    @php
        $steps = $this->steps;
        $done = count(array_filter($steps));
        $total = count($steps);
        $percent = (int) round($done / max(1, $total) * 100);
    @endphp

    <div class="page-head">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="page-head-title">{{ __('client.home.greeting', ['name' => auth()->user()?->name]) }}</h1>
                {{-- The "conectar después" promise, kept in sight: the pill
                dies by itself the moment the number connects. --}}
                @if (($business = auth()->user()?->business) && ! $business->isConnected())
                    <a
                        href="{{ route('my-business.contacto') }}"
                        wire:navigate
                        class="status-tag is-warning"
                    >
                        <span class="dot"></span>
                        {{ $business->name }} · {{ __('client.home.disconnected') }}
                    </a>
                @endif
            </div>
            <p class="page-head-sub">
                {{ $this->isActive ? __('client.home.sub_active') : __('client.home.sub_new') }}
            </p>
        </div>
    </div>

    @if ($this->usage !== null)
        <a href="{{ route('my-plan') }}" wire:navigate class="home-usage">
            <x-ui.usage-meter
                :label="__('plan.meters.conversations')"
                :text="__('plan.meters.conversations_of', ['used' => number_format($this->usage['used'], 0, ',', '.'), 'cap' => number_format($this->usage['cap'], 0, ',', '.')])"
                :percent="$this->usage['percent']"
                :state="$this->usage['state']"
            />
        </a>
    @endif

    @if (! $this->isActive)
        <x-ui.card class="setup-card">
            <div class="setup-head">
                <div>
                    <h2>{{ __('client.setup.title', ['percent' => $percent]) }}</h2>
                    <p>{{ __('client.setup.sub') }}</p>
                </div>
                <span class="setup-count font-mono">{{ __('client.setup.progress', ['done' => $done, 'total' => $total]) }}</span>
            </div>

            <div
                class="setup-bar"
                role="progressbar"
                aria-valuenow="{{ $percent }}"
                aria-valuemin="0"
                aria-valuemax="100"
            >
                <i style="width: {{ $percent }}%"></i>
            </div>

            <div class="setup-list">
                @foreach ($steps as $key => $completed)
                    <div
                        wire:key="step-{{ $key }}"
                        @class(['setup-step', 'is-done' => $completed, 'is-hero' => $key === 'whatsapp'])
                    >
                        <span class="setup-tick">
                            @if ($completed)
                                <x-icon name="check" :size="14" />
                            @endif
                        </span>
                        <div class="setup-copy">
                            <b>{{ __('client.setup.steps.'.$key.'.label') }}</b>
                            <span>{{ __('client.setup.steps.'.$key.'.hint') }}</span>
                        </div>
                        @unless ($completed)
                            @php
                                // Every step leads somewhere real (audit, 2026-09-20):
                                // trying the assistant lives beside the catalog.
                                $stepRoute = ['business' => 'my-business', 'catalog' => 'my-services',
                                    'try' => 'my-services', 'whatsapp' => 'whatsapp'][$key] ?? null;
                            @endphp
                            @if ($stepRoute !== null)
                                <x-ui.button
                                    :variant="$key === 'whatsapp' ? 'primary' : 'secondary'"
                                    size="sm"
                                    :href="route($stepRoute)"
                                    wire:navigate
                                >
                                    {{ __('client.setup.steps.'.$key.'.cta') }}
                                </x-ui.button>
                            @endif
                        @endunless
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        {{-- Empty widgets teach what fills them: a preview of the populated
        state beats a naked zero (NN/g empty-state guidance). --}}
        <div class="preview-grid">
            <x-ui.card class="preview-card">
                <div class="preview-head">
                    <span class="preview-icon"><x-icon name="message-circle" :size="16" /></span>
                    <h3>{{ __('client.previews.conversations_title') }}</h3>
                </div>
                <p>{{ __('client.previews.conversations_text') }}</p>
            </x-ui.card>
            <x-ui.card class="preview-card">
                <div class="preview-head">
                    <span class="preview-icon"><x-icon name="bar-chart-3" :size="16" /></span>
                    <h3>{{ __('client.previews.metrics_title') }}</h3>
                </div>
                <p>{{ __('client.previews.metrics_text') }}</p>
            </x-ui.card>
        </div>
    @else
        {{-- Real month numbers, same vocabulary as /estadisticas. --}}
        <div class="stat-grid">
            <x-ui.stat-card
                :label="__('statistics.kpis.conversations')"
                :value="(string) $this->kpis['conversations']"
                icon="message-circle"
                tint="brand"
            />
            <x-ui.stat-card
                :label="__('statistics.kpis.questions')"
                :value="(string) $this->kpis['questions']"
                icon="bot"
                tint="info"
            />
            <x-ui.stat-card
                :label="__('statistics.kpis.new_contacts')"
                :value="(string) $this->kpis['new_contacts']"
                icon="users"
                tint="warning"
            />
            <x-ui.stat-card
                :label="__('statistics.kpis.audio_minutes')"
                :value="(string) $this->kpis['audio_minutes']"
                icon="zap"
                tint="accent"
            />
        </div>

        <x-ui.card class="recent-card">
            <div class="recent-head">
                <h3>{{ __('client.recent.title') }}</h3>
                <x-ui.button variant="ghost" size="sm" :href="route('conversations')" wire:navigate>
                    {{ __('client.recent.view_all') }}
                </x-ui.button>
            </div>
            @foreach ($this->recent as $thread)
                <a href="{{ route('conversations') }}" wire:navigate wire:key="recent-{{ $thread->id }}" class="recent-row">
                    <x-ui.avatar :name="$thread->contact_name ?? $thread->contact_phone" size="sm" />
                    <div class="recent-copy">
                        <b>{{ $thread->contact_name ?? __('client.conversations.anonymous') }}</b>
                        <span>{{ $thread->latestMessage?->body }}</span>
                    </div>
                    <span class="recent-time font-mono">
                        {{ $thread->last_message_at?->isToday() ? $thread->last_message_at->format('H:i') : $thread->last_message_at?->format('d/m') }}
                    </span>
                </a>
            @endforeach
        </x-ui.card>
    @endif
</div>
