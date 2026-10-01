<?php

use App\Enums\SupportTicketKind;
use App\Livewire\Forms\Client\SupportTicketForm;
use App\Models\Menu;
use App\Traits\HasNotifications;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Technical support from inside the panel. What the screen already knows is
 * CAPTURED — route, url, browser, viewport, plan — and never asked, which is
 * what lets the form demand one single field. Opened from a screen it arrives
 * pointing at that screen; opened from nowhere in particular it asks where.
 */
new class extends Component
{
    use HasNotifications;
    use WithFileUploads;

    public SupportTicketForm $form;

    public bool $opened = false;

    /** The screen it was opened from, or null when there is none to guess. */
    #[Locked]
    public ?string $from = null;

    /** Captured by the browser when the panel opens: never typed by anyone. */
    public array $client = [];

    public ?string $sentCode = null;

    public function mount(?string $from = null): void
    {
        $this->from = $from ?? request()->route()?->getName();
    }

    public function open(): void
    {
        $this->opened = true;
        $this->sentCode = null;
        $this->form->screen = $this->from;
    }

    public function close(): void
    {
        $this->opened = false;
        $this->form->reset();
        $this->form->screen = $this->from;
    }

    /**
     * The screens she can point at, so a report opened from nowhere still
     * lands somewhere. Same menu the panel draws, same permissions.
     *
     * @return Collection<string, string>
     */
    #[Computed]
    public function screens(): Collection
    {
        return $this->flatten(Menu::tree())
            ->filter(fn (Menu $item): bool => $item->route_name !== null)
            ->unique('route_name')
            ->mapWithKeys(fn (Menu $item): array => [(string) $item->route_name => $item->label])
            ->sort();
    }

    /** @return array<int, array{value: string, label: string, icon: string}> */
    #[Computed]
    public function kinds(): array
    {
        return array_map(fn (SupportTicketKind $kind): array => [
            'value' => $kind->value,
            'label' => $kind->label(),
            'icon' => $kind->icon(),
        ], SupportTicketKind::cases());
    }

    public function send(): void
    {
        $notification = $this->form->save($this->context());

        $this->dispatchNotification($notification);

        if ($this->form->ticket !== null) {
            $this->sentCode = $this->form->ticket->code;
            $this->form->reset();
            $this->form->screen = $this->from;
        }
    }

    /**
     * What we know without asking. The javascript errors come from the same
     * bucket livewire-failures.js already fills, so a broken screen reports
     * itself instead of being described.
     *
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return array_filter([
            'route' => $this->from,
            'url' => $this->client['url'] ?? null,
            'viewport' => $this->client['viewport'] ?? null,
            'agent' => $this->client['agent'] ?? null,
            'errors' => $this->client['errors'] ?? null,
            'plan' => auth()->user()?->business?->subscription?->plan,
            'locale' => app()->getLocale(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  Collection<int, Menu>  $items
     * @return Collection<int, Menu>
     */
    private function flatten(Collection $items): Collection
    {
        return $items->flatMap(fn (Menu $item): Collection => collect([$item])
            ->merge($this->flatten($item->childrenRecursive)));
    }
};
?>

<div
    class="support"
    x-data="{
        open: false,
        show() {
            this.open = true;
            /* Captured, not asked: the url, the window and the errors the page
               already collected. Nothing here is a question. */
            $wire.client = {
                url: window.location.href,
                viewport: window.innerWidth + 'x' + window.innerHeight,
                agent: navigator.userAgent,
                errors: (window.atendiaErrors || []).slice(-5).join(' | '),
            };
            const ready = $wire.opened ? Promise.resolve() : $wire.open();
            return Promise.resolve(ready).then(() => $nextTick(() => this.$refs.body?.focus()));
        },
        hide() {
            this.open = false;
            $wire.close();
        },
    }"
    x-on:slide-over-close.window="if (open) hide()"
>
    <button
        type="button"
        class="icon-btn icon-btn-secondary"
        x-on:click="show()"
        :aria-expanded="open"
        aria-haspopup="dialog"
        :aria-label="'{{ __('support.open') }}'"
        data-testid="support-open"
    >
        <x-icon name="life-buoy" :size="18" />
    </button>

    {{-- The topbar is a containing block and would clip a fixed panel. --}}
    @teleport('body')
        <div x-show="open" x-cloak>
            @if ($opened)
                <x-ui.slide-over :title="__('support.title')" :subtitle="__('support.sub')">
                    @if ($sentCode !== null)
                        <x-ui.card class="p-6">
                            <x-ui.empty-state
                                icon="check-circle"
                                :title="__('support.thanks_title')"
                                :body="__('support.thanks_body', ['code' => $sentCode])"
                                compact
                            >
                                <x-ui.button variant="secondary" size="sm" x-on:click="hide()">
                                    {{ __('support.thanks_close') }}
                                </x-ui.button>
                            </x-ui.empty-state>
                        </x-ui.card>
                    @else
                        <form wire:submit="send" class="support-form">
                            {{-- The only required field, and the first one: a
                            category asked before the text is why reports die. --}}
                            <x-catalog.form-row>
                                <x-inputsform.textarea
                                    span="full"
                                    x-ref="body"
                                    name="body"
                                    :label="__('support.fields.body')"
                                    :hint="__('support.body_hint')"
                                    :rows="5"
                                    maxlength="2000"
                                    counter
                                    wire:model="form.body"
                                />
                            </x-catalog.form-row>

                            <p class="support-legend">{{ __('support.kind_legend') }}</p>
                            <div class="support-kinds" role="group" aria-label="{{ __('support.fields.kind') }}">
                                @foreach ($this->kinds as $kind)
                                    <button
                                        type="button"
                                        class="support-chip @if ($form->kind === $kind['value']) is-on @endif"
                                        wire:click="$set('form.kind', '{{ $kind['value'] }}')"
                                        data-testid="support-kind-{{ $kind['value'] }}"
                                    >
                                        <x-icon :name="$kind['icon']" :size="16" />
                                        {{ $kind['label'] }}
                                    </button>
                                @endforeach
                            </div>

                            <x-catalog.form-row>
                                {{-- Pre-picked from where she opened it; still a
                                field, because the guess can be wrong. --}}
                                <x-inputsform.combobox
                                    span="long"
                                    name="screen"
                                    :label="__('support.fields.screen')"
                                    :value="$form->screen"
                                    :options="$this->screens->all()"
                                    :placeholder="__('support.screen_placeholder')"
                                    wire:model="form.screen"
                                />
                            </x-catalog.form-row>

                            <x-catalog.form-row>
                                <x-inputsform.file
                                    span="full"
                                    name="attachment"
                                    :label="__('support.fields.attachment')"
                                    :note="__('support.attachment_hint')"
                                    wire:model="form.attachment"
                                />
                            </x-catalog.form-row>

                            <div class="support-actions">
                                <x-ui.button variant="danger" size="sm" type="button" x-on:click="hide()">
                                    {{ __('support.cancel') }}
                                </x-ui.button>
                                <x-ui.button variant="primary" size="sm" type="submit" wire:loading.attr="disabled">
                                    {{ __('support.send') }}
                                </x-ui.button>
                            </div>
                        </form>
                    @endif
                </x-ui.slide-over>
            @endif
        </div>
    @endteleport
</div>
