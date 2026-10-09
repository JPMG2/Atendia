<?php

use App\Classes\Main\AuditTrail;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Who did what.
 *
 * It is opened to answer about a PERSON — "qué hizo Rocío" — never about a
 * table, so the filter is the person. And it opens on the strong changes:
 * the day it was built, 757 of 848 entries were the assistant filing
 * suggestions, which buries the eighteen businesses somebody deleted.
 */
new class extends Component
{
    #[Url(as: 'quien', except: '')]
    public string $causer = '';

    #[Url(as: 'fuertes', except: true)]
    public bool $onlyStrong = true;

    /** @return Collection<int, \Spatie\Activitylog\Models\Activity> */
    #[Computed]
    public function entries(): Collection
    {
        return AuditTrail::rows($this->causer, $this->onlyStrong);
    }

    #[Computed]
    public function total(): int
    {
        return AuditTrail::total();
    }

    /** @return array<string, string> */
    #[Computed]
    public function causerOptions(): array
    {
        return AuditTrail::causerOptions();
    }

    /** The tab is copy: a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('admin.audit.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('admin.audit.title')" :sub="__('admin.audit.sub')">
        <x-ui.result-count :shown="$this->entries->count()" :total="$this->total" noun="admin.audit.count" />
    </x-ui.page-head>

    <x-ui.card class="p-5">
        <x-catalog.form-row>
            <x-inputsform.combobox
                size="s"
                span="text"
                :label="__('admin.audit.who')"
                name="causer"
                :options="$this->causerOptions"
                :value="$causer"
                :placeholder="__('admin.audit.anybody')"
                wire:model.live="causer"
            />

            <x-inputsform.switch-field
                span="short"
                :label="__('admin.audit.strong_only')"
                name="onlyStrong"
                :on="__('admin.audit.strong_on')"
                :off="__('admin.audit.strong_off')"
                wire:model.live="onlyStrong"
            />
        </x-catalog.form-row>

        @if ($this->entries->isEmpty())
            <p class="text-muted text-sm">{{ __('admin.audit.empty') }}</p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
                    <thead>
                        <tr>
                            <th>{{ __('admin.audit.when') }}</th>
                            <th>{{ __('admin.audit.who') }}</th>
                            <th>{{ __('admin.audit.what') }}</th>
                            <th>{{ __('admin.audit.on') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->entries as $entry)
                            @php($strong = App\Classes\Main\AuditTrail::isStrong($entry))

                            {{-- Painted only while the ordinary movements are on
                            screen too: with the filter on everything is strong,
                            and painting every row marks nothing. The red tag
                            carries the weight either way. --}}
                            <tr wire:key="act-{{ $entry->id }}" @class(['is-over' => $strong && ! $onlyStrong])>
                                <td class="is-name font-mono" data-label="{{ __('admin.audit.when') }}">
                                    {{ $entry->created_at?->format('d/m/Y H:i') }}
                                </td>

                                <td class="is-name" data-label="{{ __('admin.audit.who') }}">
                                    @if ($entry->causer === null)
                                        {{-- The assistant and the console act too, and saying
                                        so is truer than leaving the column blank. --}}
                                        <span class="text-muted">{{ __('admin.audit.system') }}</span>
                                    @else
                                        <span class="aiu-name">{{ $entry->causer->name }}</span>
                                        <span class="aiu-note font-mono">{{ $entry->causer->email }}</span>
                                    @endif
                                </td>

                                <td class="is-name is-key" data-label="{{ __('admin.audit.what') }}">
                                    <div class="audit-what">
                                        <span @class(['status-tag', 'is-danger' => $strong, 'is-neutral' => ! $strong])>
                                            {{ __('admin.audit.actions.'.$entry->description) }}
                                        </span>

                                        @if ($entry->log_name === App\Classes\Main\AuditTrail::ACCESS)
                                            @php($names = (array) ($entry->properties['names'] ?? []))
                                            {{-- An audit read a year from now has to say WHICH key
                                            changed hands, but 39 names in the row buried the next
                                            one: the count leads, the names open on demand. --}}
                                            @if (count($names) > 0)
                                                <details class="audit-names">
                                                    <summary>{{ trans_choice('admin.audit.names_count', count($names), ['count' => count($names)]) }}</summary>
                                                    <span class="aiu-note font-mono">{{ implode(', ', $names) }}</span>
                                                </details>
                                            @endif
                                        @endif
                                    </div>
                                </td>

                                <td class="is-name" data-label="{{ __('admin.audit.on') }}">
                                    {{ App\Classes\Main\AuditTrail::subjectOf($entry) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>
