<?php

use App\Livewire\Forms\Admin\PlatformSettingForm;
use App\Models\PlatformSetting;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The behaviour knobs she may turn without a deploy.
 *
 * Grouped by the TASK each one belongs to and not by the config file they
 * come from: she opens this screen thinking "the digest goes out too late",
 * never "atendia.schedule.whatsapp_digest".
 */
new class extends Component
{
    use HasNotifications;

    public PlatformSettingForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    /**
     * By urgency of use, not alphabetically: the hours the automatic sends go
     * out are what she opens this screen for, and sorting by name buried them
     * under "analysis" at the bottom of the page.
     *
     * @return Collection<string, Collection<int, PlatformSetting>>
     */
    #[Computed]
    public function groups(): Collection
    {
        $order = ['sends', 'analysis', 'handoff', 'support', 'billing', 'costs', 'referral'];

        return PlatformSetting::board()
            ->groupBy('group')
            ->sortBy(function (Collection $rows, string $group) use ($order): int {
                $at = array_search($group, $order, true);

                // `?:` would send position 0 — the first group — to the end.
                return $at === false ? PHP_INT_MAX : $at;
            });
    }

    /** @return array<int, array{value: string, label: string}> */
    #[Computed]
    public function weekdays(): array
    {
        return PlatformSetting::weekdayOptions();
    }

    /** How many knobs sit away from what the code ships with. */
    #[Computed]
    public function movedCount(): int
    {
        return PlatformSetting::board()
            ->filter(fn (PlatformSetting $s): bool => (string) ($this->form->value[$s->id] ?? '') !== (string) $s->default_value)
            ->count();
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());

        unset($this->groups, $this->movedCount);
    }

    /** Puts every knob back on screen; nothing is written until she saves. */
    public function restoreDefaults(): void
    {
        $this->form->restoreDefaults();

        unset($this->movedCount);
    }

    /** The tab is copy: a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('admin.settings.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('admin.settings.title')" :sub="__('admin.settings.sub')">
        @if ($this->movedCount > 0)
            <x-ui.badge variant="accent">
                {{ trans_choice('admin.settings.moved', $this->movedCount, ['count' => $this->movedCount]) }}
            </x-ui.badge>
        @endif
    </x-ui.page-head>

    @foreach ($this->groups as $group => $settings)
        <x-ui.card class="mb-3 p-5" wire:key="group-{{ $group }}">
            <h2 class="aiu-section">{{ __('admin.settings.groups.'.$group.'.title') }}</h2>
            <p class="aiu-foot" style="margin-top:0">{{ __('admin.settings.groups.'.$group.'.sub') }}</p>

            {{-- A table, so the knob, what it does and where the code left it
            are three columns and not three sentences per row. --}}
            <div class="pay-table-wrap">
                <table class="pay-table aim-assign">
                    <thead>
                        <tr>
                            <th>{{ __('admin.settings.columns.name') }}</th>
                            <th class="is-pick">{{ __('admin.settings.columns.value') }}</th>
                            <th class="is-num">{{ __('admin.settings.columns.default') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($settings as $setting)
                            <tr wire:key="set-{{ $setting->id }}">
                                <td class="is-name is-key" data-label="{{ __('admin.settings.columns.name') }}">
                                    <span class="aiu-name">{{ __('admin.settings.keys.'.$setting->key.'.label') }}</span>
                                    {{-- The consequence, not the config path: a knob
                                    whose effect she has to guess gets left alone. --}}
                                    <span class="aiu-note">{{ __('admin.settings.keys.'.$setting->key.'.hint') }}</span>
                                </td>
                                <td class="is-pick" data-label="{{ __('admin.settings.columns.value') }}">
                                    @if ($setting->type === 'weekday')
                                        <x-inputsform.combobox
                                            size="s"
                                            :name="'value.'.$setting->id"
                                            :options="$this->weekdays"
                                            :value="(string) ($form->value[$setting->id] ?? '')"
                                            :aria-label="__('admin.settings.keys.'.$setting->key.'.label')"
                                            wire:model="form.value.{{ $setting->id }}"
                                        />
                                    @else
                                        <x-inputsform.input
                                            size="s"
                                            :name="'value.'.$setting->id"
                                            :type="$setting->type === 'time' ? 'time' : 'number'"
                                            :aria-label="__('admin.settings.keys.'.$setting->key.'.label')"
                                            wire:model="form.value.{{ $setting->id }}"
                                        />
                                    @endif
                                </td>
                                <td class="is-num font-mono" data-label="{{ __('admin.settings.columns.default') }}">
                                    {{ $setting->type === 'weekday'
                                        ? (__('admin.settings.weekdays')[(int) $setting->default_value] ?? $setting->default_value)
                                        : $setting->default_value }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endforeach

    <div class="aim-actions">
        <x-ui.button variant="primary" icon="check" wire:click="save">
            {{ __('admin.settings.save') }}
        </x-ui.button>

        {{-- Nothing is written until she saves: the button only puts the
        defaults back on screen, so changing her mind costs one Cancelar. --}}
        <x-ui.button variant="secondary" icon="rotate-ccw" wire:click="restoreDefaults">
            {{ __('admin.settings.restore') }}
        </x-ui.button>

        <span class="sup-age">{{ __('admin.settings.save_hint') }}</span>
    </div>
</div>
