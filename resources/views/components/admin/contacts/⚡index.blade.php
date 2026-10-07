<?php

use App\Models\PlatformContact;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\View\View as ViewContract;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Who reaches the platform, across every business at once.
 *
 * `platform_contacts` has been filling since the first message and nobody
 * ever looked at it. The one thing it knows that no business can see from
 * its own panel is the overlap: the same person writing to two of them.
 */
new class extends Component
{
    /** @return Collection<int, PlatformContact> */
    #[Computed]
    public function people(): Collection
    {
        return PlatformContact::radar();
    }

    /** @return array{people: int, shared: int, conversations: int, countries: int} */
    #[Computed]
    public function reach(): array
    {
        return PlatformContact::reach();
    }

    public function render(): ViewContract
    {
        // A full-page admin screen, so this title DOES reach the tab.
        return $this->view()->title(__('contacts.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('contacts.title')" :sub="__('contacts.sub')">
        <span class="sup-age">{{ __('contacts.read_at', ['time' => now()->translatedFormat('d/m/Y H:i')]) }}</span>
    </x-ui.page-head>

    @if ($this->people->isEmpty())
        <x-ui.card class="p-5">
            <x-ui.empty-state
                icon="users"
                :title="__('contacts.empty_title')"
                :body="__('contacts.empty_body')"
            />
        </x-ui.card>
    @else
        <div class="stat-grid stat-grid-fill mb-3">
            <x-ui.stat-card :label="__('contacts.reach.people')" :value="$this->reach['people']" icon="users" tint="brand" />
            <x-ui.stat-card :label="__('contacts.reach.shared')" :value="$this->reach['shared']" icon="store" tint="accent" />
            <x-ui.stat-card :label="__('contacts.reach.conversations')" :value="$this->reach['conversations']" icon="message-circle" tint="info" />
            <x-ui.stat-card :label="__('contacts.reach.countries')" :value="$this->reach['countries']" icon="map-pin" tint="warning" />
        </div>

        {{-- Said out loud instead of leaving a zero to interpret: nobody
        sharing businesses yet is a fact about today, not a broken screen. --}}
        @if ($this->reach['shared'] === 0)
            <p class="sup-meta">{{ __('contacts.none_shared') }}</p>
        @endif

        <x-ui.card class="p-5">
            <div class="pay-table-wrap">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>{{ __('contacts.table.phone') }}</th>
                            <th>{{ __('contacts.table.name') }}</th>
                            <th>{{ __('contacts.table.businesses') }}</th>
                            <th>{{ __('contacts.table.conversations') }}</th>
                            <th>{{ __('contacts.table.country') }}</th>
                            <th>{{ __('contacts.table.last_seen') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->people as $person)
                            <tr wire:key="contact-{{ $person->id }}">
                                {{-- data-label: stacked below 991px the header row is gone,
                                and a cell without its name is a loose value. --}}
                                <td data-label="{{ __('contacts.table.phone') }}" class="font-mono">{{ $person->phone }}</td>
                                <td data-label="{{ __('contacts.table.name') }}">
                                    {{ $person->name ?? __('contacts.unknown') }}
                                </td>
                                <td data-label="{{ __('contacts.table.businesses') }}" class="font-mono">
                                    @if ($person->businesses_count > 1)
                                        <span class="status-tag is-brand">{{ __('contacts.shared_tag', ['count' => $person->businesses_count]) }}</span>
                                    @else
                                        {{ $person->businesses_count }}
                                    @endif
                                </td>
                                <td data-label="{{ __('contacts.table.conversations') }}" class="font-mono">{{ $person->conversations_count }}</td>
                                <td data-label="{{ __('contacts.table.country') }}" class="font-mono">{{ $person->country_code ?? __('contacts.unknown') }}</td>
                                <td data-label="{{ __('contacts.table.last_seen') }}" class="font-mono">
                                    {{ $person->last_activity_at?->translatedFormat('d/m/Y') ?? __('contacts.unknown') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif
</div>
