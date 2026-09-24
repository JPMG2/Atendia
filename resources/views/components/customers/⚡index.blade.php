<?php

use App\Classes\Main\Client;
use App\Models\Customer;
use App\Traits\HasNotifications;
use App\Traits\ManagesCustomerSheet;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Mis clientes" — the tenant's customer directory: every person the
 * assistant has talked to, searchable and filterable. The inbox is the
 * day's flow; this screen is the asset it leaves behind.
 */
new class extends Component
{
    use HasNotifications;
    use ManagesCustomerSheet;

    public string $search = '';

    /** all | optin | birthday (this month). */
    public string $filter = 'all';

    /** The list walks in tranches; a filter change rewinds to the top. */
    public int $visible = 15;

    /** The row the sheet is open on. */
    public ?int $sheetId = null;

    /** @return Collection<int, Customer> */
    #[Computed]
    public function customers(): Collection
    {
        return Client::for(Auth::user())->directory?->customers ?? new Collection;
    }

    /**
     * Accent-free search plus the audience filters, in memory: this is
     * presentation over an already-loaded directory.
     *
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function filtered(): Collection
    {
        $customers = match ($this->filter) {
            'optin' => $this->customers->filter(fn (Customer $customer): bool => $customer->marketing_opt_in_at !== null),
            'birthday' => $this->customers->filter(fn (Customer $customer): bool => $customer->birthday?->month === now()->month),
            default => $this->customers,
        };

        $needle = Str::ascii(mb_strtolower(trim($this->search)));

        if ($needle === '') {
            return $customers->values();
        }

        return $customers->filter(fn (Customer $customer): bool => str_contains(Str::ascii(mb_strtolower((string) $customer->displayName())), $needle)
            || str_contains($customer->phone, $needle)
            || str_contains(Str::ascii(mb_strtolower((string) $customer->email)), $needle))->values();
    }

    /** @return Collection<int, Customer> */
    #[Computed]
    public function rows(): Collection
    {
        return $this->filtered->take($this->visible)->values();
    }

    public function loadMore(): void
    {
        $this->visible += 15;
    }

    public function updatedSearch(): void
    {
        $this->visible = 15;
    }

    public function updatedFilter(): void
    {
        $this->visible = 15;
    }

    public function show(int $id): void
    {
        $this->sheetId = $id;
        unset($this->customer);
        $this->openCustomer();
    }

    protected function sheetCustomer(): ?Customer
    {
        return $this->sheetId === null
            ? null
            : Client::for(Auth::user())->directory?->customer($this->sheetId);
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('client.customers.title'));
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('client.customers.title') }}</h1>
            <p class="page-head-sub">{{ __('client.customers.sub') }}</p>
        </div>
    </div>

    @if ($this->customers->isEmpty())
        <x-ui.card class="p-6">
            <div class="flex items-start gap-4">
                <div class="bg-brand-soft flex size-11 flex-none items-center justify-center rounded-xl" style="color: var(--brand)">
                    <x-icon name="users" :size="22" />
                </div>
                <div class="min-w-0">
                    <h2 class="font-display text-strong text-base">{{ __('client.customers.empty_title') }}</h2>
                    <p class="text-body mt-1 text-sm">{{ __('client.customers.empty_body') }}</p>
                </div>
            </div>
        </x-ui.card>
    @else
        <x-ui.card class="p-5">
            {{-- A declared row: the search absorbs the slack (golden rule). --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="long"
                    name="search"
                    icon="search"
                    :placeholder="__('client.customers.search')"
                    :aria-label="__('client.customers.search')"
                    wire:model.live.debounce.300ms="search"
                />
                <x-inputsform.combobox
                    span="short"
                    name="filter"
                    wire:model.live="filter"
                    :value="$filter"
                    :options="[
                        'all' => __('client.customers.filter_all'),
                        'optin' => __('client.customers.filter_optin'),
                        'birthday' => __('client.customers.filter_birthday'),
                    ]"
                />
            </x-catalog.form-row>

            <p class="bd-subtle mt-3 border-b pb-3 font-mono text-sm text-subtle">
                {{ trans_choice('client.customers.count', $this->filtered->count(), ['count' => $this->filtered->count()]) }}
            </p>

            @if ($this->rows->isEmpty())
                <p class="text-muted py-6 text-center text-sm">{{ __('client.customers.no_results') }}</p>
            @else
                <ul class="divide-y divide-[color:var(--border-subtle)]">
                    @foreach ($this->rows as $customer)
                        <li wire:key="customer-{{ $customer->id }}">
                            <button
                                type="button"
                                wire:click="show({{ $customer->id }})"
                                class="hover:bg-sunken flex w-full flex-wrap items-center gap-x-4 gap-y-1 rounded-lg px-2 py-2.5 text-left transition-colors"
                            >
                                <x-ui.avatar :name="$customer->displayName() ?? $customer->phone" size="sm" />
                                <span class="min-w-0 flex-1">
                                    <span class="text-strong block truncate text-sm font-semibold">
                                        <x-ui.match :text="$customer->displayName() ?? __('client.conversations.anonymous')" :needle="$search" />
                                    </span>
                                    <span class="text-muted block truncate font-mono text-xs">
                                        <x-ui.match :text="$customer->phone" :needle="$search" />
                                        @if ($customer->email !== null)
                                            · <x-ui.match :text="$customer->email" :needle="$search" />
                                        @endif
                                    </span>
                                </span>
                                @if ($customer->birthday?->month === now()->month)
                                    <x-ui.badge variant="accent">{{ __('client.customers.badge_birthday') }}</x-ui.badge>
                                @endif
                                @if ($customer->marketing_opt_in_at !== null)
                                    <x-ui.badge variant="brand">{{ __('client.customers.badge_optin') }}</x-ui.badge>
                                @endif
                                <span class="text-subtle flex-none font-mono text-xs">
                                    {{ $customer->last_activity_at?->inBusinessTime()->isToday() ? $customer->last_activity_at->inBusinessTime()->format('H:i') : $customer->last_activity_at?->inBusinessTime()->format('d/m') }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <x-ui.load-more :shown="$this->rows->count()" :total="$this->filtered->count()" />
            @endif
        </x-ui.card>

        <x-client.customer-sheet
            :customer="$this->customer"
            :duplicate="$this->customerDuplicate"
            :form="$customerForm"
            :show="$showCustomer"
        />
    @endif
</div>
