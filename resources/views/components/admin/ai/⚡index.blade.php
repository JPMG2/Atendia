<?php

use App\Classes\Main\AiEvalResults;
use App\Dto\NotificationDto;
use App\Enums\AiCapability;
use App\Enums\NotificationType;
use App\Livewire\Forms\Admin\AiModelForm;
use App\Livewire\Forms\Admin\AiTaskAssignmentForm;
use App\Models\AiConnection;
use App\Models\AiModel;
use App\Models\AiTask;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Which model answers each kind of work, through which key, and what each
 * model costs.
 *
 * The assignment lives in `ai_tasks`: with no row a task runs on the pair
 * written in its agent, which is why every row says what runs TODAY instead
 * of leaving the column empty. The keys themselves live in `.env`.
 */
new class extends Component
{
    use HasNotifications;

    public AiTaskAssignmentForm $tasks;

    public AiModelForm $model;

    /** Whether the model form is open; it opens above the list, replacing nothing. */
    public bool $editing = false;

    /** @var array<string, string> The "connection|code" picked at the head of each group. */
    public array $bulk = [];

    public function mount(): void
    {
        $this->tasks->setup();
        $this->model->setup();
    }

    /** @return Collection<string, Collection<int, array<string, mixed>>> Tasks by kind of work. */
    #[Computed]
    public function groups(): Collection
    {
        return AiTask::board()->groupBy('group');
    }

    /** @return EloquentCollection<int, AiModel> */
    #[Computed]
    public function models(): EloquentCollection
    {
        return AiModel::board();
    }

    /**
     * What each battery measured, by suite and model code. Read from files the
     * console run leaves: the screen never starts a run, because it spends real tokens.
     *
     * @return array<string, array<string, object>>
     */
    #[Computed]
    public function evals(): array
    {
        return [
            AiEvalResults::CONVERSATION => AiEvalResults::measured(AiEvalResults::CONVERSATION),
            AiEvalResults::MECHANICAL => AiEvalResults::measured(AiEvalResults::MECHANICAL),
        ];
    }

    /**
     * The row that carries a model's score: its newest price. A model with
     * three prices would otherwise repeat the same score three times.
     *
     * @return list<int>
     */
    #[Computed]
    public function newestRows(): array
    {
        return $this->models->unique('code')->pluck('id')->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function connections(): Collection
    {
        return AiConnection::board();
    }

    /** @return array<string, string> */
    #[Computed]
    public function labels(): array
    {
        return AiConnection::labels();
    }

    /** @return array<string, string> What a model can be published under: labs with a key. */
    #[Computed]
    public function drivers(): array
    {
        return AiConnection::drivers();
    }

    /**
     * What each select offers, by the capability the task needs.
     *
     * @return array<string, array<string, string>>
     */
    #[Computed]
    public function choices(): array
    {
        return collect(AiCapability::cases())
            ->mapWithKeys(fn (AiCapability $capability): array => [$capability->value => AiModel::assignable($capability)])
            ->all();
    }

    /**
     * The fallback can only go through another key: on the ladder a second
     * model behind the same connection would replace the first instead of
     * following it.
     *
     * @return array<string, string>
     */
    public function fallbackChoices(int $taskId, AiCapability $needs): array
    {
        $connection = explode('|', (string) ($this->tasks->model[$taskId] ?? ''))[0];

        return AiModel::assignable($needs, $connection === '' ? null : $connection);
    }

    /**
     * One choice for every task of a group, written into the selects and NOT
     * saved: it only spares nine clicks, and the Guardar below still decides.
     * A task whose work the model cannot do keeps what it had, and a fallback
     * that now sits behind the same key is cleared, or Guardar would refuse it.
     */
    public function applyToGroup(string $group): void
    {
        $pair = (string) ($this->bulk[$group] ?? '');

        if ($pair === '') {
            return;
        }

        $applied = 0;
        $kept = 0;

        foreach (AiTask::board()->where('group', $group) as $row) {
            if (! array_key_exists($pair, $this->choices[$row['needs']->value] ?? [])) {
                $kept++;

                continue;
            }

            $this->tasks->model[$row['id']] = $pair;

            if (explode('|', (string) ($this->tasks->fallback[$row['id']] ?? ''))[0] === explode('|', $pair)[0]) {
                $this->tasks->fallback[$row['id']] = '';
            }

            $applied++;
        }

        $this->dispatchNotification(new NotificationDto(
            trans_choice('admin.ai.bulk.applied', $applied, ['count' => $applied]).($kept > 0 ? ' '.trans_choice('admin.ai.bulk.kept', $kept, ['count' => $kept]) : ''),
            $applied > 0 ? NotificationType::Info : NotificationType::Warning,
        ));
    }

    public function assign(): void
    {
        $this->dispatchNotification($this->tasks->saveAll());

        unset($this->groups);
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
        unset($this->models, $this->choices, $this->groups, $this->connections, $this->drivers);
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

    <x-ui.tabs
        :default="'assign'"
        :tabs="[
            ['value' => 'assign', 'label' => __('admin.ai.tabs.assign')],
            ['value' => 'catalog', 'label' => __('admin.ai.tabs.catalog'), 'badge' => $this->models->unique('code')->count()],
            ['value' => 'connections', 'label' => __('admin.ai.tabs.connections'), 'badge' => __('admin.ai.tabs.connections_badge', ['ready' => $this->connections->where('configured', true)->count(), 'total' => $this->connections->count()])],
        ]"
    >
        {{-- Assignment: the kind of work is the unit, the key is part of the choice. --}}
        <div x-show="tab === 'assign'" wire:key="pane-assign">
            @if ($this->groups->isEmpty())
                <x-ui.card class="mb-3 p-5">
                    <x-ui.empty-state
                        compact
                        icon="bot"
                        :title="__('admin.ai.tasks_empty_title')"
                        :body="__('admin.ai.tasks_empty')"
                    />
                </x-ui.card>
            @else
                @foreach (['conversation', 'background', 'audio'] as $group)
                    @continue (! $this->groups->has($group))

                    <x-ui.card class="aim-group mb-3 p-5" wire:key="group-{{ $group }}">
                        <p class="sup-meta">{{ __("admin.ai.groups.{$group}.title") }} · {{ __("admin.ai.groups.{$group}.sub") }}</p>

                        {{-- Nine mechanical tasks are one decision most days: one choice
                        for the whole group, which the Guardar below still confirms. --}}
                        @if ($this->groups[$group]->count() > 1 && $this->choices['text'] !== [])
                            <x-catalog.form-row class="aim-bulk">
                                <x-inputsform.combobox
                                    size="s"
                                    span="text"
                                    :label="__('admin.ai.bulk.label')"
                                    name="bulk-{{ $group }}"
                                    :options="$this->choices['text']"
                                    :placeholder="__('admin.ai.bulk.placeholder')"
                                    wire:model="bulk.{{ $group }}"
                                />

                                <x-ui.button size="sm" variant="secondary" icon="check" wire:click="applyToGroup('{{ $group }}')">
                                    {{ trans_choice('admin.ai.bulk.apply', $this->groups[$group]->count(), ['count' => $this->groups[$group]->count()]) }}
                                </x-ui.button>
                            </x-catalog.form-row>
                        @endif

                        {{-- A table, so "Modelo" and "Respaldo" are said once at the top
                        instead of once per task, and the whole assignment is one
                        decision with one Guardar under it. --}}
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
                                    @foreach ($this->groups[$group] as $row)
                                        <tr wire:key="task-{{ $row['id'] }}">
                                            <td class="is-name" data-label="{{ __('admin.ai.columns.task') }}">
                                                <span class="aiu-name">
                                                    {{ $row['label'] }}
                                                    @if ($row['needs'] !== AiCapability::Text)
                                                        <span class="status-tag is-neutral">{{ $row['needs']->label() }}</span>
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="is-running font-mono" data-label="{{ __('admin.ai.running') }}">
                                                @forelse ($row['running'] as $connection => $code)
                                                    {{ $this->labels[$connection] ?? $connection }} · {{ $code ?? __('admin.ai.provider_default') }}
                                                @empty
                                                    <span class="text-muted">{{ __('admin.ai.no_agent') }}</span>
                                                @endforelse
                                            </td>
                                            <td class="is-pick" data-label="{{ __('admin.ai.model') }}">
                                                @if ($this->choices[$row['needs']->value] === [])
                                                    <span class="text-muted">{{ __('admin.ai.no_model_for_task') }}</span>
                                                @else
                                                    <x-inputsform.combobox
                                                        size="s"
                                                        :name="'model-'.$row['id']"
                                                        :options="$this->choices[$row['needs']->value]"
                                                        :placeholder="__('admin.ai.unassigned')"
                                                        :aria-label="__('admin.ai.model')"
                                                        wire:model="tasks.model.{{ $row['id'] }}"
                                                    />
                                                @endif
                                            </td>
                                            <td class="is-pick" data-label="{{ __('admin.ai.fallback') }}">
                                                @php($fallbacks = $this->fallbackChoices($row['id'], $row['needs']))
                                                @if ($fallbacks === [])
                                                    <span class="text-muted">{{ __('admin.ai.no_fallback_available') }}</span>
                                                @else
                                                    <x-inputsform.combobox
                                                        size="s"
                                                        :name="'fallback-'.$row['id']"
                                                        :options="$fallbacks"
                                                        :placeholder="__('admin.ai.no_fallback')"
                                                        :aria-label="__('admin.ai.fallback')"
                                                        wire:model="tasks.fallback.{{ $row['id'] }}"
                                                    />
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-ui.card>
                @endforeach

                <div class="aim-actions mb-3">
                    <x-ui.button variant="primary" icon="check" wire:click="assign">
                        {{ __('admin.ai.save') }}
                    </x-ui.button>
                    <span class="sup-age">{{ __('admin.ai.save_hint') }}</span>
                </div>
            @endif

            {{-- Embeddings are read, not assigned: every vector in the base was
            made by this model, so it is a migration and not a select. --}}
            <x-ui.card class="p-5" x-data="{ how: false }">
                <p class="sup-meta">{{ __('admin.ai.embeddings.title') }} · {{ __('admin.ai.embeddings.lab') }}</p>

                <div class="aim-lock">
                    <span class="aim-lock-icon"><x-icon name="lock" :size="18" /></span>
                    <div>
                        <span class="aiu-name font-mono">{{ config('rag.embedding.model') }} · {{ config('ai.default_for_embeddings') }}</span>
                        <span class="aiu-note font-mono">{{ __('admin.ai.embeddings.dimensions', ['count' => config('rag.embedding.dimensions')]) }}</span>
                        <span class="aiu-note">{{ __('admin.ai.embeddings.locked') }}</span>
                    </div>
                    <x-ui.button size="sm" variant="secondary" x-on:click="how = ! how">
                        {{ __('admin.ai.embeddings.how') }}
                    </x-ui.button>
                </div>

                <div class="aim-steps" x-show="how" x-cloak>
                    <strong>{{ __('admin.ai.embeddings.steps_title') }}</strong>
                    <ol>
                        <li>{{ __('admin.ai.embeddings.step_1') }}</li>
                        <li>{{ __('admin.ai.embeddings.step_2') }}</li>
                        <li>{{ __('admin.ai.embeddings.step_3') }}</li>
                    </ol>
                </div>
            </x-ui.card>
        </div>

        {{-- Catalog: what the platform may call, and what it cost from a day on. --}}
        <div x-show="tab === 'catalog'" x-cloak wire:key="pane-catalog">
            <x-ui.card class="p-5">
                <div class="aim-head">
                    <p class="sup-meta">{{ __('admin.ai.models') }} · {{ __('admin.ai.models_sub') }}</p>
                    <x-ui.button size="sm" variant="primary" icon="plus" wire:click="create">
                        {{ __('admin.ai.new') }}
                    </x-ui.button>
                </div>

                {{-- How a model earns its score: said where the score is read. --}}
                <p class="aiu-foot">
                    <span>{{ __('admin.ai.eval.how') }}</span>
                    <span class="font-mono">EVAL_CONNECTION=&lt;{{ __('admin.ai.eval.key') }}&gt; EVAL_MODEL=&lt;{{ __('admin.ai.eval.model') }}&gt; ./vendor/bin/pest tests/Eval</span>
                </p>

                @if ($editing)
                    <form wire:submit="store" class="aim-form">
                        <x-catalog.form-row>
                            <x-inputsform.combobox
                                span="text"
                                :label="__('admin.ai.fields.provider')"
                                required
                                name="provider"
                                :options="$this->drivers"
                                :value="$model->provider"
                                wire:model="model.provider"
                            />

                            <x-inputsform.combobox
                                span="text"
                                :label="__('admin.ai.fields.capability')"
                                required
                                name="capability"
                                :options="AiCapability::options()"
                                :value="$model->capability"
                                wire:model.live="model.capability"
                            />

                            <x-inputsform.input
                                span="long"
                                :label="__('admin.ai.fields.label')"
                                required
                                name="label"
                                maxlength="60"
                                wire:model="model.label"
                            />
                        </x-catalog.form-row>

                        <x-catalog.form-row>
                            <x-inputsform.input
                                span="text"
                                :label="__('admin.ai.fields.code')"
                                required
                                name="code"
                                :hint="__('admin.ai.fields.code_hint')"
                                maxlength="80"
                                wire:model="model.code"
                            />

                            <x-inputsform.datepicker
                                span="text"
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
                            @if ($model->capability === AiCapability::Transcription->value)
                                <x-inputsform.input
                                    span="short"
                                    :label="__('admin.ai.fields.per_minute')"
                                    required
                                    name="per_minute"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    wire:model="model.per_minute"
                                />
                            @else
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
                            @endif

                            <x-inputsform.input
                                span="long"
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
                    {{-- The prices are why this tab is opened: whether a mechanical
                    task can move to a cheaper model. Stacked inside each row they
                    could not be compared, so they are columns read top to bottom. --}}
                    <div class="pay-table-wrap">
                        <table class="pay-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.ai.columns.model') }}</th>
                                    <th>{{ __('admin.ai.columns.capability') }}</th>
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
                                            {{-- The battery measures conversation, so a voice model has no score to show. --}}
                                            @if (! $priced->capability->billsPerMinute() && in_array($priced->id, $this->newestRows, true))
                                                @php($scores = collect($this->evals)->map(fn (array $suite): ?object => $suite[$priced->code] ?? null)->filter())
                                                @forelse ($scores as $suite => $score)
                                                    <span class="status-tag {{ $score->passed === $score->total ? 'is-brand' : 'is-warning' }}">
                                                        {{ __('admin.ai.eval.'.$suite, ['passed' => $score->passed, 'total' => $score->total, 'date' => $score->ranAt->format('d/m')]) }}
                                                    </span>
                                                @empty
                                                    <span class="status-tag is-neutral">{{ __('admin.ai.eval.unmeasured') }}</span>
                                                @endforelse
                                            @endif
                                        </td>
                                        <td data-label="{{ __('admin.ai.columns.capability') }}">
                                            <span class="status-tag is-neutral">{{ $priced->capability->label() }}</span>
                                        </td>
                                        @if ($priced->capability->billsPerMinute())
                                            <td class="is-num font-mono" colspan="3" data-label="{{ __('admin.ai.columns.per_minute') }}">{{ $priced->per_minute }} {{ __('admin.ai.fields.per_minute_unit') }}</td>
                                        @else
                                            <td class="is-num font-mono" data-label="{{ __('admin.ai.columns.prompt') }}">{{ $priced->prompt_per_million }}</td>
                                            <td class="is-num font-mono" data-label="{{ __('admin.ai.columns.cached') }}">{{ $priced->cached_per_million }}</td>
                                            <td class="is-num font-mono" data-label="{{ __('admin.ai.columns.completion') }}">{{ $priced->completion_per_million }}</td>
                                        @endif
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

        {{-- Connections: read-only, because the key itself never travels through a screen. --}}
        <div x-show="tab === 'connections'" x-cloak wire:key="pane-connections">
            <x-ui.card class="p-5">
                <p class="sup-meta">{{ __('admin.ai.connections.title') }} · {{ __('admin.ai.connections.sub') }}</p>

                <div class="pay-table-wrap">
                    <table class="pay-table">
                        <thead>
                            <tr>
                                <th>{{ __('admin.ai.connections.title') }}</th>
                                <th>{{ __('admin.ai.connections.lab') }}</th>
                                <th>{{ __('admin.ai.connections.key') }}</th>
                                <th class="is-num">{{ __('admin.ai.connections.models') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->connections as $connection)
                                <tr wire:key="connection-{{ $connection['key'] }}">
                                    <td class="is-name" data-label="{{ __('admin.ai.connections.title') }}">
                                        <span class="aiu-name">{{ $connection['label'] }}</span>
                                        <span class="aiu-note font-mono">{{ $connection['key'] }}</span>
                                    </td>
                                    <td class="font-mono" data-label="{{ __('admin.ai.connections.lab') }}">{{ $connection['driver'] }}</td>
                                    <td data-label="{{ __('admin.ai.connections.key') }}">
                                        <span class="status-tag {{ $connection['configured'] ? 'is-brand' : 'is-neutral' }}">
                                            {{ $connection['configured'] ? __('admin.ai.connections.ready') : __('admin.ai.connections.missing') }}
                                        </span>
                                    </td>
                                    <td class="is-num font-mono" data-label="{{ __('admin.ai.connections.models') }}">{{ $connection['models'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </x-ui.tabs>
</div>
