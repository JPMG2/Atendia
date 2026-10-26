<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Business\RequestCustomerOptIn;
use App\Models\Business;
use App\Models\Customer;
use App\Services\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The "Clientes" piece: the tenant's customer directory. The inbox is
 * the day's flow; this is the asset it leaves behind.
 */
class Directory
{
    public function __construct(private Business $business) {}

    /**
     * Every customer, freshest activity first.
     *
     * @var Collection<int, Customer>
     */
    public Collection $customers {
        get => $this->business->customers()
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /**
     * The directory as the screen shows it: an audience filter (all, opted in,
     * birthday this month) and an accent-free search over name, phone and
     * e-mail. One place, so the screen and its report can never disagree.
     *
     * @return Collection<int, Customer>
     */
    public function filtered(string $search = '', string $filter = 'all'): Collection
    {
        $thisMonth = now(app(Tenant::class)->timezone())->month;

        $customers = match ($filter) {
            'optin' => $this->customers->filter(fn (Customer $customer): bool => $customer->marketing_opt_in_at !== null),
            'birthday' => $this->customers->filter(fn (Customer $customer): bool => $customer->birthday?->month === $thisMonth),
            default => $this->customers,
        };

        $needle = Str::ascii(mb_strtolower(trim($search)));

        if ($needle === '') {
            return $customers->values();
        }

        return $customers->filter(fn (Customer $customer): bool => str_contains(Str::ascii(mb_strtolower((string) $customer->displayName())), $needle)
            || str_contains($customer->phone, $needle)
            || str_contains(Str::ascii(mb_strtolower((string) $customer->email)), $needle))->values();
    }

    /** One customer of THIS business, or null when it is not the tenant's. */
    public function customer(int $id): ?Customer
    {
        return $this->business->customers()->find($id);
    }

    /**
     * The words the app prints for the directory, the web screen's own, so the
     * phone never writes a sentence of its own.
     *
     * @var array<string, mixed>
     */
    public array $copy {
        get => [
            'title' => __('client.customers.title'),
            'sub' => __('client.customers.sub'),
            'search' => __('client.customers.search'),
            'filters' => [
                'all' => __('client.customers.filter_all'),
                'optin' => __('client.customers.filter_optin'),
                'birthday' => __('client.customers.filter_birthday'),
            ],
            'noResults' => __('client.customers.no_results'),
            'loadMore' => __('pagination.load_more'),
            'emptyTitle' => __('client.customers.empty_title'),
            'emptyBody' => __('client.customers.empty_body'),
            'fields' => [
                'name' => __('client.customers.field_name'),
                'email' => __('client.customers.field_email'),
                'birthday' => __('client.customers.field_birthday'),
                'notes' => __('client.customers.field_notes'),
            ],
            'save' => __('client.customers.save'),
            'cancel' => __('client.customers.cancel'),
            'mergeButton' => __('client.customers.merge_button'),
            'mergeConfirmTitle' => __('client.customers.merge_confirm_title'),
            'mergeConfirmBody' => __('client.customers.merge_confirm_body'),
        ];
    }

    /**
     * One page of the directory as the screen lists it, from the same cut the
     * web and its export use.
     *
     * @return array{any: bool, count: int, countLabel: string, rows: list<array<string, mixed>>, hasMore: bool, showing: string}
     */
    public function page(string $search, string $filter, int $page, int $perPage = 15): array
    {
        $filtered = $this->filtered($search, $filter);

        return [
            // The web shows its "no customers yet" card only when the business has none at all.
            'any' => $this->business->customers()->exists(),
            'count' => $filtered->count(),
            'countLabel' => trans_choice('client.customers.count', $filtered->count(), ['count' => $filtered->count()]),
            'rows' => $filtered->slice(($page - 1) * $perPage, $perPage)->map(fn (Customer $customer): array => $this->rowOf($customer))->values()->all(),
            'hasMore' => $filtered->count() > $page * $perPage,
            // The list footer's own line, "15 de 48", as the web's load-more prints it.
            'showing' => __('pagination.showing', ['shown' => min($page * $perPage, $filtered->count()), 'total' => $filtered->count()]),
        ];
    }

    /**
     * A row of the list: who, how to reach them, the two badges and when they
     * last wrote, as the web's row says it.
     *
     * @return array<string, mixed>
     */
    public function rowOf(Customer $customer): array
    {
        $last = $customer->last_activity_at?->inBusinessTime();

        return [
            'id' => $customer->id,
            'name' => $customer->displayName() ?? __('client.conversations.anonymous'),
            'avatarName' => $customer->displayName() ?? $customer->phone,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'badges' => array_values(array_filter([
                $customer->birthday?->month === now(app(Tenant::class)->timezone())->month ? ['kind' => 'birthday', 'label' => __('client.customers.badge_birthday')] : null,
                $customer->marketing_opt_in_at !== null ? ['kind' => 'optin', 'label' => __('client.customers.badge_optin')] : null,
            ])),
            'last' => $last === null ? null : ($last->isToday() ? $last->format('H:i') : $last->format('d/m')),
        ];
    }

    /**
     * The fiche the web opens on a person: what is known, what the assistant
     * learned on its own, the merge suggestion and where the consent stands.
     *
     * @return array<string, mixed>
     */
    public function sheetOf(Customer $customer): array
    {
        $duplicate = $customer->duplicateOf();
        $consent = $customer->marketing_opt_in_at;
        $asked = $customer->marketing_opt_in_requested_at;

        return [
            'id' => $customer->id,
            'title' => $customer->displayName() ?? __('client.conversations.anonymous'),
            'since' => __('client.customers.since', ['date' => $customer->first_seen_at?->inBusinessTime()->format('d/m/Y')]),
            'phone' => $customer->phone,
            'country' => $customer->country_code === null ? null : strtoupper($customer->country_code),
            'pushName' => $customer->profile_name === null ? null : __('client.customers.push_name', ['name' => $customer->profile_name]),
            'values' => [
                'name' => (string) $customer->name,
                'email' => (string) $customer->email,
                'birthday' => (string) $customer->birthday?->toDateString(),
                'notes' => (string) $customer->notes,
            ],
            // What the assistant picked up by itself, one line per field, as the web prints it.
            'learned' => collect(['name', 'email', 'birthday'])
                ->filter(fn (string $field): bool => isset($customer->ai_extracted[$field]) && is_scalar($customer->ai_extracted[$field]['value'] ?? null))
                ->mapWithKeys(fn (string $field): array => [$field => __('client.customers.ai_badge', [
                    'field' => __('client.customers.field_'.$field),
                    'value' => $customer->ai_extracted[$field]['value'],
                ])])->all(),
            'duplicate' => $duplicate === null ? null : [
                'id' => $duplicate->id,
                'title' => __('client.customers.merge_title'),
                'body' => __('client.customers.merge_body', ['name' => $duplicate->displayName() ?? $duplicate->phone]),
            ],
            'optIn' => [
                'granted' => $consent !== null,
                'text' => $consent !== null
                    ? __('client.customers.opt_in_yes', ['date' => $consent->inBusinessTime()->format('d/m/Y')])
                    : ($asked !== null ? __('client.customers.opt_in_requested', ['date' => $asked->inBusinessTime()->format('d/m/Y')]) : null),
                'button' => $consent === null ? __('client.customers.opt_in_button') : null,
            ],
        ];
    }

    /** @param  array{name: ?string, email: ?string, birthday: ?string, notes: ?string}  $validated  Already validated. */
    public function save(Customer $customer, array $validated): void
    {
        $customer->update($validated);
    }

    /** @return bool false when the business cannot message customers right now (the web says "connect your WhatsApp") */
    public function requestOptIn(Customer $customer): bool
    {
        return app(RequestCustomerOptIn::class)->handle($this->business, $customer);
    }

    /**
     * The twin's threads move to this person and its row dies. The phone says WHICH twin
     * it showed: if the twin changed since, nothing is folded.
     *
     * @return bool|null null when there is no twin; false when the twin is no longer the one shown
     */
    public function merge(Customer $customer, int $shownTwinId): ?bool
    {
        $duplicate = $customer->duplicateOf();

        if ($duplicate === null) {
            return null;
        }

        if ($duplicate->id !== $shownTwinId) {
            return false;
        }

        $customer->mergeFrom($duplicate);

        return true;
    }
}
