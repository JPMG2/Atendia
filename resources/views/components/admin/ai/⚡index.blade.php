<?php

use App\Livewire\Forms\Admin\AiModelForm;
use App\Livewire\Forms\Admin\AiTaskAssignmentForm;
use App\Models\AiModel;
use App\Models\AiTask;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Which model answers each task, and what each model costs.
 *
 * The orchestration lives in `ai_tasks`: with no row a task runs on the pair
 * written in its agent, which is why every row says what runs TODAY instead
 * of leaving the column empty.
 */
new class extends Component
{
    use HasNotifications;

    public AiTaskAssignmentForm $tasks;

    public AiModelForm $model;

    /** Whether the price form is open; it opens above the list, replacing nothing. */
    public bool $editing = false;

    public function mount(): void
    {
        $this->tasks->setup();
        $this->model->setup();
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function rows(): Collection
    {
        return AiTask::board();
    }

    /** @return EloquentCollection<int, AiModel> */
    #[Computed]
    public function models(): EloquentCollection
    {
        return AiModel::board();
    }

    /** @return array<string, string> */
    #[Computed]
    public function options(): array
    {
        return AiModel::assignable();
    }

    /**
     * The labs this app has credentials for, straight from the package config.
     *
     * @return array<string, string>
     */
    public function providers(): array
    {
        return collect(array_keys((array) config('ai.providers')))
            ->mapWithKeys(fn (string $name): array => [$name => $name])
            ->all();
    }

    /**
     * The fallback can only be another lab: on the ladder a second model of
     * the same provider would replace the first instead of following it.
     *
     * @return array<string, string>
     */
    public function fallbackOptions(int $taskId): array
    {
        return AiModel::assignable(AiModel::providerOf($this->tasks->model[$taskId] ?? null));
    }

    public function assign(): void
    {
        $this->dispatchNotification($this->tasks->saveAll());

        unset($this->rows);
    }

    public function create(): void
    {
        $this->model->setup();
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $model = AiModel::priceRow($id);

        if ($model === null) {
            return;
        }

        $this->model->setup($model);
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->model->setup();
        $this->editing = false;
    }

    public function store(): void
    {
        $this->dispatchNotification($this->model->save());

        $this->editing = false;
        unset($this->models, $this->options, $this->rows);
    }

    /** The tab is copy: a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('admin.ai.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('admin.ai.title')" :sub="__('admin.ai.sub')" />

    {{-- Tasks: the assignment, resolved in the row it belongs to. --}}
    <x-ui.card class="mb-3 p-5">
        <p class="sup-meta">{{ __('admin.ai.tasks') }} · {{ __('admin.ai.tasks_sub') }}</p>

        @if ($this->rows->isEmpty())
            <x-ui.empty-state
                compact
                icon="bot"
                :title="__('admin.ai.tasks_empty_title')"
                :body="__('admin.ai.tasks_empty')"
            />
        @else
            {{-- A table, so "Modelo" and "Respaldo" are said once at the top
            instead of twelve times down the page, and the whole assignment is
            one decision with one Guardar under it. --}}
            <div class="pay-table-wrap">
                <table class="pay-table aim-assign">
                    <thead>
                        <tr>
                            <th>{{ __('admin.ai.columns.task') }}</th>
                            <th>{{ __('admin.ai.running') }}</th>
                            <th class="is-pick">{{ __('admin.ai.model') }}</th>
                            <th class="is-pick">{{ __('admin.ai.fallback') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rows as $row)
                            <tr wire:key="task-{{ $row['id'] }}">
                                <td class="is-name" data-label="{{ __('admin.ai.columns.task') }}">
                                    <span class="aiu-name">
                                        {{ $row['label'] }}
                                        @if ($row['mechanical'])
                                            <span class="status-tag is-neutral">{{ __('admin.ai.mechanical') }}</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="is-running font-mono" data-label="{{ __('admin.ai.running') }}">
                                    @forelse ($row['running'] as $provider => $code)
                                        {{ $provider }} · {{ $code ?? __('admin.ai.provider_default') }}
                                    @empty
                                        <span class="text-muted">{{ __('admin.ai.no_agent') }}</span>
                                    @endforelse
                                </td>
                                <td class="is-pick" data-label="{{ __('admin.ai.model') }}">
                                    <x-inputsform.combobox
                                        size="s"
                                        :name="'model-'.$row['id']"
                                        :options="$this->options"
                                        :placeholder="__('admin.ai.unassigned')"
                                        :aria-label="__('admin.ai.model')"
                                        wire:model="tasks.model.{{ $row['id'] }}"
                                    />
                                </td>
                                <td class="is-pick" data-label="{{ __('admin.ai.fallback') }}">
                                    <x-inputsform.combobox
                                        size="s"
                                        :name="'fallback-'.$row['id']"
                                        :options="$this->fallbackOptions($row['id'])"
                                        :placeholder="__('admin.ai.no_fallback')"
                                        :aria-label="__('admin.ai.fallback')"
                                        wire:model="tasks.fallback.{{ $row['id'] }}"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="aim-actions">
                <x-ui.button variant="primary" icon="check" wire:click="assign">
                    {{ __('admin.ai.save') }}
                </x-ui.button>
                <span class="sup-age">{{ __('admin.ai.save_hint') }}</span>
            </div>
        @endif
    </x-ui.card>

    {{-- Models: what the platform may call, and what it cost from a day on. --}}
    <x-ui.card class="p-5">
        <div class="aim-head">
            <p class="sup-meta">{{ __('admin.ai.models') }} · {{ __('admin.ai.models_sub') }}</p>
            <x-ui.button size="sm" variant="primary" icon="plus" wire:click="create">
                {{ __('admin.ai.new') }}
            </x-ui.button>
        </div>

        @if ($editing)
            <form wire:submit="store" class="aim-form">
                <x-catalog.form-row>
                    <x-inputsform.combobox
                        span="short"
                        :label="__('admin.ai.fields.provider')"
                        required
                        name="provider"
                        :options="$this->providers()"
                        wire:model="model.provider"
                    />

                    <x-inputsform.input
                        span="text"
                        :label="__('admin.ai.fields.code')"
                        required
                        name="code"
                        :hint="__('admin.ai.fields.code_hint')"
                        maxlength="80"
                        wire:model="model.code"
                    />

                    <x-inputsform.input
                        span="text"
                        :label="__('admin.ai.fields.label')"
                        required
                        name="label"
                        maxlength="60"
                        wire:model="model.label"
                    />
                </x-catalog.form-row>

                <x-catalog.form-row>
                    <x-inputsform.input
                        span="short"
                        :label="__('admin.ai.fields.prompt')"
                        required
                        name="prompt_per_million"
                        type="number"
                        step="0.0001"
                        min="0"
                        wire:model="model.prompt_per_million"
                    />

                    <x-inputsform.input
                        span="short"
                        :label="__('admin.ai.fields.cached')"
                        required
                        name="cached_per_million"
                        type="number"
                        step="0.0001"
                        min="0"
                        wire:model="model.cached_per_million"
                    />

                    <x-inputsform.input
                        span="short"
                        :label="__('admin.ai.fields.completion')"
                        required
                        name="completion_per_million"
                        type="number"
                        step="0.0001"
                        min="0"
                        wire:model="model.completion_per_million"
                    />

                    <x-inputsform.datepicker
                        span="short"
                        :label="__('admin.ai.fields.effective_from')"
                        name="effective_from"
                        :value="$model->effective_from"
                        wire:model="model.effective_from"
                    />

                    <x-inputsform.switch-field
                        span="short"
                        :label="__('admin.ai.fields.status')"
                        name="is_active"
                        :on="__('admin.ai.active')"
                        :off="__('admin.ai.inactive')"
                        wire:model="model.is_active"
                    />
                </x-catalog.form-row>

                <x-catalog.form-row>
                    <x-inputsform.input
                        span="full"
                        :label="__('admin.ai.fields.source')"
                        name="source"
                        :hint="__('admin.ai.fields.source_hint')"
                        maxlength="255"
                        wire:model="model.source"
                    />
                </x-catalog.form-row>

                <div class="aim-actions">
                    <x-ui.button type="submit" variant="primary" icon="check">{{ __('admin.ai.save_model') }}</x-ui.button>
                    <x-ui.button variant="danger" wire:click="cancel">{{ __('admin.ai.cancel') }}</x-ui.button>
                </div>
            </form>
        @endif

        @if ($this->models->isEmpty())
            <x-ui.empty-state
                compact
                icon="zap"
                :title="__('admin.ai.models_empty_title')"
                :body="__('admin.ai.models_empty')"
            />
        @else
            {{-- The prices are why this screen is opened: whether a mechanical
            task can move to a cheaper model. Stacked inside each row they
            could not be compared, so they are columns read top to bottom. --}}
            <div class="pay-table-wrap">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.ai.columns.model') }}</th>
                            <th class="is-num">{{ __('admin.ai.columns.prompt') }}</th>
                            <th class="is-num">{{ __('admin.ai.columns.cached') }}</th>
                            <th class="is-num">{{ __('admin.ai.columns.completion') }}</th>
                            <th class="is-num">{{ __('admin.ai.columns.effective_from') }}</th>
                            <th>{{ __('admin.ai.fields.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->models as $priced)
                            <tr wire:key="model-{{ $priced->id }}">
                                <td class="is-name" data-label="{{ __('admin.ai.columns.model') }}">
                                    <span class="aiu-name">{{ $priced->label }}</span>
                                    <span class="aiu-note font-mono">{{ $priced->provider }} · {{ $priced->code }}</span>
                                    @if ($priced->source !== null)
                                        <span class="aiu-note">{{ $priced->source }}</span>
                                    @endif
                                </td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai.columns.prompt') }}">{{ $priced->prompt_per_million }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai.columns.cached') }}">{{ $priced->cached_per_million }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai.columns.completion') }}">{{ $priced->completion_per_million }}</td>
                                <td class="is-num font-mono" data-label="{{ __('admin.ai.columns.effective_from') }}">{{ $priced->effective_from->format('d/m/Y') }}</td>
                                <td data-label="{{ __('admin.ai.fields.status') }}">
                                    <span class="status-tag {{ $priced->is_active ? 'is-brand' : 'is-neutral' }}">
                                        {{ $priced->is_active ? __('admin.ai.active') : __('admin.ai.inactive') }}
                                    </span>
                                </td>
                                <td data-label="">
                                    <x-ui.button size="sm" variant="secondary" icon="pencil" wire:click="edit({{ $priced->id }})">
                                        {{ __('admin.ai.edit') }}
                                    </x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>
