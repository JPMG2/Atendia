<?php

use App\Actions\Admin\RejectMentionFollow;
use App\Actions\Admin\VerifyMentionFollow;
use App\Dto\NotificationDto;
use App\Enums\MentionStatus;
use App\Enums\NotificationType;
use App\Livewire\Forms\Admin\FollowerCountForm;
use App\Livewire\Forms\Admin\MentionPublishForm;
use App\Models\BusinessMention;
use App\Models\MentionGoal;
use App\Models\SocialFollowerCount;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The desk of the businesses that accepted to be mentioned on the platform's
 * networks. One thing to do per row and it is done in the row: check that the
 * business follows, then publish and paste the link. Nothing goes out for an
 * account that does not follow ours.
 */
new class extends Component
{
    use HasNotifications;

    private const PAGE = 25;

    public MentionPublishForm $form;

    #[Url(as: 'ver', except: 'pending')]
    public string $stage = 'pending';

    public int $limit = self::PAGE;

    /** The row whose link is being typed; 0 when none. */
    public int $publishing = 0;

    public FollowerCountForm $count;

    /** The account whose followers are being typed; 0 when none. */
    public int $counting = 0;

    /** @return \Illuminate\Support\Collection<int, array{link: \App\Models\SocialLink, latest: ?SocialFollowerCount, change: ?int, mentions: int, stale: bool}> */
    #[Computed]
    public function followers(): \Illuminate\Support\Collection
    {
        return SocialFollowerCount::board();
    }

    public function startCount(int $linkId): void
    {
        $this->authorize('mentions.manage');

        $this->count->setup();
        $this->counting = $linkId;
    }

    public function cancelCount(): void
    {
        $this->count->setup();
        $this->counting = 0;
    }

    public function saveCount(): void
    {
        $this->authorize('mentions.manage');

        $notification = $this->count->save($this->counting, auth()->user());
        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Success) {
            $this->counting = 0;
        }
    }

    /** @return array{pending: int, not_following: int, ready: int, published: int} */
    #[Computed]
    public function counts(): array
    {
        return BusinessMention::deskCounts();
    }

    /** @return Collection<int, BusinessMention> */
    #[Computed]
    public function rows(): Collection
    {
        // One row past the page: it tells whether "load more" has anything to load.
        return BusinessMention::desk($this->status(), $this->limit + 1);
    }

    /** What the export menu carries to the report: the stage on screen. */
    #[Computed]
    public function exportParams(): array
    {
        return ['ver' => $this->status()->value];
    }

    private function status(): MentionStatus
    {
        $status = MentionStatus::tryFrom($this->stage);

        return $status !== null && ! $status->isRefusal() ? $status : MentionStatus::Pending;
    }

    public function show(string $stage): void
    {
        $this->stage = $stage;
        $this->limit = self::PAGE;
        $this->publishing = 0;
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE;
    }

    public function verify(int $id): void
    {
        $this->authorize('mentions.manage');

        $this->dispatchNotification(app(VerifyMentionFollow::class)->handle($id, auth()->user()) === null
            ? new NotificationDto(__('mentions.stale'), NotificationType::Error)
            : new NotificationDto(__('mentions.verified'), NotificationType::Success));
    }

    public function reject(int $id): void
    {
        $this->authorize('mentions.manage');

        $this->dispatchNotification(app(RejectMentionFollow::class)->handle($id) === null
            ? new NotificationDto(__('mentions.stale'), NotificationType::Error)
            : new NotificationDto(__('mentions.rejected'), NotificationType::Success));
    }

    public function startPublish(int $id): void
    {
        $this->authorize('mentions.manage');

        $this->form->setup();
        $this->publishing = $id;
    }

    public function cancelPublish(): void
    {
        $this->form->setup();
        $this->publishing = 0;
    }

    public function publish(): void
    {
        $this->authorize('mentions.manage');

        $notification = $this->form->save($this->publishing);
        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Success) {
            $this->publishing = 0;
        }
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('mentions.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('mentions.title')" :sub="__('mentions.sub')">
        <x-ui.export-menu report="menciones" :params="$this->exportParams" />
    </x-ui.page-head>

    @island(name: 'desk')
    <div wire:poll.30s.visible class="flex flex-col gap-4">
        <span class="text-subtle font-mono text-sm">{{ __('mentions.read_at', ['time' => now()->format('H:i:s')]) }}</span>

        <div class="stat-grid stat-grid-fill">
            <x-ui.stat-card
                :label="__('mentions.stages.pending')"
                :value="(string) $this->counts['pending']"
                icon="users"
                :tint="$this->counts['pending'] > 0 ? 'warning' : 'brand'"
                :href="route('admin.mentions', ['ver' => 'pending'])"
            >{{ __('mentions.tiles.pending') }}</x-ui.stat-card>

            <x-ui.stat-card
                :label="__('mentions.stages.ready')"
                :value="(string) $this->counts['ready']"
                icon="send"
                :tint="$this->counts['ready'] > 0 ? 'info' : 'brand'"
                :href="route('admin.mentions', ['ver' => 'ready'])"
            >{{ __('mentions.tiles.ready') }}</x-ui.stat-card>

            <x-ui.stat-card
                :label="__('mentions.stages.not_following')"
                :value="(string) $this->counts['not_following']"
                icon="clock"
                tint="brand"
                :href="route('admin.mentions', ['ver' => 'not_following'])"
            >{{ __('mentions.tiles.not_following') }}</x-ui.stat-card>

            <x-ui.stat-card
                :label="__('mentions.stages.published')"
                :value="(string) $this->counts['published']"
                icon="check-circle"
                tint="brand"
                :href="route('admin.mentions', ['ver' => 'published'])"
            >{{ __('mentions.tiles.published') }}</x-ui.stat-card>
        </div>

        <x-ui.card class="p-5">
            <div class="mb-3 flex flex-wrap gap-2">
                @foreach (['pending', 'ready', 'not_following', 'published'] as $value)
                    <x-ui.button
                        :variant="$this->stage === $value ? 'primary' : 'secondary'"
                        size="sm"
                        wire:click="show('{{ $value }}')"
                        wire:key="stage-{{ $value }}"
                    >{{ __('mentions.stages.'.$value) }} · <span class="font-mono">{{ $this->counts[$value] }}</span></x-ui.button>
                @endforeach
            </div>

            @php($rows = $this->rows->take($limit))

            @if ($rows->isEmpty())
                <x-ui.empty-state compact icon="star" :title="__('mentions.empty.'.$this->stage.'.title')" :body="__('mentions.empty.'.$this->stage.'.body')" />
            @else
                <div class="pay-table-wrap">
                    <table class="pay-table">
                        <thead>
                            <tr>
                                <th>{{ __('mentions.columns.business') }}</th>
                                <th>{{ __('mentions.columns.account') }}</th>
                                <th>{{ __('mentions.columns.answered') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $mention)
                                @php($mine = (string) \Illuminate\Support\Str::of((string) $mention->business_account_url)->after('://')->rtrim('/'))
                                <tr wire:key="mention-{{ $mention->id }}">
                                    <td class="is-key" data-label="{{ __('mentions.columns.business') }}">
                                        <div class="min-w-0">
                                            <div class="font-semibold">{{ $mention->business?->name }}</div>
                                            <div class="text-subtle text-xs">{{ MentionGoal::achievement($mention->goal_threshold) }}</div>
                                            @if ($mention->status === MentionStatus::Ready || $mention->status === MentionStatus::Published)
                                                <div class="text-body mt-1 text-sm" data-caption>{{ $mention->caption }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td data-label="{{ __('mentions.columns.account') }}">
                                        <div class="min-w-0 text-sm">
                                            @if ($mention->business_account_url)
                                                <a href="{{ $mention->business_account_url }}" target="_blank" rel="noopener" class="st-link font-mono">{{ $mine }}</a>
                                            @else
                                                <span class="text-subtle">—</span>
                                            @endif
                                            <div class="text-subtle text-xs">{{ $mention->socialNetwork?->name }}</div>
                                        </div>
                                    </td>
                                    <td class="font-mono" data-label="{{ __('mentions.columns.answered') }}">
                                        {{ ($mention->status === MentionStatus::Published ? $mention->published_at : $mention->answered_at)?->format('d/m H:i') }}
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($mention->status === MentionStatus::Pending)
                                                @foreach ($mention->platform_accounts ?? [] as $account)
                                                    <x-ui.button variant="ghost" size="sm" icon="external-link" :href="$account['url']" target="_blank" rel="noopener" wire:key="ours-{{ $mention->id }}-{{ $loop->index }}">
                                                        {{ __('mentions.actions.open_ours', ['network' => $account['network']]) }}
                                                    </x-ui.button>
                                                @endforeach
                                                <x-ui.button variant="primary" size="sm" icon="check" class="data-loading:opacity-50" wire:click="verify({{ $mention->id }})">
                                                    {{ __('mentions.actions.follows') }}
                                                </x-ui.button>
                                                <x-ui.button variant="danger" size="sm" class="data-loading:opacity-50" wire:click="reject({{ $mention->id }})">
                                                    {{ __('mentions.actions.not_follows') }}
                                                </x-ui.button>
                                            @elseif ($mention->status === MentionStatus::Ready)
                                                @if ($this->publishing === $mention->id)
                                                    <div class="flex w-full flex-wrap items-start gap-2">
                                                        <div class="min-w-48 flex-1">
                                                            <x-ui.input
                                                                name="post_url"
                                                                :placeholder="__('mentions.actions.post_link_placeholder')"
                                                                wire:model="form.post_url"
                                                                wire:keydown.enter="publish"
                                                            />
                                                        </div>
                                                        <x-ui.button variant="primary" size="sm" icon="check" class="data-loading:opacity-50" wire:click="publish">
                                                            {{ __('mentions.actions.save_published') }}
                                                        </x-ui.button>
                                                        <x-ui.button variant="danger" size="sm" wire:click="cancelPublish">
                                                            {{ __('mentions.actions.cancel') }}
                                                        </x-ui.button>
                                                    </div>
                                                @else
                                                    <div x-data="{ copied: false }" class="flex flex-wrap items-center gap-2">
                                                        <x-ui.button
                                                            variant="secondary"
                                                            size="sm"
                                                            icon="copy"
                                                            x-on:click="navigator.clipboard?.writeText($el.closest('tr').querySelector('[data-caption]').textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)"
                                                        >
                                                            <span x-text="copied ? {{ \Illuminate\Support\Js::from(__('mentions.actions.copied')) }} : {{ \Illuminate\Support\Js::from(__('mentions.actions.copy')) }}"></span>
                                                        </x-ui.button>
                                                        <x-ui.button variant="primary" size="sm" icon="send" wire:click="startPublish({{ $mention->id }})">
                                                            {{ __('mentions.actions.mark_published') }}
                                                        </x-ui.button>
                                                    </div>
                                                @endif
                                            @elseif ($mention->status === MentionStatus::Published && $mention->post_url)
                                                <x-ui.button variant="ghost" size="sm" icon="external-link" :href="$mention->post_url" target="_blank" rel="noopener">
                                                    {{ __('mentions.actions.open_post') }}
                                                </x-ui.button>
                                            @else
                                                <span class="text-subtle text-xs">{{ $mention->status->label() }}</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-ui.load-more :shown="$rows->count()" :total="$this->rows->count()" />
            @endif
        </x-ui.card>

        {{-- What our own accounts have: the number the mentions are measured against. --}}
        <x-ui.card class="p-5">
            <div class="mb-3">
                <h2 class="font-display text-base font-bold">{{ __('mentions.followers.title') }}</h2>
                <p class="text-subtle text-xs">{{ __('mentions.followers.sub') }}</p>
            </div>

            @if ($this->followers->isEmpty())
                <x-ui.empty-state compact icon="users" :title="__('mentions.followers.empty_title')" :body="__('mentions.followers.empty_body')" />
            @else
                <div class="pay-table-wrap">
                    <table class="pay-table">
                        <tbody>
                            @foreach ($this->followers as $line)
                                <tr wire:key="followers-{{ $line['link']->id }}">
                                    <td class="is-key">
                                        <div class="min-w-0">
                                            <div class="font-semibold">{{ $line['link']->socialNetwork?->name }}</div>
                                            <a href="{{ $line['link']->url }}" target="_blank" rel="noopener" class="st-link font-mono text-xs">{{ \Illuminate\Support\Str::of($line['link']->url)->after('://')->rtrim('/') }}</a>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($line['latest'] === null)
                                            <span class="text-subtle text-sm">{{ __('mentions.followers.never') }}</span>
                                        @else
                                            <div class="min-w-0">
                                                <div class="font-mono text-lg font-bold">{{ number_format($line['latest']->followers, 0, ',', '.') }}</div>
                                                <div class="text-subtle font-mono text-xs">{{ __('mentions.followers.as_of', ['date' => $line['latest']->counted_on->format('d/m/Y')]) }}</div>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($line['change'] === null)
                                            @if ($line['latest'] !== null)
                                                <span class="text-subtle text-xs">{{ __('mentions.followers.first') }}</span>
                                            @endif
                                        @else
                                            <div class="min-w-0 text-sm">
                                                <div class="font-semibold">
                                                    {{ $line['change'] === 0
                                                        ? __('mentions.followers.same')
                                                        : __('mentions.followers.since', ['change' => ($line['change'] > 0 ? '+' : '').number_format($line['change'], 0, ',', '.')]) }}
                                                </div>
                                                <div class="text-subtle text-xs">{{ trans_choice('mentions.followers.mentions', $line['mentions'], ['count' => $line['mentions']]) }}</div>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($line['stale'] && $line['latest'] !== null)
                                                <span class="status-tag is-warning">{{ __('mentions.followers.stale') }}</span>
                                            @endif

                                            @if ($this->counting === $line['link']->id)
                                                <div class="min-w-32">
                                                    <x-ui.input
                                                        name="followers"
                                                        type="number"
                                                        min="0"
                                                        :placeholder="__('mentions.followers.placeholder')"
                                                        wire:model="count.followers"
                                                        wire:keydown.enter="saveCount"
                                                    />
                                                </div>
                                                <x-ui.button variant="primary" size="sm" icon="check" class="data-loading:opacity-50" wire:click="saveCount">
                                                    {{ __('mentions.followers.save') }}
                                                </x-ui.button>
                                                <x-ui.button variant="danger" size="sm" wire:click="cancelCount">
                                                    {{ __('mentions.followers.cancel') }}
                                                </x-ui.button>
                                            @else
                                                <x-ui.button variant="secondary" size="sm" icon="plus" wire:click="startCount({{ $line['link']->id }})">
                                                    {{ __('mentions.followers.count') }}
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
    @endisland
</div>
