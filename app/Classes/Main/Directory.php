<?php

declare(strict_types=1);

namespace App\Classes\Main;

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
}
