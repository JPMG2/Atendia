<?php

use App\Actions\Embeddings\ActivateEmbeddingMigration;
use App\Actions\Embeddings\CancelEmbeddingMigration;
use App\Actions\Embeddings\DiscardEmbeddingBackup;
use App\Actions\Embeddings\ResumeEmbeddingMigration;
use App\Actions\Embeddings\RollBackEmbeddingMigration;
use App\Classes\Main\EmbeddingSpace;
use App\Classes\Main\EmbeddingTables;
use App\Dto\NotificationDto;
use App\Enums\EmbeddingMigrationStatus;
use App\Enums\NotificationType;
use App\Livewire\Forms\Admin\EmbeddingMigrationForm;
use App\Models\AiModel;
use App\Models\EmbeddingMigration;
use App\Services\Knowledge\VectorColumns;
use App\Traits\HasNotifications;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The model the knowledge search runs on, and the way to change it: every
 * vector in the base was made by it, so a change is a process with steps —
 * convert beside the old, switch at once, keep the old until she lets it go.
 */
new class extends Component
{
    use HasNotifications;

    public EmbeddingMigrationForm $form;

    /** Whether the choice of the new model is open. */
    public bool $choosing = false;

    public function mount(): void
    {
        $this->form->setup();
    }

    #[Computed]
    public function active(): EmbeddingSpace
    {
        return EmbeddingSpace::active();
    }

    /** The change in progress: the screen's whole second half hangs from it. */
    #[Computed]
    public function migration(): ?EmbeddingMigration
    {
        return EmbeddingMigration::open();
    }

    /** @return Collection<int, EmbeddingMigration> */
    #[Computed]
    public function history(): Collection
    {
        return EmbeddingMigration::history();
    }

    /** @return array<string, string> */
    #[Computed]
    public function choices(): array
    {
        return AiModel::embeddingChoices();
    }

    /**
     * How far each table is, counted from the base on every look: a bar that
     * says "done" has to be the base saying it.
     *
     * @return list<array{table: string, total: int, converted: int, percent: int}>
     */
    #[Computed]
    public function progress(): array
    {
        $status = $this->migration?->status;

        if ($status !== EmbeddingMigrationStatus::Building && $status !== EmbeddingMigrationStatus::Ready) {
            return [];
        }

        $columns = app(VectorColumns::class);

        return collect(EmbeddingTables::registered())
            ->map(function ($table) use ($columns): array {
                $total = $columns->total($table);
                $converted = min($total, $columns->converted($table));

                return [
                    'table' => $table->table,
                    'total' => $total,
                    'converted' => $converted,
                    'percent' => $total === 0 ? 100 : (int) floor($converted * 100 / $total),
                ];
            })
            ->all();
    }

    public function choose(): void
    {
        $this->authorizeManage();
        $this->form->setup();
        $this->choosing = true;
    }

    public function stopChoosing(): void
    {
        $this->choosing = false;
    }

    public function start(): void
    {
        $this->authorizeManage();

        $notification = $this->form->save();
        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Success) {
            $this->choosing = false;
        }

        $this->refresh();
    }

    public function resume(): void
    {
        $this->run(function (EmbeddingMigration $migration): string {
            $pending = app(ResumeEmbeddingMigration::class)->handle($migration);

            return $pending > 0 ? __('admin.ai.embeddings.resumed', ['count' => $pending]) : __('admin.ai.embeddings.nothing_pending');
        });
    }

    public function activate(): void
    {
        $this->run(function (EmbeddingMigration $migration): string {
            app(ActivateEmbeddingMigration::class)->handle($migration);

            return __('admin.ai.embeddings.activated');
        });
    }

    public function rollBack(): void
    {
        $this->run(function (EmbeddingMigration $migration): string {
            app(RollBackEmbeddingMigration::class)->handle($migration);

            return __('admin.ai.embeddings.rolled_back');
        });
    }

    public function discard(): void
    {
        $this->run(function (EmbeddingMigration $migration): string {
            app(DiscardEmbeddingBackup::class)->handle($migration);

            return __('admin.ai.embeddings.discarded');
        });
    }

    public function cancel(): void
    {
        $this->run(function (EmbeddingMigration $migration): string {
            app(CancelEmbeddingMigration::class)->handle($migration);

            return __('admin.ai.embeddings.cancelled');
        });
    }

    /** One step on the open change: refused with its reason, or done and said. */
    private function run(Closure $step): void
    {
        $this->authorizeManage();

        $migration = EmbeddingMigration::open();

        if ($migration === null) {
            $this->dispatchNotification(new NotificationDto(__('admin.ai.embeddings.errors.nothing_open'), NotificationType::Warning));
            $this->refresh();

            return;
        }

        try {
            $this->dispatchNotification(new NotificationDto($step($migration), NotificationType::Success));
        } catch (DomainException $refusal) {
            $this->dispatchNotification(new NotificationDto(__('admin.ai.embeddings.errors.'.$refusal->getMessage()), NotificationType::Warning));
        }

        $this->refresh();
    }

    /** A change of model is the platform's most expensive decision: the route's key is not the only lock. */
    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('ai.manage'), 403);
    }

    private function refresh(): void
    {
        unset($this->active, $this->migration, $this->history, $this->progress, $this->choices);
    }
};
?>

<div @if ($this->migration?->status === EmbeddingMigrationStatus::Building) wire:poll.5s @endif>
    <x-ui.card class="p-5">
        <p class="sup-meta">{{ __('admin.ai.embeddings.title') }} · {{ __('admin.ai.embeddings.lab') }}</p>

        <div class="aim-lock">
            <span class="aim-lock-icon"><x-icon name="search" :size="18" /></span>
            <div>
                <span class="aiu-name font-mono">{{ $this->active->model }} · {{ $this->active->provider }}</span>
                <span class="aiu-note font-mono">{{ __('admin.ai.embeddings.dimensions', ['count' => $this->active->dimensions]) }}</span>
                <span class="aiu-note">{{ __('admin.ai.embeddings.in_force') }}</span>
            </div>
            @if ($this->migration === null && ! $choosing)
                <x-ui.button size="sm" variant="secondary" wire:click="choose">{{ __('admin.ai.embeddings.change') }}</x-ui.button>
            @endif
        </div>

        @if ($choosing)
            <div class="aim-form">
                <x-catalog.form-row>
                    <x-inputsform.combobox
                        span="long"
                        size="s"
                        name="target"
                        required
                        :label="__('admin.ai.embeddings.target')"
                        :hint="__('admin.ai.embeddings.target_hint')"
                        :placeholder="__('admin.ai.embeddings.target_placeholder')"
                        :value="$form->target"
                        :options="$this->choices"
                        wire:model="form.target"
                    />
                </x-catalog.form-row>

                <div class="aim-actions">
                    <x-ui.button variant="primary" icon="check" wire:click="start" wire:loading.attr="disabled">{{ __('admin.ai.embeddings.start') }}</x-ui.button>
                    <x-ui.button variant="danger" wire:click="stopChoosing">{{ __('admin.ai.cancel') }}</x-ui.button>
                </div>
            </div>
        @endif

        @if ($this->migration !== null)
            @php($migration = $this->migration)
            <div class="aim-steps is-open">
                <div class="aim-head">
                    <strong>{{ __('admin.ai.embeddings.moving', ['from' => $migration->from_model, 'to' => $migration->model, 'count' => $migration->dimensions]) }}</strong>
                    <span class="status-tag {{ $migration->status->tone() }}">{{ $migration->status->label() }}</span>
                </div>

                @if ($migration->status === EmbeddingMigrationStatus::Switched)
                    <p class="sup-body">{{ __('admin.ai.embeddings.kept_hint', ['model' => $migration->from_model]) }}</p>
                @else
                    <p class="sup-body">{{ __('admin.ai.embeddings.'.($migration->status === EmbeddingMigrationStatus::Ready ? 'ready_hint' : 'building_hint')) }}</p>

                    @foreach ($this->progress as $row)
                        <x-ui.usage-meter
                            wire:key="progress-{{ $row['table'] }}"
                            :label="__('admin.ai.embeddings.tables.'.$row['table'])"
                            :text="$row['converted'].' / '.$row['total']"
                            :percent="$row['percent']"
                        />
                    @endforeach
                @endif

                <div class="aim-actions">
                    @if ($migration->status === EmbeddingMigrationStatus::Ready)
                        <x-ui.button
                            variant="primary"
                            icon="check"
                            x-on:click="dialog.confirm({
                                title: {{ \Illuminate\Support\Js::from(__('admin.ai.embeddings.activate_title')) }},
                                message: {{ \Illuminate\Support\Js::from(__('admin.ai.embeddings.activate_body', ['model' => $migration->model])) }},
                                accept: {{ \Illuminate\Support\Js::from(__('admin.ai.embeddings.activate')) }},
                                type: 'warning',
                            }).then((ok) => ok && $wire.activate())"
                        >{{ __('admin.ai.embeddings.activate') }}</x-ui.button>
                    @endif

                    @if ($migration->status === EmbeddingMigrationStatus::Building || $migration->status === EmbeddingMigrationStatus::Ready)
                        <x-ui.button variant="secondary" wire:click="resume">{{ __('admin.ai.embeddings.resume') }}</x-ui.button>
                        <x-ui.button
                            variant="danger"
                            x-on:click="dialog.confirm({
                                title: {{ \Illuminate\Support\Js::from(__('admin.ai.embeddings.cancel_title')) }},
                                message: {{ \Illuminate\Support\Js::from(__('admin.ai.embeddings.cancel_body')) }},
                                accept: {{ \Illuminate\Support\Js::from(__('admin.ai.embeddings.cancel')) }},
                                type: 'warning',
                            }).then((ok) => ok && $wire.cancel())"
                        >{{ __('admin.ai.embeddings.cancel') }}</x-ui.button>
                    @endif

                    @if ($migration->status === EmbeddingMigrationStatus::Switched)
                        <x-ui.button
                            variant="secondary"
                            x-on:click="dialog.confirm({
                                title: {{ \Illuminate\Support\Js::from(__('admin.ai.embeddings.rollback_title')) }},
                                message: {{ \Illuminate\Support\Js::from(__('admin.ai.embeddings.rollback_body', ['model' => $migration->from_model])) }},
                                accept: {{ \Illuminate\Support\Js::from(__('admin.ai.embeddings.rollback')) }},
                                type: 'warning',
                            }).then((ok) => ok && $wire.rollBack())"
                        >{{ __('admin.ai.embeddings.rollback') }}</x-ui.button>
                        <x-ui.button
                            variant="danger"
                            x-on:click="dialog.confirm({
                                title: {{ \Illuminate\Support\Js::from(__('admin.ai.embeddings.discard_title')) }},
                                message: {{ \Illuminate\Support\Js::from(__('admin.ai.embeddings.discard_body', ['model' => $migration->from_model])) }},
                                accept: {{ \Illuminate\Support\Js::from(__('admin.ai.embeddings.discard')) }},
                                type: 'danger',
                            }).then((ok) => ok && $wire.discard())"
                        >{{ __('admin.ai.embeddings.discard') }}</x-ui.button>
                    @endif
                </div>
            </div>
        @endif

        @if ($this->history->isNotEmpty())
            <div class="pay-table-wrap">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.ai.embeddings.history.when') }}</th>
                            <th>{{ __('admin.ai.embeddings.history.change') }}</th>
                            <th>{{ __('admin.ai.embeddings.history.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->history as $past)
                            <tr wire:key="history-{{ $past->id }}">
                                <td class="font-mono" data-label="{{ __('admin.ai.embeddings.history.when') }}">{{ $past->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="font-mono" data-label="{{ __('admin.ai.embeddings.history.change') }}">{{ $past->from_model }} → {{ $past->model }} · {{ $past->dimensions }}</td>
                                <td data-label="{{ __('admin.ai.embeddings.history.status') }}"><span class="status-tag {{ $past->status->tone() }}">{{ $past->status->label() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>
