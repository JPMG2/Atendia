<?php

use App\Enums\PanelNotificationType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Which kinds this person wants on their bell. Muting hides the row from THEM
 * only: the notice belongs to the business, so a teammate who still wants it
 * keeps getting it.
 */
new class extends Component
{
    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    /** @return list<PanelNotificationType> */
    #[Computed]
    public function kinds(): array
    {
        return PanelNotificationType::cases();
    }

    public function toggle(string $type): void
    {
        $kind = PanelNotificationType::tryFrom($type);

        if ($kind === null) {
            return;
        }

        $this->user->toggleBellType($kind, in_array($type, $this->user->mutedBellTypes(), true));

        unset($this->user);
    }
};
?>

<x-ui.card id="avisos" class="bp-card">
    <div class="bp-card-head">
        <h2>{{ __('bell.settings.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('bell.settings.sub') }}</p>

    <ul class="bell-prefs">
        @foreach ($this->kinds as $kind)
            @php($wanted = ! in_array($kind->value, $this->user->mutedBellTypes(), true))

            <li wire:key="pref-{{ $kind->value }}" class="bell-pref">
                <span class="bell-mark" style="{{ $kind->tintStyle() }}">
                    <x-icon :name="$kind->icon()" :size="16" />
                </span>

                <span class="bell-pref-text">{{ __('bell.settings.kinds.'.$kind->value) }}</span>

                <x-ui.switch
                    :name="'bell-'.$kind->value"
                    :checked="$wanted"
                    size="sm"
                    data-testid="bell-pref-{{ $kind->value }}"
                    wire:click="toggle('{{ $kind->value }}')"
                />
            </li>
        @endforeach
    </ul>
</x-ui.card>
