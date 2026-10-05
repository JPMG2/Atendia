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

    public function assign(int $taskId): void
    {
        $this->dispatchNotification($this->tasks->save($taskId));

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
            <div class="aim-rows">
                @foreach ($this->rows as $row)
                    <div class="aim-row" wire:key="task-{{ $row['id'] }}">
                        <span class="aim-task">
                            <span class="aim-task-head">
                                <span class="aim-task-name">{{ $row['label'] }}</span>
                                @if ($row['mechanical'])
                                    <span class="status-tag is-neutral">{{ __('admin.ai.mechanical') }}</span>
                                @endif
                            </span>
                            <span class="sup-meta">
                                <span>{{ __('admin.ai.running') }}:</span>
                                @forelse ($row['running'] as $provider => $code)
                                    <span class="font-mono">{{ $provider }} · {{ $code ?? __('admin.ai.provider_default') }}</span>
                                @empty
                                    <span class="text-muted">{{ __('admin.ai.no_agent') }}</span>
                                @endforelse
                            </span>
                        </span>

                        <span class="aim-pick">
                            <x-inputsform.combobox
                                size="s"
                                span="text"
                                :label="__('admin.ai.model')"
                                :name="'model-'.$row['id']"
                                :options="$this->options"
                                :placeholder="__('admin.ai.unassigned')"
                                wire:model="tasks.model.{{ $row['id'] }}"
                            />

                            <x-inputsform.combobox
                                size="s"
                                span="text"
                                :label="__('admin.ai.fallback')"
                                :name="'fallback-'.$row['id']"
                                :options="$this->fallbackOptions($row['id'])"
                                :placeholder="__('admin.ai.no_fallback')"
                                wire:model="tasks.fallback.{{ $row['id'] }}"
                            />

                            <x-ui.button size="sm" variant="secondary" icon="check" wire:click="assign({{ $row['id'] }})">
                                {{ __('admin.ai.save') }}
                            </x-ui.button>
                        </span>
                    </div>
                @endforeach
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
            <div class="aim-rows">
                @foreach ($this->models as $priced)
                    <div class="aim-row" wire:key="model-{{ $priced->id }}">
                        <span class="aim-task">
                            <span class="aim-task-head">
                                <span class="aim-task-name">{{ $priced->label }}</span>
                                <span class="status-tag {{ $priced->is_active ? 'is-brand' : 'is-neutral' }}">
                                    {{ $priced->is_active ? __('admin.ai.active') : __('admin.ai.inactive') }}
                                </span>
                            </span>
                            <span class="sup-meta">
                                <span class="font-mono">{{ $priced->provider }} · {{ $priced->code }}</span>
                                @if ($priced->source !== null)
                                    <span>{{ $priced->source }}</span>
                                @endif
                            </span>
                        </span>

                        <span class="aim-prices sup-meta">
                            <span>{{ __('admin.ai.columns.prompt') }} <strong class="font-mono">{{ $priced->prompt_per_million }}</strong></span>
                            <span>{{ __('admin.ai.columns.cached') }} <strong class="font-mono">{{ $priced->cached_per_million }}</strong></span>
                            <span>{{ __('admin.ai.columns.completion') }} <strong class="font-mono">{{ $priced->completion_per_million }}</strong></span>
                            <span>{{ __('admin.ai.columns.effective_from') }} <strong class="font-mono">{{ $priced->effective_from->format('d/m/Y') }}</strong></span>
                        </span>

                        <span class="aim-side">
                            <x-ui.button size="sm" variant="secondary" icon="pencil" wire:click="edit({{ $priced->id }})">
                                {{ __('admin.ai.edit') }}
                            </x-ui.button>
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>
</div>
