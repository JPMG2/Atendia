<?php

use App\Classes\Main\Access;
use App\Classes\Main\AuditTrail;
use App\Dto\RoleMenuNodeDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Admin\RoleForm;
use App\Models\User;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * One role, edited on the menu it opens: the admin sidebar painted with a
 * switch per screen, so a job is described by looking at what it will see.
 */
new class extends Component
{
    use HasNotifications;

    public RoleForm $form;

    /** The role being edited; null while a new one is still being written. */
    #[Locked]
    public ?int $roleId = null;

    /**
     * What the editor starts from. The Alpine state is seeded from these two
     * once: a seed that changed with every render would restart it and lose
     * the switches the person just moved.
     *
     * @var list<string>
     */
    #[Locked]
    public array $seed = [];

    #[Locked]
    public bool $startedNew = true;

    /** How many people the role reaches, for the preview's "this takes X from N people". */
    #[Locked]
    public int $holdersCount = 0;

    public function mount(?int $role = null): void
    {
        if ($role === null) {
            $this->form->setup();

            return;
        }

        // The owner's role never loads here: a screen that could open it could save it.
        $found = Access::staffRole($role);

        abort_if($found === null, 404);

        $this->form->setup($found);
        $this->roleId = $found->id;
        $this->seed = $this->form->permissions;
        $this->startedNew = false;
        $this->holdersCount = (int) $found->users_count;
    }

    /**
     * The row names that open each permission, to say WHICH screens a save moves.
     *
     * @return array<string, list<string>>
     */
    #[Computed]
    public function screenNames(): array
    {
        $names = [];
        $walk = function (array $nodes) use (&$walk, &$names): void {
            foreach ($nodes as $node) {
                if ($node->children === [] && $node->permission !== null) {
                    $names[$node->permission][] = $node->label;
                }

                $walk($node->children);
            }
        };

        $walk($this->tree);

        return $names;
    }

    /** @return list<RoleMenuNodeDto> */
    #[Computed]
    public function tree(): array
    {
        return Access::menuTree();
    }

    /**
     * One entry per switchable row, repeated when two screens share a permission.
     *
     * @return list<string>
     */
    #[Computed]
    public function rows(): array
    {
        return array_merge([], ...array_map(fn (RoleMenuNodeDto $node): array => $node->screenPermissions, $this->tree));
    }

    /** @return list<array{label: string, screens: list<string>}> */
    #[Computed]
    public function groups(): array
    {
        return collect($this->tree)
            ->filter(fn (RoleMenuNodeDto $node): bool => $node->screenPermissions !== [])
            ->map(fn (RoleMenuNodeDto $node): array => ['label' => $node->label, 'screens' => $node->screenPermissions])
            ->values()
            ->all();
    }

    /** @return array<string, list<string>> */
    #[Computed]
    public function dependents(): array
    {
        return Access::dependents($this->tree);
    }

    /** @return list<string> */
    #[Computed]
    public function alwaysOn(): array
    {
        return collect($this->tree)->filter(fn (RoleMenuNodeDto $node): bool => $node->isAlwaysOn)->pluck('label')->all();
    }

    #[Computed]
    public function role(): ?Role
    {
        return $this->roleId === null ? null : Access::staffRole($this->roleId);
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function holders(): Collection
    {
        return $this->role === null ? collect() : Access::holders($this->role);
    }

    /** Whether the role's trail is open in the side panel. */
    public bool $historyOpen = false;

    /** How many entries the panel shows; the audit screen holds the rest. */
    private const int HISTORY_LIMIT = 30;

    public function openHistory(): void
    {
        $this->authorize('audit.view');

        $this->historyOpen = $this->roleId !== null;
    }

    public function closeHistory(): void
    {
        $this->historyOpen = false;
    }

    /** @return Collection<int, \App\Models\Activity> */
    #[Computed]
    public function history(): Collection
    {
        return $this->role === null ? collect() : AuditTrail::forRole($this->role, self::HISTORY_LIMIT);
    }

    /** The "start from" pick is only a trigger: nothing about it is stored. */
    public string $starter = '';

    /** @return array<string, string> */
    #[Computed]
    public function startingLabels(): array
    {
        return collect(Access::startingPoints())->map(fn (array $point): string => $point['label'])->all();
    }

    public function updatedStarter(string $value): void
    {
        $point = Access::startingPoints()[$value] ?? null;

        if ($point !== null) {
            $this->dispatch('role-loaded', permissions: $point['permissions']);
        }
    }

    public function save(): void
    {
        $this->authorize('roles.manage');

        $key = mb_strtolower(trim($this->form->name));
        $notification = $this->form->save();

        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Success) {
            return;
        }

        // The form clears itself after saving; the screen keeps showing the role it just wrote.
        $role = Access::staffRoleNamed($key);

        if ($role === null) {
            return;
        }

        $isNew = $this->roleId === null;

        $this->form->setup($role);
        $this->roleId = $role->id;
        unset($this->role, $this->holders);

        $this->dispatch('role-saved', permissions: $this->form->permissions);

        // Reloading /nuevo would write the same role twice: the address now names it.
        if ($isNew) {
            $this->js('history.replaceState(null, "", '.Js::from(route('admin.roles.edit', $role->id)).')');
        }
    }

    /** The tab is copy: a PHP attribute cannot call __(). */
    public function render(): View
    {
        $title = $this->roleId === null
            ? __('admin.roles.editor.title_new')
            : __('admin.roles.editor.title_edit', ['role' => $this->form->name]);

        return $this->view()->title($title);
    }
};
?>

<div
    class="re"
    x-data="roleEditor({
        wire: $wire,
        saved: {{ Js::from($seed) }},
        dependents: {{ Js::from($this->dependents) }},
        rows: {{ Js::from($this->rows) }},
        groups: {{ Js::from($this->groups) }},
        isNew: {{ Js::from($startedNew) }},
        holders: {{ Js::from($holdersCount) }},
        screenNames: {{ Js::from($this->screenNames) }},
        labels: {
            removes: {{ Js::from(__('admin.roles.editor.impact_removes')) }},
            adds: {{ Js::from(__('admin.roles.editor.impact_adds')) }},
            confirmTitle: {{ Js::from(__('admin.roles.editor.confirm_title')) }},
            confirmAccept: {{ Js::from(__('admin.roles.editor.confirm_accept')) }},
            personOne: {{ Js::from(__('admin.roles.editor.person_one')) }},
            personMany: {{ Js::from(__('admin.roles.editor.person_many')) }},
            clean: {{ Js::from(__('admin.roles.editor.clean')) }},
            unsavedNew: {{ Js::from(__('admin.roles.editor.unsaved_new')) }},
            unsavedOne: {{ Js::from(__('admin.roles.editor.unsaved_one')) }},
            unsavedMany: {{ Js::from(__('admin.roles.editor.unsaved_many')) }},
        },
    })"
    x-on:role-saved.window="settle($event.detail)"
    x-on:role-loaded.window="load($event.detail)"
>
    <x-ui.page-head
        :title="$roleId === null ? __('admin.roles.editor.title_new') : __('admin.roles.editor.title_edit', ['role' => $form->name])"
        :sub="__('admin.roles.editor.sub')"
        :back="route('admin.roles')"
        :backLabel="__('admin.roles.editor.back')"
    />

    <form x-on:submit.prevent="confirmSave()">
        @if ($roleId === null)
            <x-ui.card class="re-name p-5">
                <x-catalog.form-row>
                    <x-inputsform.input
                        span="short"
                        :label="__('admin.roles.fields.name')"
                        required
                        name="name"
                        :hint="__('admin.roles.fields.name_hint')"
                        maxlength="40"
                        wire:model="form.name"
                    />

                    <x-inputsform.combobox
                        span="text"
                        :label="__('admin.roles.editor.start_label')"
                        name="starter"
                        :hint="__('admin.roles.editor.start_hint')"
                        :placeholder="__('admin.roles.editor.start_placeholder')"
                        :options="$this->startingLabels"
                        wire:model.live="starter"
                    />
                </x-catalog.form-row>
            </x-ui.card>
        @endif

        <div class="re-layout">
            <x-ui.card class="re-menu">
                <div class="re-menu-head">
                    <h2>{{ __('admin.roles.editor.menu_title') }}</h2>
                    <div class="re-menu-tools">
                        @if ($roleId !== null && auth()->user()->can('audit.view'))
                            <button type="button" class="re-link" wire:click="openHistory" data-testid="role-history">{{ __('admin.roles.editor.history') }}</button>
                        @endif
                        <button type="button" class="re-link" x-on:click="previewing = true">{{ __('admin.roles.editor.preview') }}</button>
                        <button type="button" class="re-link" x-on:click="setAll(true)">{{ __('admin.roles.editor.all_on') }}</button>
                        <button type="button" class="re-link" x-on:click="setAll(false)">{{ __('admin.roles.editor.all_off') }}</button>
                    </div>
                </div>

                <div class="re-tree">
                    @foreach ($this->tree as $node)
                        <x-roles.menu-node :node="$node" wire:key="node-{{ $loop->index }}" />
                    @endforeach
                </div>
            </x-ui.card>

            <aside class="re-side">
                <x-ui.card class="re-sum">
                    <p class="re-big">
                        <span x-text="open"></span>
                        <small>{{ __('admin.roles.editor.of') }} <span x-text="total"></span> {{ __('admin.roles.editor.screens') }}</small>
                    </p>
                    <div class="re-meter"><b x-bind:style="{ width: percent + '%' }"></b></div>

                    <ul class="re-opens">
                        <template x-for="group in summary" x-bind:key="group.label">
                            <li>
                                <x-icon name="check" :size="16" />
                                <span>
                                    <b x-text="group.label"></b>
                                    <span x-show="group.of > 1" x-text="'· ' + group.on + ' {{ __('admin.roles.editor.of') }} ' + group.of"></span>
                                </span>
                            </li>
                        </template>
                        <li class="re-none" x-show="summary.length === 0">{{ __('admin.roles.editor.none_open') }}</li>
                    </ul>

                    @if ($this->alwaysOn !== [])
                        <p class="re-note">{{ __('admin.roles.editor.always_note', ['screens' => implode(', ', $this->alwaysOn)]) }}</p>
                    @endif

                    <p class="re-note">{{ __('admin.roles.panel_implicit') }}</p>
                </x-ui.card>

                @if ($roleId !== null)
                    <x-ui.card class="re-sum">
                        <h3 class="re-side-title">{{ __('admin.roles.editor.holders_title') }}</h3>

                        @if ($this->holders->isNotEmpty())
                            <div class="re-faces">
                                @foreach ($this->holders as $holder)
                                    <x-ui.avatar :name="$holder->name" size="sm" wire:key="holder-{{ $holder->id }}" />
                                @endforeach
                            </div>
                        @endif

                        <p class="re-note">{{ trans_choice('admin.roles.editor.holders_count', $this->role?->users_count ?? 0, ['count' => $this->role?->users_count ?? 0]) }}</p>

                        @if (($this->role?->users_count ?? 0) > 0)
                            <p class="re-note">{{ __('admin.roles.editor.holders_note') }}</p>
                        @endif
                    </x-ui.card>
                @endif
            </aside>
        </div>

        @if ($historyOpen && $this->role !== null)
            <x-ui.slide-over
                x-on:slide-over-close="$wire.closeHistory()"
                :title="__('admin.roles.editor.history_title', ['role' => $this->role->name])"
                :subtitle="__('admin.roles.editor.history_sub')"
            >
                @if ($this->history->isEmpty())
                    <x-ui.empty-state icon="history" :title="__('admin.roles.editor.history_empty_title')" :body="__('admin.roles.editor.history_empty_body')" :compact="true" />
                @else
                    <ol class="hist-list" data-testid="role-history-list">
                        @foreach ($this->history as $entry)
                            @php
                                // A permission change sits on the role; being given or losing the role sits on the person.
                                $text = ($entry->properties['what'] ?? '') === 'role'
                                    ? __('admin.roles.editor.history_'.($entry->description === 'granted' ? 'given' : 'lost'), ['name' => $entry->subject?->name ?? '#'.$entry->subject_id])
                                    : collect($entry->properties['names'] ?? [])->reject(Access::PANEL)->map(
                                        fn (string $name): string => Lang::has('admin.roles.permissions.'.$name) ? __('admin.roles.permissions.'.$name) : $name,
                                    )->implode(', ');
                            @endphp

                            @continue ($text === '')

                            <li class="hist-item" wire:key="role-hist-{{ $entry->id }}">
                                <span class="hist-when font-mono">{{ $entry->created_at?->format('d/m/Y H:i') }}</span>
                                <span @class(['status-tag', 'is-danger' => $entry->description === 'revoked', 'is-neutral' => $entry->description !== 'revoked'])>
                                    {{ __('admin.audit.actions.'.$entry->description) }}
                                </span>
                                <span class="hist-on">{{ $text }}</span>
                                <span class="hist-on text-muted">{{ __('admin.roles.editor.history_by', ['name' => $entry->causer?->name ?? __('admin.audit.system')]) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif

                <x-slot:footer>
                    <x-ui.button variant="secondary" icon="scroll-text" :href="route('admin.audit', ['fuertes' => 0])" wire:navigate>
                        {{ __('admin.users.timeline.open_audit') }}
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.slide-over>
        @endif

        <template x-if="previewing">
            <x-ui.slide-over
                x-on:slide-over-close="previewing = false"
                :title="__('admin.roles.editor.preview_title')"
                :subtitle="__('admin.roles.editor.preview_sub')"
            >
                <template x-if="impact.length > 0">
                    <div class="re-impact">
                        <template x-for="line in impact" x-bind:key="line"><p x-text="line"></p></template>
                    </div>
                </template>

                <nav class="re-preview">
                    @foreach ($this->tree as $node)
                        <x-roles.preview-node :node="$node" />
                    @endforeach

                    <p class="re-note" x-show="open === 0">{{ __('admin.roles.editor.preview_empty') }}</p>
                </nav>
            </x-ui.slide-over>
        </template>

        <div class="re-bar"x-bind:class="{ 're-dirty': dirty }">
            <span class="re-bar-msg"><span class="re-dot"></span><span x-text="message"></span></span>

            <div class="re-bar-btns">
                <x-ui.button
                    type="button"
                    variant="danger"
                    x-on:click="dirty
                        ? dialog.confirm({
                            title: {{ Js::from(__('admin.roles.editor.discard_title')) }},
                            message: {{ Js::from(__('admin.roles.editor.discard_body')) }},
                            accept: {{ Js::from(__('admin.roles.editor.discard_accept')) }},
                            type: 'warning',
                        }).then((ok) => ok && Livewire.navigate({{ Js::from(route('admin.roles')) }}))
                        : Livewire.navigate({{ Js::from(route('admin.roles')) }})"
                >{{ __('admin.roles.cancel') }}</x-ui.button>

                <x-ui.button type="submit" variant="primary" icon="check" x-bind:disabled="! dirty && ! isNew">{{ __('admin.roles.save') }}</x-ui.button>
            </div>
        </div>
    </form>
</div>
