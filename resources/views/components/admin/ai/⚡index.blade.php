<?php

use App\Actions\Admin\StartModelEval;
use App\Classes\Main\AiEvalResults;
use App\Classes\Main\TaskCosts;
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

    /** This month's spend by task, read once per render. */
    #[Computed]
    public function costs(): TaskCosts
    {
        return new TaskCosts(now());
    }

    /**
     * The codes that have a price in the catalog. A model running without one
     * is spending money nobody can value.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function priced(): array
    {
        return AiModel::providersByCode();
    }

    /** The battery run in progress, if any: the banner and the poll hang from it. */
    #[Computed]
    public function probing(): ?object
    {
        return AiEvalResults::running();
    }

    /** Measures a catalog model against the nine background jobs; the screen confirmed the cost first. */
    public function probe(string $code, StartModelEval $start): void
    {
        try {
            $start->handle($code);
            $message = __('admin.ai.eval.started', ['model' => $code]);
            $type = NotificationType::Info;
        } catch (\DomainException $e) {
            $message = __('admin.ai.eval.errors.'.$e->getMessage());
            $type = NotificationType::Warning;
        }

        unset($this->probing);
        $this->dispatchNotification(new NotificationDto($message, $type));
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
                        <div class="bp-card-head"><h2>{{ __("admin.ai.groups.{$group}.title") }}</h2></div>
                        <p class="bp-card-sub">{{ __("admin.ai.groups.{$group}.sub") }}</p>

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
                                        <th class="is-num">{{ __('admin.ai.cost.column') }}</th>
                                        <th class="is-pick">{{ __('admin.ai.model') }}</th>
                                        <th class="is-pick">{{ __('admin.ai.fallback') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->groups[$group] as $row)
                                        <tr wire:key="task-{{ $row['id'] }}">
                                            <td class="is-name is-key" data-label="{{ __('admin.ai.columns.task') }}">
                                                <span class="aiu-name">
                                                    {{ $row['label'] }}
                                                    @if ($row['needs'] !== AiCapability::Text)
                                                        <span class="status-tag is-neutral">{{ $row['needs']->label() }}</span>
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="is-running" data-label="{{ __('admin.ai.running') }}">
                                                {{-- One line per model, not per key: two keys on the same model
                                                are said once, with the count and the names on hover. --}}
                                                @forelse (collect($row['running'])->groupBy(fn ($code) => $code ?? '', true) as $code => $connections)
                                                    @php($keys = $connections->keys()->map(fn ($key) => $this->labels[$key] ?? $key))
                                                    <span class="aim-run" wire:key="run-{{ $row['id'] }}-{{ $code }}">
                                                        <span class="aim-run-code">{{ $code !== '' ? $code : __('admin.ai.provider_default') }}</span>
                                                        <span class="aim-run-key" title="{{ $keys->implode(', ') }}">{{ $keys->count() > 1 ? __('admin.ai.running_many', ['count' => $keys->count()]) : $keys->first() }}</span>
                                                        {{-- It runs, so it spends; with no catalog price nobody can say how much. --}}
                                                        @unless (array_key_exists($code, $this->priced))
                                                            <span class="status-tag is-warning" title="{{ __('admin.ai.unpriced_hint') }}">{{ __('admin.ai.unpriced') }}</span>
                                                        @endunless
                                                    </span>
                                                @empty
                                                    <span class="text-muted">{{ __('admin.ai.no_agent') }}</span>
                                                @endforelse
                                            </td>
                                            @php($spent = $row['needs'] === AiCapability::Transcription ? null : $this->costs->actual($row['key']))
                                            <td class="is-num font-mono" data-label="{{ __('admin.ai.cost.column') }}">
                                                @if ($spent === null)
                                                    <span class="text-muted">—</span>
                                                @elseif ($spent->cost === null)
                                                    <span class="status-tag is-warning">{{ __('admin.ai.unpriced') }}</span>
                                                @else
                                                    {{ number_format($spent->cost, 4, ',', '.') }}
                                                @endif
                                                @if ($spent !== null)
                                                    <span class="aiu-note">{{ trans_choice('admin.ai.cost.calls', $spent->calls, ['count' => $spent->calls]) }}</span>
                                                @endif
                                            </td>
                                            <td class="is-pick" data-label="{{ __('admin.ai.model') }}">
                                                @if ($this->choices[$row['needs']->value] === [])
                                                    <span class="text-muted">{{ __('admin.ai.no_model_for_task') }}</span>
                                                @else
                                                    <x-inputsform.combobox
                                                        size="s"
                                                        :name="'model-'.$row['id']"
                                                        :options="$this->choices[$row['needs']->value]"
                                                        :value="$this->tasks->model[$row['id']] ?? ''"
                                                        :placeholder="__('admin.ai.unassigned')"
                                                        :aria-label="__('admin.ai.model')"
                                                        wire:model.live="tasks.model.{{ $row['id'] }}"
                                                    />

                                                    {{-- The same tokens of this month at the picked model's price:
                                                    a reading of what happened, not a forecast. --}}
                                                    @php($candidate = explode('|', (string) ($this->tasks->model[$row['id']] ?? ''))[1] ?? '')
                                                    @if ($spent?->cost !== null && $candidate !== '')
                                                        @php($alt = $this->costs->with($row['key'], $candidate))
                                                        @if ($alt !== null)
                                                            @php($delta = $spent->cost > 0 ? round(($alt - $spent->cost) / $spent->cost * 100) : 0)
                                                            <span class="aim-est {{ $delta < 0 ? 'is-less' : ($delta > 0 ? 'is-more' : '') }}">
                                                                {{ __('admin.ai.cost.with', ['cost' => number_format($alt, 4, ',', '.'), 'delta' => ($delta > 0 ? '+' : '').$delta]) }}
                                                            </span>
                                                        @endif
                                                    @endif
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
                                                        :value="$this->tasks->fallback[$row['id']] ?? ''"
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

            {{-- Embeddings are not assigned like the rest: every vector in the
            base was made by this model, so changing it is a process. --}}
            <livewire:ai.embedding-space />
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

                {{-- The poll lives only while a run does: a page that polls forever is a bill. --}}
                @if ($this->probing !== null)
                    <div wire:poll.5s class="mb-3">
                        <x-ui.alert variant="info" icon="play">
                            {{ __('admin.ai.eval.running', ['model' => $this->probing->code, 'since' => $this->probing->since->format('H:i')]) }}
                        </x-ui.alert>
                    </div>
                @endif

                {{-- How a model earns its score: said where the score is read. --}}
                <details class="aim-how">
                    <summary>{{ __('admin.ai.eval.how_title') }}</summary>
                    <p class="aiu-foot">
                        <span>{{ __('admin.ai.eval.how') }}</span>
                        <span class="font-mono">EVAL_CONNECTION=&lt;{{ __('admin.ai.eval.key') }}&gt; EVAL_MODEL=&lt;{{ __('admin.ai.eval.model') }}&gt; ./vendor/bin/pest tests/Eval</span>
                    </p>
                </details>

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
                            @elseif ($model->capability === AiCapability::Embedding->value)
                                {{-- An embedding model reads text and returns a vector: it bills the input only, and its length is part of what it is. --}}
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
                                    :label="__('admin.ai.fields.dimensions')"
                                    required
                                    name="dimensions"
                                    type="number"
                                    min="64"
                                    max="2000"
                                    :hint="__('admin.ai.fields.dimensions_hint')"
                                    wire:model="model.dimensions"
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
                        <table class="pay-table aim-models">
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
                                        <td class="is-name is-key" data-label="{{ __('admin.ai.columns.model') }}">
                                            {{-- Where the price was read is the audit trail of the row:
                                            on hover here, in full in the edit form. --}}
                                            <span class="aiu-name" @if ($priced->source !== null) title="{{ $priced->source }}" @endif>{{ $priced->label }}</span>
                                            <span class="aiu-note font-mono">{{ $priced->provider }} · {{ $priced->code }}</span>
                                            {{-- The battery measures conversation, so a voice model has no score to show. --}}
                                            @if ($priced->capability->serves(AiCapability::Text) && in_array($priced->id, $this->newestRows, true))
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
                                        @elseif (! $priced->capability->billsOutput())
                                            <td class="is-num font-mono" data-label="{{ __('admin.ai.columns.prompt') }}">{{ $priced->prompt_per_million }}</td>
                                            <td class="font-mono" colspan="2" data-label="{{ __('admin.ai.fields.dimensions') }}">{{ __('admin.ai.embeddings.dimensions', ['count' => $priced->dimensions]) }}</td>
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
                                            <div class="aim-row-actions">
                                                {{-- Only what answers in text is measured by the battery, and the
                                                dialog says what it costs before anything is spent. --}}
                                                @if ($priced->capability->serves(AiCapability::Text) && in_array($priced->id, $this->newestRows, true))
                                                    <x-ui.icon-button
                                                        icon="play"
                                                        size="sm"
                                                        variant="ghost"
                                                        :label="__('admin.ai.eval.run')"
                                                        :disabled="$this->probing !== null"
                                                        x-on:click="dialog.confirm({
                                                            title: {{ Illuminate\Support\Js::from(__('admin.ai.eval.confirm_title', ['model' => $priced->code])) }},
                                                            message: {{ Illuminate\Support\Js::from(__('admin.ai.eval.confirm_body')) }},
                                                            accept: {{ Illuminate\Support\Js::from(__('admin.ai.eval.confirm_accept')) }},
                                                            type: 'warning',
                                                        }).then(ok => ok && $wire.probe({{ Illuminate\Support\Js::from($priced->code) }}))"
                                                    />
                                                @endif
                                                <x-ui.icon-button icon="pencil" size="sm" variant="secondary" :label="__('admin.ai.edit')" wire:click="edit({{ $priced->id }})" />
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

        {{-- Connections: read-only, because the key itself never travels through a screen. --}}
        <div x-show="tab === 'connections'" x-cloak wire:key="pane-connections">
            <x-ui.card class="p-5">
                <div class="bp-card-head"><h2>{{ __('admin.ai.connections.title') }}</h2></div>
                <p class="bp-card-sub">{{ __('admin.ai.connections.sub') }}</p>

                {{-- Only the keys that exist are rows: fifteen "Sin clave" lines
                were most of the tab and told her one thing, once. --}}
                @php($withKey = $this->connections->where('configured', true))
                @php($withoutKey = $this->connections->where('configured', false))

                <div class="pay-table-wrap">
                    <table class="pay-table aim-conns">
                        <thead>
                            <tr>
                                <th>{{ __('admin.ai.connections.title') }}</th>
                                <th>{{ __('admin.ai.connections.lab') }}</th>
                                <th>{{ __('admin.ai.connections.key') }}</th>
                                <th class="is-num">{{ __('admin.ai.connections.models') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($withKey as $connection)
                                <tr wire:key="connection-{{ $connection['key'] }}">
                                    <td class="is-name is-key" data-label="{{ __('admin.ai.connections.title') }}">
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

                @if ($withoutKey->isNotEmpty())
                    <p class="aiu-foot">{{ __('admin.ai.connections.missing_list', ['count' => $withoutKey->count(), 'names' => $withoutKey->pluck('label')->implode(', ')]) }}</p>
                @endif
            </x-ui.card>
        </div>
    </x-ui.tabs>
</div>
