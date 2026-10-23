<?php

use App\Classes\Main\Bell;
use App\Classes\Main\Client;
use App\Models\Business;
use App\Models\PanelNotification;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The bell: the topbar badge and the panel with what happened. The badge is the
 * only thing that costs a query on every screen the topbar rides; the feed is
 * read when the panel opens, like its neighbour Ask AtendIa.
 */
new class extends Component
{
    /** True while the panel is open: the feed is fetched only then. */
    public bool $opened = false;

    /**
     * The badge follows the socket: a row raised while the owner is looking at
     * another screen lights the bell without a reload.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $businessId = Auth::user()?->business_id;

        return $businessId === null ? [] : ["echo-private:business.{$businessId},.panel.notified" => '$refresh'];
    }

    #[Computed]
    public function user(): ?User
    {
        return Auth::user();
    }

    #[Computed]
    public function business(): ?Business
    {
        return $this->user?->business;
    }

    /** The number on the bell. Nothing to read keeps it dark, on purpose. */
    #[Computed]
    public function unread(): int
    {
        return $this->business === null || $this->user === null
            ? 0
            : PanelNotification::unreadCountFor($this->user);
    }

    /**
     * The feed already cut into the day groups the panel prints, on the
     * business clock.
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    #[Computed]
    public function groups(): Collection
    {
        return $this->bell?->groups ?? collect();
    }

    /** The piece both the panel and the mobile app read the inbox through. */
    #[Computed]
    public function bell(): ?Bell
    {
        return $this->user === null ? null : Client::for($this->user)->bell;
    }

    /**
     * The unread in one sentence, above the list of it: she reads whether it is
     * worth reading on before scrolling — the digest a morning inbox owes.
     */
    #[Computed]
    public function digest(): string
    {
        return $this->bell?->digestFor($this->groups) ?? '';
    }

    public function open(): void
    {
        $this->opened = true;
    }

    public function close(): void
    {
        $this->opened = false;
    }

    /**
     * Opening a row is what marks it read: opening the panel is not reading.
     * A bundled row carries every id it stands for.
     *
     * @param  list<int>  $ids
     */
    public function read(array $ids): void
    {
        foreach ($ids as $id) {
            PanelNotification::markOneReadFor((int) $id, $this->user);
        }

        unset($this->unread, $this->groups);
    }

    /**
     * The row's click, in ONE round trip. A plain `wire:navigate` link raced
     * its own `wire:click`: the page left before the read was stamped, and the
     * count came back untouched.
     *
     * @param  list<int>  $ids
     */
    public function openRow(array $ids): void
    {
        $this->read($ids);

        // The target is read back from the notice, never taken from the click:
        // a url coming off the page would be an open redirect.
        $url = PanelNotification::urlOf((int) ($ids[0] ?? 0));

        if ($url !== null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function readAll(): void
    {
        PanelNotification::markAllReadFor($this->user);

        unset($this->unread, $this->groups);
    }

    /** The hour on the business clock: a row stamped in UTC reads three hours off. */
    public function time(CarbonInterface $at): string
    {
        return $at->copy()->setTimezone($this->business->localTimezone())->format('H:i');
    }
};
?>

<div class="bell" x-data="{ open: false }" x-on:slide-over-close.window="if (open) { open = false; $wire.close(); }">
    {{-- Same markup as its neighbours (theme, Ask AtendIa): one row, one button style. --}}
    <button
        type="button"
        class="icon-btn icon-btn-secondary bell-btn"
        data-testid="bell"
        aria-label="{{ __('bell.title') }}"
        title="{{ __('bell.title') }}"
        x-on:click="open = true; $wire.open()"
    >
        <x-icon name="bell" :size="20" />
        @if ($this->unread > 0)
            <span class="bell-count" data-testid="bell-count" aria-label="{{ __('bell.unread', ['count' => $this->unread]) }}">
                {{ $this->unread > 9 ? '9+' : $this->unread }}
            </span>
        @endif
    </button>

    {{-- To the body: the topbar is a containing block and would clip a fixed panel to its height. --}}
    @teleport('body')
        <div x-show="open" x-cloak>
            @if ($opened)
                <x-ui.slide-over :title="__('bell.title')" :subtitle="__('bell.subtitle')">
                    @if ($this->unread > 0)
                        {{-- The night in one sentence before the list of it: the
                        digest tells her whether it is worth reading on. --}}
                        <p class="bell-digest" data-testid="bell-digest">{{ $this->digest }}</p>

                        <div class="bell-actions">
                            <x-ui.button variant="ghost" size="sm" icon="check-check" wire:click="readAll">
                                {{ __('bell.mark_all') }}
                            </x-ui.button>
                        </div>
                    @endif

                    @forelse ($this->groups as $group => $rows)
                        <p class="bell-group">{{ __('bell.groups.'.$group) }}</p>

                        <ul class="bell-list">
                            @foreach ($rows as $row)
                                <li wire:key="row-{{ $row['key'] }}" class="bell-item">
                                    {{-- The navigation is server side so the read
                                    mark cannot lose the race to it. --}}
                                    <button
                                        type="button"
                                        data-testid="bell-row-{{ $row['type']->value }}"
                                        class="bell-row @unless ($row['read']) bell-row-unread @endunless"
                                        wire:click="openRow({{ Js::from($row['ids']) }})"
                                        x-on:click="open = false"
                                    >
                                        <span class="bell-mark" style="{{ $row['type']->tintStyle() }}">
                                            <x-icon :name="$row['type']->icon()" :size="16" />
                                        </span>

                                        <span class="bell-text">{{ $row['text'] }}</span>

                                        <time class="bell-time font-mono">{{ $this->time($row['at']) }}</time>
                                    </button>

                                    {{-- Clearing a bundle of eight one by one is
                                    work: it gets its own way out, beside the row. --}}
                                    @if (count($row['ids']) > 1 && ! $row['read'])
                                        <x-ui.icon-button
                                            icon="check-check"
                                            size="sm"
                                            variant="ghost"
                                            data-testid="bell-clear-{{ $row['type']->value }}"
                                            :label="__('bell.mark_kind')"
                                            :title="__('bell.mark_kind')"
                                            wire:click="read({{ Js::from($row['ids']) }})"
                                        />
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @empty
                        <x-ui.empty-state
                            icon="bell"
                            :title="__('bell.empty.title')"
                            :body="__('bell.empty.body')"
                        />
                    @endforelse

                    <x-slot:footer>
                        {{-- Silencing a kind belongs where it annoys, not two
                        screens away: the gear sits in the panel, as in Slack. --}}
                        <a href="{{ route('settings.avisos') }}" wire:navigate class="bell-settings-link" x-on:click="open = false">
                            <x-icon name="settings" :size="16" />
                            {{ __('bell.settings.link') }}
                        </a>
                    </x-slot:footer>
                </x-ui.slide-over>
            @endif
        </div>
    @endteleport
</div>
